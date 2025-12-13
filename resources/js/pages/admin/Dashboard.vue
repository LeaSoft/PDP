<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { fetchJson } from '@/lib/csrf'

type Metrics = {
  users: { total: number; active_last_7_days: number }
  pdps: { total: number; active: number }
  entries: { pending: number; pending_over_7_days: number }
  curators: { total: number; overloaded: number }
}

const loading = ref(false)
const error = ref<string | null>(null)
const data = ref<Metrics | null>(null)
const lastUpdatedAt = ref<string | null>(null)
let intervalId: number | null = null

async function load() {
  loading.value = true
  error.value = null
  try {
    const res = await fetchJson('/admin/dashboard')
    data.value = res as Metrics
    lastUpdatedAt.value = new Date().toLocaleTimeString()
  } catch (e: any) {
    error.value = e?.message || 'Failed to load metrics'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  load()
  // Auto-refresh every ~90 seconds
  intervalId = window.setInterval(load, 90_000)
})

onBeforeUnmount(() => {
  if (intervalId) window.clearInterval(intervalId)
})

function statusColor(value: number, goodHigh = true): 'green' | 'yellow' | 'red' {
  // Simple heuristic; can be refined later via thresholds
  if (goodHigh) {
    if (value <= 0) return 'yellow'
    return 'green'
  }
  // When fewer is better
  if (value === 0) return 'green'
  if (value < 10) return 'yellow'
  return 'red'
}

function cardClass(color: 'green' | 'yellow' | 'red') {
  return {
    green: 'border-emerald-500/40 bg-emerald-500/10 text-emerald-700',
    yellow: 'border-amber-500/40 bg-amber-500/10 text-amber-700',
    red: 'border-rose-500/40 bg-rose-500/10 text-rose-700',
  }[color]
}

function navigateTo(feature: string) {
  // Card → Drill-down mapping
  switch (feature) {
    case 'users':
      router.visit('/admin/users', { preserveState: true })
      break
    case 'entries-pending':
      router.visit('/admin/entries?status=pending', { preserveState: true })
      break
    case 'entries-pending-7d':
      router.visit('/admin/entries?status=pending&older_than=7', { preserveState: true })
      break
    case 'curators-overloaded':
      router.visit('/admin/curators?overloaded=1', { preserveState: true })
      break
    case 'curators':
      router.visit('/admin/curators', { preserveState: true })
      break
    case 'users-active':
      router.visit('/admin/users?active_last_days=7', { preserveState: true })
      break
    case 'pdps':
      router.visit('/admin/pdps', { preserveState: true })
      break
    case 'pdps-active':
      router.visit('/admin/pdps?status=active', { preserveState: true })
      break
    default:
      // No-op for total tiles
      break
  }
}

function tooltipFor(card: string): string | undefined {
  if (!data.value) return undefined
  if (card === 'entries-pending') {
    const v = data.value.entries.pending
    if (v > 10) return 'Red: pending entries > 10'
    if (v > 0) return 'Yellow: pending entries > 0'
  }
  if (card === 'entries-pending-7d') {
    const v = data.value.entries.pending_over_7_days
    if (v > 10) return 'Red: entries pending more than 7 days > 10'
    if (v > 0) return 'Yellow: entries pending more than 7 days > 0'
  }
  if (card === 'curators-overloaded') {
    const v = data.value.curators.overloaded
    if (v > 0) return 'Red: at least one curator is overloaded'
  }
  return undefined
}
</script>

<template>
  <AdminLayout>
    <div class="mx-auto max-w-7xl px-4 md:px-6 pt-6 md:pt-8">
      <div class="mb-8 flex items-center justify-between">
        <h1 class="text-xl font-semibold">Admin — Global Overview</h1>
        <button class="rounded-md border px-3 py-1 text-sm" @click="load" :disabled="loading">Refresh</button>
      </div>

      <div v-if="lastUpdatedAt" class="mb-5 text-xs text-muted-foreground">Last updated at {{ lastUpdatedAt }}</div>

      <div v-if="error" class="mb-5 rounded-md border border-rose-200 bg-rose-50 p-3 text-rose-700">
        {{ error }}
      </div>

      <div v-if="loading" class="text-sm text-muted-foreground">Loading…</div>

      <div v-else-if="data" class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3 lg:gap-8">
        <!-- Users -->
        <button class="rounded-lg border p-5 text-left transition hover:opacity-90 md:p-6" :class="cardClass(statusColor(data.users.total))" @click="navigateTo('users')">
          <div class="text-sm">Users — Total</div>
          <div class="mt-1 text-2xl font-semibold">{{ data.users.total }}</div>
        </button>
        <button class="rounded-lg border p-5 text-left transition hover:opacity-90 md:p-6" :class="cardClass(statusColor(data.users.active_last_7_days))" @click="navigateTo('users-active')">
          <div class="text-sm">Users — Active (7d)</div>
          <div class="mt-1 text-2xl font-semibold">{{ data.users.active_last_7_days }}</div>
        </button>

        <!-- PDPs -->
        <button class="rounded-lg border p-5 text-left transition hover:opacity-90 md:p-6" :class="cardClass(statusColor(data.pdps.total))" @click="navigateTo('pdps')">
          <div class="text-sm">PDPs — Total</div>
          <div class="mt-1 text-2xl font-semibold">{{ data.pdps.total }}</div>
        </button>
        <button class="rounded-lg border p-5 text-left transition hover:opacity-90 md:p-6" :class="cardClass(statusColor(data.pdps.active))" @click="navigateTo('pdps-active')">
          <div class="text-sm">PDPs — Active</div>
          <div class="mt-1 text-2xl font-semibold">{{ data.pdps.active }}</div>
        </button>

        <!-- Entries -->
        <button class="rounded-lg border p-5 text-left transition hover:opacity-90 md:p-6" :title="tooltipFor('entries-pending')" :class="cardClass(statusColor(data.entries.pending, false))" @click="navigateTo('entries-pending')">
          <div class="text-sm">Entries — Pending</div>
          <div class="mt-1 text-2xl font-semibold">{{ data.entries.pending }}</div>
        </button>
        <button class="rounded-lg border p-5 text-left transition hover:opacity-90 md:p-6" :title="tooltipFor('entries-pending-7d')" :class="cardClass(statusColor(data.entries.pending_over_7_days, false))" @click="navigateTo('entries-pending-7d')">
          <div class="text-sm">Entries — Pending > 7d</div>
          <div class="mt-1 text-2xl font-semibold">{{ data.entries.pending_over_7_days }}</div>
        </button>

        <!-- Curators -->
        <button class="rounded-lg border p-5 text-left transition hover:opacity-90 md:p-6" :class="cardClass(statusColor(data.curators.total))" @click="navigateTo('curators')">
          <div class="text-sm">Curators — Total</div>
          <div class="mt-1 text-2xl font-semibold">{{ data.curators.total }}</div>
        </button>
        <button class="rounded-lg border p-5 text-left transition hover:opacity-90 md:p-6" :title="tooltipFor('curators-overloaded')" :class="cardClass(statusColor(data.curators.overloaded, false))" @click="navigateTo('curators-overloaded')">
          <div class="text-sm">Curators — Overloaded</div>
          <div class="mt-1 text-2xl font-semibold">{{ data.curators.overloaded }}</div>
        </button>
      </div>
    </div>
  </AdminLayout>
</template>
