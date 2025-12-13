<script setup lang="ts">
import { computed } from 'vue'

type Column = {
  key: string
  label: string
  sortable?: boolean
  width?: string
  align?: 'left'|'center'|'right'
}

interface Props {
  columns: Column[]
  rows: any[]
  total: number
  page: number
  perPage: number
  sortBy: string
  sortDir: 'asc'|'desc'
  loading?: boolean
  emptyText?: string
  rowClass?: (row: any) => string | undefined
}

const props = withDefaults(defineProps<Props>(), {
  rows: () => [],
  loading: false,
  emptyText: 'No data',
  rowClass: undefined,
})

const emit = defineEmits<{
  (e: 'update:page', value: number): void
  (e: 'update:perPage', value: number): void
  (e: 'update:sort', value: { by: string; dir: 'asc'|'desc' }): void
}>()

const totalPages = computed(() => Math.max(1, Math.ceil((props.total || 0) / (props.perPage || 1))))

function toggleSort(col: Column) {
  if (!col.sortable) return
  const dir = props.sortBy === col.key ? (props.sortDir === 'asc' ? 'desc' : 'asc') : 'asc'
  emit('update:sort', { by: col.key, dir })
}

function toPage(p: number) {
  const clamped = Math.min(Math.max(1, p), totalPages.value)
  emit('update:page', clamped)
}

function changePerPage(e: Event) {
  const v = Number((e.target as HTMLSelectElement).value)
  emit('update:perPage', v)
}
</script>

<template>
  <div class="rounded-md border overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full table-auto text-sm">
        <thead class="bg-muted/40">
          <tr>
            <th v-for="col in columns" :key="col.key" :style="{ width: col.width || undefined }" class="px-3 py-2 text-left text-xs font-medium text-muted-foreground select-none">
              <button v-if="col.sortable" class="inline-flex items-center gap-1" @click="toggleSort(col)">
                <span>{{ col.label }}</span>
                <span v-if="sortBy === col.key">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
              </button>
              <span v-else>{{ col.label }}</span>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td :colspan="columns.length" class="px-3 py-6 text-center text-muted-foreground">Loading…</td>
          </tr>
          <tr v-else-if="rows.length === 0">
            <td :colspan="columns.length" class="px-3 py-6 text-center text-muted-foreground">{{ emptyText }}</td>
          </tr>
          <tr v-else v-for="(row, idx) in rows" :key="idx" :class="rowClass ? rowClass(row) : ''" class="border-t">
            <td v-for="col in columns" :key="col.key" class="px-3 py-2 align-top">
              <slot :name="`cell:${col.key}`" :row="row">{{ (row as any)[col.key] }}</slot>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="flex items-center justify-between border-t px-3 py-2 text-xs">
      <div class="flex items-center gap-2">
        <span>Rows per page</span>
        <select class="rounded border px-2 py-1" :value="perPage" @change="changePerPage">
          <option :value="10">10</option>
          <option :value="20">20</option>
          <option :value="50">50</option>
          <option :value="100">100</option>
        </select>
        <span class="text-muted-foreground">Total: {{ total }}</span>
      </div>
      <div class="flex items-center gap-2">
        <button class="rounded border px-2 py-1" :disabled="page <= 1" @click="toPage(page - 1)">Prev</button>
        <span>Page {{ page }} / {{ totalPages }}</span>
        <button class="rounded border px-2 py-1" :disabled="page >= totalPages" @click="toPage(page + 1)">Next</button>
      </div>
    </div>
  </div>

</template>
