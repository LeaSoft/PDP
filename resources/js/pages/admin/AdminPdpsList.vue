<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import AdminTable from '@/components/admin/AdminTable.vue'
import { fetchJson } from '@/lib/csrf'

type Row = {
  pdp_id: number
  title: string
  owner_name: string
  curator_name: string
  active_entries_count: number
  pending_entries_count: number
}

const loading = ref(false)
const rows = ref<Row[]>([])
const total = ref(0)
const pageNum = ref(1)
const perPage = ref(20)
const sortBy = ref('pending_entries_count')
const sortDir = ref<'asc'|'desc'>('desc')

const status = ref<string | null>(new URLSearchParams(location.search).get('status'))

const columns = [
  { key: 'title', label: 'Title', sortable: true },
  { key: 'owner_name', label: 'Owner', sortable: true },
  { key: 'curator_name', label: 'Curator', sortable: true },
  { key: 'active_entries_count', label: 'Active Entries', sortable: true },
  { key: 'pending_entries_count', label: 'Pending Entries', sortable: true },
]

function toQuery() {
  const p = new URLSearchParams()
  if (status.value) p.set('status', String(status.value))
  p.set('page', String(pageNum.value))
  p.set('per_page', String(perPage.value))
  p.set('sort_by', String(sortBy.value))
  p.set('sort_dir', String(sortDir.value))
  return p
}

async function load() {
  loading.value = true
  try {
    const qs = toQuery().toString()
    const res = await fetchJson(`/admin/pdps.json?${qs}`)
    rows.value = Array.isArray(res?.data) ? res.data : []
    total.value = Number(res?.meta?.total || 0)
  } finally {
    loading.value = false
  }
}

function syncUrl() {
  const q = toQuery().toString()
  router.visit(`/admin/pdps?${q}`, { preserveState: true, preserveScroll: true, replace: true, only: [] })
}

watch([pageNum, perPage, sortBy, sortDir], () => { syncUrl(); load() })

onMounted(() => {
  const url = new URL(location.href)
  pageNum.value = Number(url.searchParams.get('page') || 1)
  perPage.value = Number(url.searchParams.get('per_page') || 20)
  sortBy.value = url.searchParams.get('sort_by') || 'pending_entries_count'
  sortDir.value = (url.searchParams.get('sort_dir') as any) || 'desc'
  load()
})

function rowClass(r: Row) {
  if (r.pending_entries_count > 10) return 'bg-rose-50'
  if (r.pending_entries_count > 0) return 'bg-amber-50'
  return ''
}
</script>

<template>
  <AdminLayout>
    <div class="mb-4 flex items-center justify-between">
      <h1 class="text-lg font-semibold">PDPs</h1>
      <div class="flex items-center gap-2 text-xs">
        <label>Status
          <select class="ml-1 rounded border px-2 py-1" v-model="status" @change="pageNum = 1; syncUrl(); load()">
            <option :value="null">All</option>
            <option value="active">Active</option>
            <option value="done">Done</option>
          </select>
        </label>
      </div>
    </div>

    <AdminTable
      :columns="columns"
      :rows="rows"
      :total="total"
      :page="pageNum"
      :per-page="perPage"
      :sort-by="sortBy"
      :sort-dir="sortDir as any"
      :loading="loading"
      :row-class="rowClass"
      @update:page="pageNum = $event"
      @update:perPage="perPage = $event; pageNum = 1"
      @update:sort="(s) => { sortBy = s.by; sortDir = s.dir; pageNum = 1 }"
    />
  </AdminLayout>
</template>
