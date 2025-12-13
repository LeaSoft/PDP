<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import AdminTable from '@/components/admin/AdminTable.vue'
import { fetchJson } from '@/lib/csrf'
import { notifyError, notifySuccess } from '@/composables/useNotify'

type Row = {
  curator_id: number
  curator_name: string
  total_assigned_users: number
  pending_entries_count: number
  avg_approval_time_days: number
}

const loading = ref(false)
const rows = ref<Row[]>([])
const total = ref(0)
const pageNum = ref(1)
const perPage = ref(20)
const sortBy = ref('pending_entries_count')
const sortDir = ref<'asc'|'desc'>('desc')

const overloaded = ref<number | null>(new URLSearchParams(location.search).get('overloaded') ? Number(new URLSearchParams(location.search).get('overloaded')) : null)

const columns = [
  { key: 'curator_name', label: 'Curator', sortable: true },
  { key: 'total_assigned_users', label: 'Assigned Users', sortable: true },
  { key: 'pending_entries_count', label: 'Pending Entries', sortable: true },
  { key: 'avg_approval_time_days', label: 'Avg Approval (days)', sortable: true },
  { key: 'actions', label: '', width: '120px' },
]

function toQuery() {
  const p = new URLSearchParams()
  if (overloaded.value != null) p.set('overloaded', String(overloaded.value))
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
    const res = await fetchJson(`/admin/curators.json?${qs}`)
    rows.value = Array.isArray(res?.data) ? res.data : []
    total.value = Number(res?.meta?.total || 0)
  } catch (e: any) {
    notifyError(e?.message || 'Failed to load curators')
  } finally {
    loading.value = false
  }
}

function syncUrl() {
  const q = toQuery().toString()
  router.visit(`/admin/curators?${q}`, { preserveState: true, preserveScroll: true, replace: true, only: [] })
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
  if (r.pending_entries_count > 0) return 'bg-rose-50'
  return ''
}

async function nudge(row: Row) {
  try {
    await fetchJson(`/admin/curators/${row.curator_id}/nudge.json`, { method: 'POST' })
    notifySuccess(`Nudged ${row.curator_name}`)
  } catch (e: any) {
    notifyError(e?.message || 'Failed to nudge curator')
  }
}
</script>

<template>
  <AdminLayout>
    <div class="mb-4 flex items-center justify-between">
      <h1 class="text-lg font-semibold">Curators</h1>
      <div class="flex items-center gap-2 text-xs">
        <label>Overloaded only
          <select class="ml-1 rounded border px-2 py-1" v-model.number="overloaded" @change="pageNum = 1; syncUrl(); load()">
            <option :value="null">All</option>
            <option :value="1">Yes</option>
            <option :value="0">No</option>
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
    >
      <template #cell:actions="{ row }">
        <button class="rounded border px-2 py-1 text-xs" @click="nudge(row)">Nudge</button>
      </template>
    </AdminTable>
  </AdminLayout>

</template>
