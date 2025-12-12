<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { fetchJson } from '@/lib/csrf';
import { notifyError } from '@/composables/useNotify';

interface Mentee {
    user_id: number;
    name: string;
    email: string;
    pending_count: number;
}

interface PendingApproval {
    id: number;
    pdp: { id: number; title: string };
    skill: { id: number; name: string };
    criterion: { index: number; text: string };
    note: string;
    created_at: string;
    owner?: { id: number; name?: string; email?: string };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'My Mentees',
        href: '/curator/mentees',
    },
];

const mentees = ref<Mentee[]>([]);
const selectedUserId = ref<number | null>(null);
const pendingApprovals = ref<PendingApproval[]>([]);
const loading = ref(false);
const loadingApprovals = ref(false);
const error = ref('');

const selectedMentee = computed(() => {
    if (!selectedUserId.value) return null;
    return mentees.value.find((m) => m.user_id === selectedUserId.value);
});

// Group approvals by PDP -> Skill -> Criteria
const groupedApprovals = computed(() => {
    const grouped: Record<
        number,
        {
            pdp: { id: number; title: string };
            skills: Record<
                number,
                {
                    skill: { id: number; name: string };
                    approvals: PendingApproval[];
                }
            >;
        }
    > = {};

    for (const approval of pendingApprovals.value) {
        if (!grouped[approval.pdp.id]) {
            grouped[approval.pdp.id] = {
                pdp: approval.pdp,
                skills: {},
            };
        }

        if (!grouped[approval.pdp.id].skills[approval.skill.id]) {
            grouped[approval.pdp.id].skills[approval.skill.id] = {
                skill: approval.skill,
                approvals: [],
            };
        }

        grouped[approval.pdp.id].skills[approval.skill.id].approvals.push(
            approval,
        );
    }

    return grouped;
});

async function loadMentees() {
    loading.value = true;
    error.value = '';
    try {
        mentees.value = await fetchJson('/curator/mentees.json');
        if (mentees.value.length > 0 && !selectedUserId.value) {
            selectedUserId.value = mentees.value[0].user_id;
            await loadPendingApprovals(selectedUserId.value);
        }
    } catch (e: any) {
        error.value = e?.message || 'Failed to load mentees';
        notifyError(error.value);
    } finally {
        loading.value = false;
    }
}

async function loadPendingApprovals(userId: number) {
    loadingApprovals.value = true;
    try {
        pendingApprovals.value = await fetchJson(
            `/curator/mentees/${userId}/pending-approvals.json`,
        );
    } catch (e: any) {
        notifyError(e?.message || 'Failed to load pending approvals');
        pendingApprovals.value = [];
    } finally {
        loadingApprovals.value = false;
    }
}

function selectMentee(userId: number) {
    selectedUserId.value = userId;
    loadPendingApprovals(userId);
}

function formatDate(dateStr: string): string {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return '';
    return new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Europe/Kyiv',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).format(d);
}

onMounted(() => {
    loadMentees();
});
</script>

<template>
    <Head title="My Mentees" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold">My Mentees</h1>
            </div>

            <div class="flex flex-1 gap-4 overflow-hidden">
                <!-- Left Column: Users List -->
                <div
                    class="w-80 flex-shrink-0 overflow-y-auto rounded-xl border border-sidebar-border/70 p-3 dark:border-sidebar-border"
                >
                    <h2 class="mb-3 text-sm font-semibold">Assigned Users</h2>

                    <div v-if="loading" class="text-xs text-muted-foreground">
                        Loading...
                    </div>
                    <div
                        v-else-if="error"
                        class="text-xs text-destructive"
                    >
                        {{ error }}
                    </div>
                    <div v-else-if="mentees.length === 0" class="text-xs text-muted-foreground">
                        No assigned users found
                    </div>
                    <div v-else class="space-y-2">
                        <button
                            v-for="mentee in mentees"
                            :key="mentee.user_id"
                            @click="selectMentee(mentee.user_id)"
                            class="w-full rounded-lg border p-3 text-left transition-colors hover:bg-muted"
                            :class="{
                                'border-primary bg-primary/5':
                                    selectedUserId === mentee.user_id,
                            }"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex-1 truncate">
                                    <div class="font-medium truncate">
                                        {{ mentee.name }}
                                    </div>
                                    <div
                                        class="text-xs text-muted-foreground truncate"
                                    >
                                        {{ mentee.email }}
                                    </div>
                                </div>
                                <div
                                    v-if="mentee.pending_count > 0"
                                    class="flex-shrink-0 rounded-full bg-orange-500 px-2 py-0.5 text-xs font-medium text-white"
                                >
                                    {{ mentee.pending_count }}
                                </div>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Right Column: Selected User's PDP -->
                <div
                    class="flex-1 overflow-y-auto rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                >
                    <div v-if="!selectedMentee" class="text-sm text-muted-foreground">
                        Select a user to view their pending approvals
                    </div>
                    <div v-else>
                        <!-- Header -->
                        <div class="mb-4">
                            <h2 class="text-lg font-semibold">
                                {{ selectedMentee.name }}
                            </h2>
                            <div class="text-sm text-muted-foreground">
                                {{ selectedMentee.email }}
                            </div>
                            <div
                                v-if="selectedMentee.pending_count > 0"
                                class="mt-2 text-sm"
                            >
                                <span class="font-medium"
                                    >Pending approvals:</span
                                >
                                {{ selectedMentee.pending_count }}
                            </div>
                        </div>

                        <!-- Loading State -->
                        <div
                            v-if="loadingApprovals"
                            class="text-sm text-muted-foreground"
                        >
                            Loading approvals...
                        </div>

                        <!-- Empty State -->
                        <div
                            v-else-if="pendingApprovals.length === 0"
                            class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
                        >
                            No pending approvals
                        </div>

                        <!-- Grouped Approvals -->
                        <div v-else class="space-y-6">
                            <div
                                v-for="(pdpGroup, pdpId) in groupedApprovals"
                                :key="pdpId"
                                class="rounded-lg border p-4"
                            >
                                <h3 class="mb-3 text-base font-semibold">
                                    {{ pdpGroup.pdp.title }}
                                </h3>

                                <div class="space-y-4">
                                    <div
                                        v-for="(
                                            skillGroup, skillId
                                        ) in pdpGroup.skills"
                                        :key="skillId"
                                        class="border-l-2 border-primary/30 pl-4"
                                    >
                                        <h4
                                            class="mb-2 text-sm font-medium text-primary"
                                        >
                                            {{ skillGroup.skill.name }}
                                        </h4>

                                        <div class="space-y-3">
                                            <div
                                                v-for="approval in skillGroup.approvals"
                                                :key="approval.id"
                                                class="rounded-md border bg-muted/30 p-3"
                                            >
                                                <div
                                                    class="mb-1 text-xs font-medium"
                                                >
                                                    Criterion:
                                                </div>
                                                <div class="mb-2 text-sm">
                                                    {{
                                                        approval.criterion.text
                                                    }}
                                                </div>

                                                <div
                                                    v-if="approval.note"
                                                    class="mb-2 text-xs text-muted-foreground"
                                                >
                                                    <span class="font-medium"
                                                        >Note:</span
                                                    >
                                                    {{ approval.note }}
                                                </div>

                                                <div
                                                    class="mb-3 text-[11px] text-muted-foreground"
                                                >
                                                    Submitted:
                                                    {{
                                                        formatDate(
                                                            approval.created_at,
                                                        )
                                                    }}
                                                </div>

                                                <div class="flex gap-2">
                                                    <a
                                                        :href="`/pdps?tab=manage&pdp=${approval.pdp.id}&skill=${approval.skill.id}&criterion=${approval.criterion.index}`"
                                                        class="rounded border px-3 py-1.5 text-xs hover:bg-background"
                                                    >
                                                        Open in PDP
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
