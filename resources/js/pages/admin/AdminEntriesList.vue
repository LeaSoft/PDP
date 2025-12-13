<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import AdminTable from '@/components/admin/AdminTable.vue'
import { fetchJson } from '@/lib/csrf'

type Row = {
  entry_id: number
  user_name: string
  pdp_title: string
  skill_name: string
  status: string
  days_pending: number
  assigned_curator_name: string
}

const loading = ref(false)
const rows = ref<Row[]>([])
const total = ref(0)
const pageNum = ref(1)
const perPage = ref(20)
const sortBy = ref('created_at')
const sortDir = ref<'asc'|'desc'>('desc')

// Filters from URL
const status = ref<string | null>(new URLSearchParams(location.search).get('status'))
const olderThan = ref<number | null>(new URLSearchParams(location.search).get('older_than') ? Number(new URLSearchParams(location.search).get('older_than')) : null)
const curatorId = ref<number | null>(new URLSearchParams(location.search).get('curator_id') ? Number(new URLSearchParams(location.search).get('curator_id')) : null)

const columns = [
  { key: 'entry_id', label: 'ID' },
  { key: 'user_name', label: 'User', sortable: true },
  { key: 'pdp_title', label: 'PDP', sortable: true },
  { key: 'skill_name', label: 'Skill' },
  { key: 'status', label: 'Status', sortable: true },
  { key: 'days_pending', label: 'Days Pending', sortable: true },
  { key: 'assigned_curator_name', label: 'Curator' },
]

function toQuery() {
  const p = new URLSearchParams()
  if (status.value) p.set('status', status.value)
  if (olderThan.value) p.set('older_than', String(olderThan.value))
  if (curatorId.value) p.set('curator_id', String(curatorId.value))
  p.set('page', String(pageNum.value))
  p.set('per_page', String(perPage.value))
  p.set('sort_by', sortBy.value)
  p.set('sort_dir', sortDir.value)
  return p
}

async function load() {
  loading.value = true
  try {
    const qs = toQuery().toString()
    const res = await fetchJson(`/admin/entries.json?${qs}`)
    rows.value = Array.isArray(res?.data) ? res.data : []
    total.value = Number(res?.meta?.total || 0)
  } finally {
    loading.value = false
  }
}

function syncUrl() {
  const q = toQuery().toString()
  router.visit(`/admin/entries?${q}`, { preserveState: true, preserveScroll: true, replace: true, only: [] })
}

watch([pageNum, perPage, sortBy, sortDir], () => { syncUrl(); load() })

onMounted(() => {
  const url = new URL(location.href)
  pageNum.value = Number(url.searchParams.get('page') || 1)
  perPage.value = Number(url.searchParams.get('per_page') || 20)
  sortBy.value = url.searchParams.get('sort_by') || 'created_at'
  sortDir.value = (url.searchParams.get('sort_dir') as any) || 'desc'
  load()
})

function rowClass(r: Row) {
  if (r.status === 'pending' && r.days_pending > 10) return 'bg-rose-50'
  if (r.status === 'pending' && r.days_pending > 0) return 'bg-amber-50'
  return ''
}
</script>

<template>
  <AdminLayout>
    <div class="mb-4 flex items-center justify-between">
      <h1 class="text-lg font-semibold">Entries</h1>
      <div class="flex items-center gap-2 text-xs">
        <label>Status
          <select class="ml-1 rounded border px-2 py-1" v-model="status" @change="pageNum = 1; syncUrl(); load()">
            <option :value="null">Any</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
          </select>
        </label>
        <label>Older than (days)
          <input class="ml-1 w-20 rounded border px-2 py-1" type="number" min="0" v-model.number="olderThan" @change="pageNum = 1; syncUrl(); load()" />
        </label>
        <label>Curator ID
          <input class="ml-1 w-24 rounded border px-2 py-1" type="number" min="1" v-model.number="curatorId" @change="pageNum = 1; syncUrl(); load()" />
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
    >
      <template #cell:entry_id="{ row }">
        <span class="font-mono">#{{ row.entry_id }}</span>
      </template>
    </AdminTable>
  </AdminLayout>
</template>
