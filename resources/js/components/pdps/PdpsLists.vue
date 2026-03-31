<script setup lang="ts">
import { ChevronDown, Plus } from 'lucide-vue-next';
import { computed } from 'vue';

export type Pdp = {
    id: number;
    title: string;
    description?: string;
    priority: 'Low' | 'Medium' | 'High';
    eta?: string;
    status: 'Planned' | 'In Progress' | 'Done' | 'Blocked';
    skills_count?: number;
    user?: { id: number; name?: string; email: string };
};

const props = defineProps<{
    pdps: Pdp[];
    sharedPdps: Pdp[];
    selectedPdpId: number | null;
    collapseOwned: boolean;
    collapseShared: boolean;
    activeTab: 'Manage' | 'Annex';
    unseenByPdp?: Record<number, { approved: number; commented: number }>;
}>();

const emit = defineEmits<{
    (e: 'update:collapseOwned', val: boolean): void;
    (e: 'update:collapseShared', val: boolean): void;
    (e: 'selectPdp', id: number): void;
    (e: 'selectPdpFromShared', id: number): void;
    (e: 'openCreatePdp'): void;
    (e: 'openEditPdp', pdp: Pdp): void;
}>();

const hasPdps = computed(() => props.pdps.length > 0);
const hasSharedPdps = computed(() => props.sharedPdps.length > 0);

function statusColor(status: string) {
    if (status === 'Done') return 'bg-green-500';
    if (status === 'In Progress') return 'bg-blue-500';
    if (status === 'Blocked') return 'bg-red-500';
    return 'bg-slate-400';
}

function statusBadge(status: string) {
    if (status === 'Done') return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300';
    if (status === 'In Progress') return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300';
    if (status === 'Blocked') return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300';
    return 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400';
}
</script>

<template>
    <div
        class="rounded-xl border border-sidebar-border/70 p-5 shadow-sm dark:border-sidebar-border"
    >
        <!-- My PDPs section -->
        <div class="mb-4 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <h2 class="text-base font-semibold">My PDPs</h2>
                <span
                    class="inline-flex min-w-[20px] items-center justify-center rounded-full bg-muted px-2 py-0.5 text-[11px] font-medium leading-none text-muted-foreground"
                    >{{ pdps.length }}</span
                >
                <button
                    class="rounded p-0.5 text-muted-foreground transition hover:bg-muted"
                    @click="emit('update:collapseOwned', !props.collapseOwned)"
                    :title="collapseOwned ? 'Expand' : 'Collapse'"
                >
                    <ChevronDown
                        class="size-4 transition-transform"
                        :class="collapseOwned ? '-rotate-90' : 'rotate-0'"
                    />
                </button>
            </div>
            <button
                v-if="activeTab !== 'Annex'"
                class="inline-flex items-center gap-1 rounded-md bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground shadow-sm hover:opacity-90"
                @click="emit('openCreatePdp')"
            >
                <Plus class="size-3.5" />
                Add PDP
            </button>
        </div>

        <div v-if="!collapseOwned">
            <div v-if="hasPdps" class="space-y-2">
                <button
                    v-for="p in pdps"
                    :key="p.id"
                    class="group relative w-full overflow-hidden rounded-lg border pl-4 pr-3 py-3 text-left text-sm transition hover:bg-muted/50"
                    :class="
                        selectedPdpId === p.id
                            ? 'border-primary/60 bg-primary/5'
                            : 'border-border'
                    "
                    @click="emit('selectPdp', p.id)"
                >
                    <!-- Status accent bar -->
                    <div
                        class="absolute top-0 left-0 h-full w-1 rounded-l-lg"
                        :class="statusColor(p.status)"
                    ></div>

                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="truncate font-semibold">{{ p.title }}</span>
                                <!-- Unseen badges -->
                                <template v-if="unseenByPdp && unseenByPdp[p.id]">
                                    <span
                                        v-if="unseenByPdp[p.id].approved"
                                        class="inline-flex items-center gap-1 rounded-full bg-green-100 px-1.5 py-0.5 text-[10px] text-green-700 dark:bg-green-900/30 dark:text-green-300"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3">
                                            <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.414l-7.5 7.5a1 1 0 01-1.414 0l-3-3a1 1 0 111.414-1.414l2.293 2.293 6.793-6.793a1 1 0 011.414 0z" clip-rule="evenodd" />
                                        </svg>
                                        {{ unseenByPdp[p.id].approved }}
                                    </span>
                                    <span
                                        v-if="unseenByPdp[p.id].commented"
                                        class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] text-amber-800 dark:bg-amber-900/30 dark:text-amber-200"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3">
                                            <path d="M18 10c0 3.866-3.582 7-8 7a9.225 9.225 0 01-2.9-.46L4 17l.46-3.1A7.827 7.827 0 013 10c0-3.866 3.582-7 8-7s7 3.134 7 7z" />
                                        </svg>
                                        {{ unseenByPdp[p.id].commented }}
                                    </span>
                                </template>
                            </div>
                            <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                <span
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium"
                                    :class="statusBadge(p.status)"
                                    >{{ p.status }}</span
                                >
                                <span class="text-[11px] text-muted-foreground">{{ p.priority }}</span>
                                <span v-if="p.eta" class="text-[11px] text-muted-foreground">· ETA: {{ p.eta }}</span>
                            </div>
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-2">
                            <span class="rounded-full bg-muted px-2 py-0.5 text-[11px] font-medium text-muted-foreground">
                                {{ p.skills_count ?? 0 }} skills
                            </span>
                            <button
                                class="rounded-md border px-2.5 py-1 text-[11px] font-medium hover:bg-muted"
                                @click.stop="emit('openEditPdp', p)"
                            >
                                Edit
                            </button>
                        </div>
                    </div>
                </button>
            </div>
            <p v-else class="text-sm text-muted-foreground">
                The list is empty. Add the first PDP.
            </p>
        </div>
        <div v-else class="my-2 h-px bg-border"></div>

        <!-- My Mentees' PDPs section -->
        <div class="mt-6">
            <div class="mb-4 flex items-center gap-2">
                <h2 class="text-base font-semibold">My Mentees' PDPs</h2>
                <span
                    class="inline-flex min-w-[20px] items-center justify-center rounded-full bg-muted px-2 py-0.5 text-[11px] font-medium leading-none text-muted-foreground"
                    >{{ sharedPdps.length }}</span
                >
                <button
                    class="rounded p-0.5 text-muted-foreground transition hover:bg-muted"
                    @click="emit('update:collapseShared', !props.collapseShared)"
                    :title="collapseShared ? 'Expand' : 'Collapse'"
                >
                    <ChevronDown
                        class="size-4 transition-transform"
                        :class="collapseShared ? '-rotate-90' : 'rotate-0'"
                    />
                </button>
            </div>
            <div v-if="!collapseShared">
                <div v-if="hasSharedPdps" class="space-y-2">
                    <button
                        v-for="p in sharedPdps"
                        :key="'s-' + p.id"
                        class="group relative w-full overflow-hidden rounded-lg border pl-4 pr-3 py-3 text-left text-sm transition hover:bg-muted/50"
                        :class="
                            selectedPdpId === p.id
                                ? 'border-primary/60 bg-primary/5'
                                : 'border-border'
                        "
                        @click="emit('selectPdpFromShared', p.id)"
                    >
                        <div
                            class="absolute top-0 left-0 h-full w-1 rounded-l-lg"
                            :class="statusColor(p.status)"
                        ></div>

                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <span class="truncate font-semibold">{{ p.title }}</span>
                                <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium"
                                        :class="statusBadge(p.status)"
                                        >{{ p.status }}</span
                                    >
                                    <span class="text-[11px] text-muted-foreground">{{ p.priority }}</span>
                                    <span v-if="p.eta" class="text-[11px] text-muted-foreground">· ETA: {{ p.eta }}</span>
                                </div>
                                <div
                                    v-if="p.user"
                                    class="mt-1 text-[11px] text-muted-foreground"
                                >
                                    Owner: {{ p.user.name || p.user.email
                                    }}<span v-if="p.user.name"> ({{ p.user.email }})</span>
                                </div>
                            </div>
                            <span class="shrink-0 rounded-full bg-muted px-2 py-0.5 text-[11px] font-medium text-muted-foreground">
                                {{ p.skills_count ?? 0 }} skills
                            </span>
                        </div>
                    </button>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    No shared PDPs yet.
                </p>
            </div>
            <div v-else class="my-2 h-px bg-border"></div>
        </div>
    </div>
</template>
