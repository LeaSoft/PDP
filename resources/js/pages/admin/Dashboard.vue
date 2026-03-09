<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { fetchJson } from '@/lib/csrf';
import { Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

type Metrics = {
    users: { total: number; active_last_7_days: number };
    pdps: { total: number; active: number };
    entries: { pending: number; pending_over_7_days: number };
    curators: { total: number; overloaded: number };
};

const loading = ref(false);
const error = ref<string | null>(null);
const data = ref<Metrics | null>(null);
const lastUpdatedAt = ref<string | null>(null);
let intervalId: number | null = null;

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const res = await fetchJson('/admin/dashboard');
        data.value = res as Metrics;
        lastUpdatedAt.value = new Date().toLocaleTimeString();
    } catch (e: any) {
        error.value = e?.message || 'Failed to load metrics';
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    load();
    // Auto-refresh every ~90 seconds
    intervalId = window.setInterval(load, 90_000);
});

onBeforeUnmount(() => {
    if (intervalId) window.clearInterval(intervalId);
});

type MetricRow = {
    label: string;
    value: number;
    href: string;
    tone: 'neutral' | 'good' | 'warn' | 'bad';
};

const rows = computed<MetricRow[]>(() => {
    if (!data.value) return [];
    return [
        {
            label: 'Users — Total',
            value: data.value.users.total,
            href: '/admin/users',
            tone: data.value.users.total > 0 ? 'good' : 'neutral',
        },
        {
            label: 'Users — Active (7d)',
            value: data.value.users.active_last_7_days,
            href: '/admin/users?active_last_days=7',
            tone: data.value.users.active_last_7_days > 0 ? 'good' : 'warn',
        },
        {
            label: 'PDPs — Total',
            value: data.value.pdps.total,
            href: '/admin/pdps',
            tone: data.value.pdps.total > 0 ? 'good' : 'neutral',
        },
        {
            label: 'PDPs — Active',
            value: data.value.pdps.active,
            href: '/admin/pdps?status=active',
            tone: data.value.pdps.active > 0 ? 'good' : 'warn',
        },
        {
            label: 'Entries — Pending',
            value: data.value.entries.pending,
            href: '/admin/entries?status=pending',
            tone:
                data.value.entries.pending === 0
                    ? 'good'
                    : data.value.entries.pending <= 10
                      ? 'warn'
                      : 'bad',
        },
        {
            label: 'Entries — Pending > 7d',
            value: data.value.entries.pending_over_7_days,
            href: '/admin/entries?status=pending&older_than=7',
            tone:
                data.value.entries.pending_over_7_days === 0
                    ? 'good'
                    : data.value.entries.pending_over_7_days <= 10
                      ? 'warn'
                      : 'bad',
        },
        {
            label: 'Curators — Total',
            value: data.value.curators.total,
            href: '/admin/curators',
            tone: data.value.curators.total > 0 ? 'good' : 'neutral',
        },
        {
            label: 'Curators — Overloaded',
            value: data.value.curators.overloaded,
            href: '/admin/curators?overloaded=1',
            tone: data.value.curators.overloaded === 0 ? 'good' : 'bad',
        },
    ];
});

function rowToneClass(tone: MetricRow['tone']) {
    if (tone === 'good') return 'bg-emerald-50/60';
    if (tone === 'warn') return 'bg-amber-50/70';
    if (tone === 'bad') return 'bg-rose-50/70';
    return '';
}
</script>

<template>
    <AdminLayout>
        <div class="mx-auto max-w-7xl px-4 pt-6 md:px-6 md:pt-8">
            <div class="mb-8 flex items-center justify-between">
                <h1 class="text-xl font-semibold">Admin — Global Overview</h1>
                <button
                    class="rounded-md border px-3 py-1 text-sm"
                    @click="load"
                    :disabled="loading"
                >
                    Refresh
                </button>
            </div>

            <div
                v-if="lastUpdatedAt"
                class="mb-5 text-xs text-muted-foreground"
            >
                Last updated at {{ lastUpdatedAt }}
            </div>

            <div
                v-if="error"
                class="mb-5 rounded-md border border-rose-200 bg-rose-50 p-3 text-rose-700"
            >
                {{ error }}
            </div>

            <div v-if="loading" class="text-sm text-muted-foreground">
                Loading…
            </div>

            <div v-else-if="data" class="overflow-hidden rounded-md border">
                <table class="w-full table-auto text-sm">
                    <thead class="bg-muted/40">
                        <tr>
                            <th
                                class="px-3 py-2 text-left text-xs font-medium text-muted-foreground"
                            >
                                Metric
                            </th>
                            <th
                                class="px-3 py-2 text-left text-xs font-medium text-muted-foreground"
                            >
                                Value
                            </th>
                            <th
                                class="px-3 py-2 text-left text-xs font-medium text-muted-foreground"
                            >
                                Open
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in rows"
                            :key="row.label"
                            :class="rowToneClass(row.tone)"
                            class="border-t"
                        >
                            <td class="px-3 py-2">{{ row.label }}</td>
                            <td class="px-3 py-2 font-semibold">
                                {{ row.value }}
                            </td>
                            <td class="px-3 py-2">
                                <Link
                                    :href="row.href"
                                    class="rounded border px-2 py-1 text-xs hover:bg-muted"
                                >
                                    Open tab
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
