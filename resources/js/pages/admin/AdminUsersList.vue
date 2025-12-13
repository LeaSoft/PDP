<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import AdminTable from '@/components/admin/AdminTable.vue'
import { fetchJson } from '@/lib/csrf'

type Row = {
  user_id: number
  name: string
  email: string
  last_activity_at: string | null
  pdp_count: number
}

const loading = ref(false)
const rows = ref<Row[]>([])
const total = ref(0)
const pageNum = ref(1)
const perPage = ref(20)
const sortBy = ref('last_activity_at')
const sortDir = ref<'asc'|'desc'>('desc')

const activeLastDays = ref<number | null>(new URLSearchParams(location.search).get('active_last_days') ? Number(new URLSearchParams(location.search).get('active_last_days')) : null)

const columns = [
  { key: 'name', label: 'Name', sortable: true },
  { key: 'email', label: 'Email', sortable: true },
  { key: 'last_activity_at', label: 'Last Activity', sortable: true },
  { key: 'pdp_count', label: 'PDPs', sortable: true },
]

function toQuery() {
  const p = new URLSearchParams()
  if (activeLastDays.value) p.set('active_last_days', String(activeLastDays.value))
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
    const res = await fetchJson(`/admin/users.json?${qs}`)
    rows.value = Array.isArray(res?.data) ? res.data : []
    total.value = Number(res?.meta?.total || 0)
  } finally {
    loading.value = false
  }
}

function syncUrl() {
  const q = toQuery().toString()
  router.visit(`/admin/users?${q}`, { preserveState: true, preserveScroll: true, replace: true, only: [] })
}

watch([pageNum, perPage, sortBy, sortDir], () => { syncUrl(); load() })

onMounted(() => {
  const url = new URL(location.href)
  pageNum.value = Number(url.searchParams.get('page') || 1)
  perPage.value = Number(url.searchParams.get('per_page') || 20)
  sortBy.value = url.searchParams.get('sort_by') || 'last_activity_at'
  sortDir.value = (url.searchParams.get('sort_dir') as any) || 'desc'
  load()
})
</script>

<template>
  <AdminLayout>
    <div class="mb-4 flex items-center justify-between">
      <h1 class="text-lg font-semibold">Users</h1>
      <div class="flex items-center gap-2 text-xs">
        <label>Active last (days)
          <input class="ml-1 w-20 rounded border px-2 py-1" type="number" min="0" v-model.number="activeLastDays" @change="pageNum = 1; syncUrl(); load()" />
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
      @update:page="pageNum = $event"
      @update:perPage="perPage = $event; pageNum = 1"
      @update:sort="(s) => { sortBy = s.by; sortDir = s.dir; pageNum = 1 }"
    >
      <template #cell:last_activity_at="{ row }">
        <span>{{ row.last_activity_at ? new Date(row.last_activity_at).toLocaleString() : '—' }}</span>
      </template>
    </AdminTable>
  </AdminLayout>
</template>
