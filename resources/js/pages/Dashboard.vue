<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { fetchJson } from '@/lib/csrf';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { CheckCircle2, RefreshCw } from 'lucide-vue-next';
import { onMounted, onUnmounted, ref, watch } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

// All requests go through shared CSRF/Sanctum helper

function formatKyivDateTime(input?: string | number | Date): string {
    if (!input) return '';
    const d = new Date(input);
    if (isNaN(d.getTime())) return '';
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Europe/Kyiv',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).formatToParts(d);
    const map: Record<string, string> = {};
    for (const p of parts) map[p.type] = p.value;
    return `${map.year}-${map.month}-${map.day} ${map.hour}:${map.minute}`;
}

// My PDPs snapshot state
interface OverviewItem {
    id: number;
    title: string;
    role: 'owner' | 'curator';
    status: string;
    eta?: string | null;
    totalCriteria: number;
    closed: number;
    remaining: number;
    updated_at: string;
    owner?: { id: number; name?: string; email?: string | null };
}
const overview = ref<OverviewItem[] | null>(null);
const overviewLoading = ref(false);
const overviewError = ref('');

async function loadOverview() {
    overviewLoading.value = true;
    overviewError.value = '';
    try {
        overview.value = await fetchJson('/dashboard/pdps/overview.json');
    } catch (e: any) {
        overviewError.value = e?.message || 'Failed to load overview';
        overview.value = [];
    } finally {
        overviewLoading.value = false;
    }
}

// Pending approvals state
interface PendingItem {
    id: number;
    pdp: { id: number; title: string };
    skill: { id: number; name: string };
    criterion: { index: number; text: string };
    note: string;
    created_at: string;
    owner?: { id: number; name?: string; email?: string };
}
const pending = ref<PendingItem[] | null>(null);
const loading = ref(false);
const error = ref('');

async function loadPending() {
    loading.value = true;
    error.value = '';
    try {
        pending.value = await fetchJson('/dashboard/pending-approvals.json');
    } catch (e: any) {
        error.value = e?.message || 'Failed to load pending approvals';
        pending.value = [];
    } finally {
        loading.value = false;
    }
}

// PDP selector + summary state (KPI + micro sparkline)
interface PdpOption {
    id: number;
    title: string;
}
const pdps = ref<PdpOption[]>([]);
const selectedPdpId = ref<number | null>(null);

interface PdpSummary {
    totalCriteria: number;
    approvedCount: number;
    pendingCount: number;
    wins: { date: string; count: number }[];
    skills: {
        id: number;
        skill: string;
        totalCriteria: number;
        approvedCount: number;
        pendingCount: number;
    }[];
}

const summary = ref<PdpSummary | null>(null);
const summaryLoading = ref(false);
const summaryError = ref('');

// Professional level (global, based on closed skills across all PDPs)
interface ProLevel {
    key: string;
    title: string;
    index: number;
    closed_skills: number;
    current_threshold: number;
    next_threshold: number | null;
    percent: number;
    remaining_to_next: number | null;
    at_max: boolean;
    levels?: { key: string; title: string; threshold: number }[];
}
const proLevel = ref<ProLevel | null>(null);
const proLevelLoading = ref(false);
const proLevelError = ref('');

async function loadProLevel() {
    proLevelLoading.value = true;
    proLevelError.value = '';
    try {
        proLevel.value = await fetchJson('/profile/pro-level.json');
    } catch (e: any) {
        proLevelError.value = e?.message || 'Failed to load level';
        proLevel.value = null;
    } finally {
        proLevelLoading.value = false;
    }
}

async function loadPdps() {
    try {
        const [own, shared] = await Promise.all([
            fetchJson('/pdps.json'),
            fetchJson('/pdps.shared.json').catch(() => []),
        ]);
        const map = new Map<number, PdpOption>();
        for (const it of own || [])
            map.set(it.id, { id: it.id, title: it.title });
        for (const it of shared || [])
            if (!map.has(it.id)) map.set(it.id, { id: it.id, title: it.title });
        pdps.value = Array.from(map.values());
        if (selectedPdpId.value == null && pdps.value.length) {
            selectedPdpId.value = pdps.value[0].id;
        }
    } catch {
        // ignore
    }
}

async function loadSummary() {
    if (!selectedPdpId.value) {
        summary.value = {
            totalCriteria: 0,
            approvedCount: 0,
            pendingCount: 0,
            wins: [],
            skills: [],
        };
        return;
    }
    summaryLoading.value = true;
    summaryError.value = '';
    try {
        summary.value = await fetchJson(
            `/dashboard/pdps/${selectedPdpId.value}/summary.json`,
        );
    } catch (e: any) {
        summaryError.value = e?.message || 'Failed to load summary';
        summary.value = {
            totalCriteria: 0,
            approvedCount: 0,
            pendingCount: 0,
            wins: [],
            skills: [],
        };
    } finally {
        summaryLoading.value = false;
    }
}

watch(selectedPdpId, () => {
    loadSummary();
});

// Auto-refresh professional level widget when user selects level in modal
function onProLevelUpdated() {
    loadProLevel();
}

onMounted(() => {
    window.addEventListener('pro-level:updated', onProLevelUpdated);
    loadOverview();
    loadPending();
    loadProLevel();
    loadPdps().then(() => {
        loadSummary();
    });
});

onUnmounted(() => {
    window.removeEventListener('pro-level:updated', onProLevelUpdated);
});
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6"
        >
            <div class="grid auto-rows-min gap-6 md:grid-cols-3">
                <!-- Left-top: My PDPs snapshot -->
                <div
                    class="relative overflow-hidden rounded-xl border border-sidebar-border/70 p-5 shadow-sm dark:border-sidebar-border"
                >
                    <div class="mb-4 flex items-center justify-between gap-2">
                        <h3 class="text-base font-semibold">My PDPs snapshot</h3>
                        <button
                            class="text-muted-foreground hover:text-foreground"
                            @click="loadOverview"
                            title="Refresh"
                        >
                            <RefreshCw class="size-4" />
                        </button>
                    </div>
                    <div
                        v-if="overviewLoading"
                        class="text-xs text-muted-foreground"
                    >
                        Loading…
                    </div>
                    <div
                        v-else-if="overviewError"
                        class="text-xs text-destructive"
                    >
                        {{ overviewError }}
                    </div>
                    <div v-else>
                        <div
                            v-if="(overview || []).length"
                            class="max-h-64 divide-y overflow-auto"
                        >
                            <div
                                v-for="it in overview || []"
                                :key="it.id"
                                class="py-3"
                            >
                                <div
                                    class="flex items-start justify-between gap-2"
                                >
                                    <div class="min-w-0 flex-1">
                                        <div
                                            class="flex items-center gap-2"
                                        >
                                            <span class="truncate text-sm font-semibold">{{
                                                it.title
                                            }}</span>
                                            <span
                                                class="inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-[10px] font-medium"
                                                :class="
                                                    it.role === 'owner'
                                                        ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
                                                        : 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300'
                                                "
                                                >{{
                                                    it.role === 'owner'
                                                        ? 'Owner'
                                                        : 'Mentor'
                                                }}</span
                                            >
                                        </div>
                                        <div
                                            class="mt-0.5 text-[11px] text-muted-foreground"
                                        >
                                            {{ it.status
                                            }}<span v-if="it.eta">
                                                · ETA: {{ it.eta }}</span
                                            >
                                        </div>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <div
                                            class="text-xs font-medium whitespace-nowrap tabular-nums"
                                        >
                                            {{
                                                Math.min(
                                                    it.closed,
                                                    it.totalCriteria,
                                                )
                                            }}
                                            / {{ it.totalCriteria }}
                                        </div>
                                    </div>
                                </div>
                                <div
                                    class="mt-2 h-2 w-full overflow-hidden rounded-full bg-muted"
                                >
                                    <div
                                        class="h-full rounded-full bg-primary transition-all"
                                        :style="{
                                            width:
                                                (it.totalCriteria
                                                    ? Math.min(
                                                          100,
                                                          Math.round(
                                                              (100 *
                                                                  it.closed) /
                                                                  it.totalCriteria,
                                                          ),
                                                      )
                                                    : 0) + '%',
                                        }"
                                    ></div>
                                </div>
                                <div class="mt-2 flex items-center gap-3">
                                    <a
                                        :href="`/pdps?tab=manage&pdp=${it.id}`"
                                        class="text-xs text-primary underline-offset-2 hover:underline"
                                        >Open</a
                                    >
                                    <a
                                        :href="`/pdps?tab=annex&pdp=${it.id}`"
                                        class="text-xs text-primary underline-offset-2 hover:underline"
                                        >Annex</a
                                    >
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-xs text-muted-foreground">
                            No available PDPs.
                        </div>
                    </div>
                </div>

                <!-- Middle-top: Professional level -->
                <div
                    class="relative overflow-hidden rounded-xl border border-sidebar-border/70 p-5 shadow-sm dark:border-sidebar-border"
                >
                    <div class="mb-4 flex items-center justify-between gap-2">
                        <h3 class="text-base font-semibold">
                            Professional level
                        </h3>
                        <button
                            class="text-muted-foreground hover:text-foreground"
                            @click="loadProLevel"
                            title="Refresh"
                        >
                            <RefreshCw class="size-4" />
                        </button>
                    </div>
                    <div
                        v-if="proLevelLoading"
                        class="text-xs text-muted-foreground"
                    >
                        Loading…
                    </div>
                    <div
                        v-else-if="proLevelError"
                        class="text-xs text-destructive"
                    >
                        {{ proLevelError }}
                    </div>
                    <div v-else-if="proLevel" class="space-y-3">
                        <div class="flex items-end justify-between gap-2">
                            <div>
                                <span
                                    class="inline-flex items-center rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300"
                                    >{{ proLevel.title }}</span
                                >
                                <div class="mt-1 text-2xl font-bold leading-none">
                                    {{ proLevel.closed_skills }}
                                    <span class="text-sm font-normal text-muted-foreground">skills closed</span>
                                </div>
                            </div>
                            <div class="text-sm font-medium text-muted-foreground">
                                {{ proLevel.percent }}%
                            </div>
                        </div>
                        <div
                            class="h-3 w-full overflow-hidden rounded-full bg-muted"
                        >
                            <div
                                class="h-full rounded-full bg-green-500 transition-all"
                                :style="{
                                    width:
                                        (proLevel ? proLevel.percent : 0) + '%',
                                }"
                            ></div>
                        </div>
                        <div
                            v-if="!proLevel.at_max && proLevel.remaining_to_next != null"
                            class="text-sm font-medium text-muted-foreground"
                        >
                            {{ proLevel.remaining_to_next }} more to next level
                        </div>
                        <div v-else-if="proLevel.at_max" class="text-sm font-medium text-green-600 dark:text-green-400">
                            Max level reached
                        </div>
                    </div>
                    <div v-else class="text-xs text-muted-foreground">
                        No level data.
                    </div>
                </div>

                <!-- Right-top: Pending approvals list -->
                <div
                    class="relative overflow-hidden rounded-xl border border-sidebar-border/70 p-5 shadow-sm dark:border-sidebar-border"
                >
                    <div class="mb-4 flex items-center justify-between gap-2">
                        <h3 class="text-base font-semibold">Pending approvals</h3>
                        <button
                            class="text-muted-foreground hover:text-foreground"
                            @click="loadPending"
                            title="Refresh"
                        >
                            <RefreshCw class="size-4" />
                        </button>
                    </div>
                    <div v-if="loading" class="text-xs text-muted-foreground">
                        Loading…
                    </div>
                    <div v-else-if="error" class="text-xs text-destructive">
                        {{ error }}
                    </div>
                    <div v-else>
                        <div
                            v-if="(pending || []).length"
                            class="max-h-64 divide-y overflow-auto"
                        >
                            <div
                                v-for="it in pending || []"
                                :key="it.id"
                                class="py-3"
                            >
                                <div class="border-l-2 border-l-amber-400 pl-3">
                                    <div
                                        class="flex items-start justify-between gap-2"
                                    >
                                        <div class="min-w-0 flex-1">
                                            <div class="truncate text-sm font-semibold">
                                                {{ it.pdp.title }}
                                            </div>
                                            <div
                                                class="truncate text-xs text-muted-foreground"
                                            >
                                                {{ it.skill.name }} · {{ it.criterion.text }}
                                            </div>
                                        </div>
                                        <div
                                            class="text-[11px] shrink-0 whitespace-nowrap text-muted-foreground"
                                        >
                                            {{ formatKyivDateTime(it.created_at) }}
                                        </div>
                                    </div>
                                    <div
                                        v-if="it.owner"
                                        class="mt-0.5 truncate text-[11px] text-muted-foreground"
                                    >
                                        Owner: {{ it.owner.name || it.owner.email }}
                                    </div>
                                    <div
                                        v-if="it.note"
                                        class="mt-1 line-clamp-2 text-xs text-muted-foreground"
                                    >
                                        {{ it.note }}
                                    </div>
                                    <div class="mt-2">
                                        <a
                                            :href="`/pdps?tab=manage&pdp=${it.pdp.id}&skill=${it.skill.id}&criterion=${it.criterion.index}`"
                                            class="text-xs text-primary underline-offset-2 hover:underline"
                                            >Open PDP</a
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="flex flex-col items-center gap-2 py-6 text-muted-foreground">
                            <CheckCircle2 class="size-8 opacity-40" />
                            <span class="text-sm">All caught up!</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom: PDP Progress — KPI tiles + per-skill breakdown -->
            <div
                class="relative flex-1 rounded-xl border border-sidebar-border/70 p-5 shadow-sm dark:border-sidebar-border"
            >
                <div class="mb-5 flex items-center justify-between gap-3">
                    <h3 class="text-base font-semibold">PDP Progress</h3>
                    <div class="flex items-center gap-2">
                        <label class="text-xs text-muted-foreground">PDP:</label>
                        <select
                            v-model.number="selectedPdpId"
                            class="rounded-md border bg-background px-3 py-1.5 text-sm"
                        >
                            <option v-if="!pdps.length" disabled value="">
                                Loading…
                            </option>
                            <option v-for="p in pdps" :key="p.id" :value="p.id">
                                {{ p.title }}
                            </option>
                        </select>
                    </div>
                </div>
                <div
                    v-if="summaryLoading"
                    class="text-xs text-muted-foreground"
                >
                    Loading…
                </div>
                <div v-else-if="summaryError" class="text-xs text-destructive">
                    {{ summaryError }}
                </div>
                <div v-else>
                    <div v-if="summary" class="space-y-5">
                        <!-- KPI tiles -->
                        <div class="grid grid-cols-2 gap-4 md:grid-cols-3">
                            <div class="rounded-xl border border-t-2 border-t-slate-400 p-4">
                                <div class="text-[11px] text-muted-foreground">
                                    Total criteria
                                </div>
                                <div class="mt-1 text-3xl font-bold tabular-nums">
                                    {{ summary.totalCriteria }}
                                </div>
                            </div>
                            <div class="rounded-xl border border-t-2 border-t-green-500 p-4">
                                <div class="text-[11px] text-muted-foreground">
                                    Closed
                                </div>
                                <div class="mt-1 text-3xl font-bold tabular-nums text-green-600 dark:text-green-400">
                                    {{ summary.approvedCount }}
                                </div>
                            </div>
                            <div class="rounded-xl border border-t-2 border-t-amber-500 p-4">
                                <div class="text-[11px] text-muted-foreground">
                                    Remaining
                                </div>
                                <div class="mt-1 text-3xl font-bold tabular-nums text-amber-600 dark:text-amber-400">
                                    {{ summary.pendingCount }}
                                </div>
                            </div>
                        </div>

                        <!-- Skills breakdown -->
                        <div>
                            <div class="mb-3 text-xs font-medium text-muted-foreground uppercase tracking-wide">
                                Per-skill status
                            </div>
                            <div
                                v-if="(summary.skills || []).length"
                                class="grid gap-3 md:grid-cols-2 lg:grid-cols-3"
                            >
                                <div
                                    v-for="s in summary.skills"
                                    :key="s.id"
                                    class="relative overflow-hidden rounded-lg border p-3 pl-4"
                                >
                                    <div
                                        class="absolute top-0 left-0 h-full w-1"
                                        :class="
                                            s.approvedCount === 0
                                                ? 'bg-red-400'
                                                : s.pendingCount === 0
                                                  ? 'bg-green-500'
                                                  : 'bg-blue-400'
                                        "
                                    ></div>
                                    <div class="truncate text-sm font-semibold">
                                        {{ s.skill }}
                                    </div>
                                    <div class="mt-1 flex items-center justify-between text-xs text-muted-foreground">
                                        <span>{{ s.approvedCount }} closed</span>
                                        <span>{{ s.totalCriteria }} total</span>
                                    </div>
                                    <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                        <div
                                            class="h-full rounded-full bg-green-500 transition-all"
                                            :style="{
                                                width: (s.totalCriteria ? Math.round((100 * s.approvedCount) / s.totalCriteria) : 0) + '%',
                                            }"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-xs text-muted-foreground">
                                No skills in the selected PDP.
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-xs text-muted-foreground">
                        No data.
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
