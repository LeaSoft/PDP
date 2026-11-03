<script setup lang="ts">
import { parseCriteriaItems } from '@/utils/criteria';
import { statusBadgeClass } from '@/utils/status';
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

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

export type PdpSkill = {
    id: number;
    pdp_id: number;
    skill: string;
    description?: string;
    criteria?: string;
    priority: 'Low' | 'Medium' | 'High';
    eta?: string;
    status: 'Planned' | 'In Progress' | 'Done' | 'Blocked';
    order_column?: number;
};

type Curator = { id: number; name?: string; email: string };

defineProps<{
    selectedPdp: Pdp | null;
    selectedPdpIsOwner: boolean;
    selectedPdpIsCurator: boolean;
    selectedPdpIsEditable: boolean;
    skills: PdpSkill[];
    curators: Curator[];
    curatorEmail: string;
    userOptions: Curator[];
    unseenByCriterion?: Record<
        string,
        { ids: number[]; types: Array<'approved' | 'rejected' | 'comment'> }
    >;
    unseenTotals?: { approved: number; rejected: number; commented: number };
}>();

const emit = defineEmits<{
    (e: 'openEditPdp', pdp: Pdp): void;
    (e: 'openCreateSkill'): void;
    (e: 'openEditSkill', skill: PdpSkill): void;
    (e: 'deleteSkill', id: number): void;
    (e: 'openProgressModal', s: PdpSkill, index: number): void;
    (e: 'toggleCriterionDone', s: PdpSkill, index: number, done: boolean): void;
    (e: 'assignCurator', u: Curator): void;
    (e: 'shareToUser', u: Curator): void;
    (e: 'removeCurator', c: Curator): void;
    (e: 'update:curatorEmail', val: string): void;
}>();

// "Assign mentor" / "Share PDP" each open a user search; picking a user runs the action
const picker = ref<'assign' | 'share' | null>(null);
const pickerRef = ref<HTMLElement | null>(null);
const searchInput = ref<HTMLInputElement | null>(null);

async function openPicker(kind: 'assign' | 'share') {
    if (picker.value === kind) {
        picker.value = null;
        return;
    }
    picker.value = kind;
    emit('update:curatorEmail', '');
    await nextTick();
    searchInput.value?.focus();
}

function pick(u: Curator) {
    if (picker.value === 'assign') emit('assignCurator', u);
    if (picker.value === 'share') emit('shareToUser', u);
    picker.value = null;
}

// Click outside closes the picker
function onDocClick(e: MouseEvent) {
    const el = pickerRef.value;
    const target = e.target as Node | null;
    if (el && target && !el.contains(target)) picker.value = null;
}
onMounted(() => document.addEventListener('click', onDocClick));
onBeforeUnmount(() => document.removeEventListener('click', onDocClick));

function onEmailInput(v: string) {
    emit('update:curatorEmail', v);
}
</script>

<template>
    <template v-if="selectedPdp">
        <h3 class="mb-1 text-lg font-semibold">{{ selectedPdp.title }}</h3>
        <!-- PDP totals for unseen approvals/rejections/comments -->
        <div
            v-if="
                unseenTotals &&
                (unseenTotals.approved ||
                    unseenTotals.rejected ||
                    unseenTotals.commented)
            "
            class="mb-2 flex gap-2 text-xs"
        >
            <span
                v-if="unseenTotals.approved"
                class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-green-700 dark:bg-green-900/30 dark:text-green-300"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    class="h-3 w-3"
                >
                    <path
                        fill-rule="evenodd"
                        d="M16.704 5.29a1 1 0 010 1.414l-7.5 7.5a1 1 0 01-1.414 0l-3-3a1 1 0 111.414-1.414l2.293 2.293 6.793-6.793a1 1 0 011.414 0z"
                        clip-rule="evenodd"
                    />
                </svg>
                {{ unseenTotals.approved }} Approved
            </span>
            <span
                v-if="unseenTotals.rejected"
                class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-red-700 dark:bg-red-900/30 dark:text-red-300"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    class="h-3 w-3"
                >
                    <path
                        fill-rule="evenodd"
                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm2.828-11.172a1 1 0 00-1.414-1.414L10 6.828 8.586 5.414a1 1 0 00-1.414 1.414L8.586 8 7.172 9.414a1 1 0 101.414 1.414L10 9.414l1.414 1.414a1 1 0 001.414-1.414L11.414 8l1.414-1.172z"
                        clip-rule="evenodd"
                    />
                </svg>
                {{ unseenTotals.rejected }} Rejected
            </span>
            <span
                v-if="unseenTotals.commented"
                class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-amber-800 dark:bg-amber-900/30 dark:text-amber-200"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    class="h-3 w-3"
                >
                    <path
                        d="M18 10c0 3.866-3.582 7-8 7a9.225 9.225 0 01-2.9-.46L4 17l.46-3.1A7.827 7.827 0 013 10c0-3.866 3.582-7 8-7s7 3.134 7 7z"
                    />
                </svg>
                {{ unseenTotals.commented }} Commented
            </span>
        </div>
        <p class="mb-3 text-sm text-muted-foreground">
            {{ selectedPdp.description }}
        </p>
        <p
            v-if="selectedPdpIsCurator && (selectedPdp as any)?.user"
            class="-mt-2 mb-3 text-[11px] text-muted-foreground"
        >
            Owner:
            {{
                (selectedPdp as any).user.name ||
                (selectedPdp as any).user.email
            }}<span v-if="(selectedPdp as any).user.name">
                ({{ (selectedPdp as any).user.email }})</span
            >
        </p>

        <div
            v-if="selectedPdpIsOwner"
            class="relative mb-4 min-h-[80px]"
            id="pdp-share"
        >
            <div ref="pickerRef" class="relative">
                <div class="flex flex-wrap gap-2">
                    <button
                        class="rounded-md border px-3 py-1.5 text-xs hover:bg-muted"
                        :aria-expanded="picker === 'assign'"
                        @click="openPicker('assign')"
                    >
                        Assign mentor
                    </button>
                    <button
                        class="inline-flex items-center gap-1 rounded-md border px-3 py-1.5 text-xs hover:bg-muted"
                        title="Share a copy of this PDP with another user"
                        :aria-expanded="picker === 'share'"
                        @click="openPicker('share')"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            class="h-3.5 w-3.5"
                        >
                            <path
                                d="M15 8a3 3 0 10-2.83-4H12a3 3 0 102.17 5.03l-6.1 3.05a3 3 0 100 1.84l6.1 3.05A3 3 0 1015 12a3 3 0 00-2.17.97l-6.1-3.05A3 3 0 0012 8h.17A2.99 2.99 0 0015 8z"
                            />
                        </svg>
                        <span>Share PDP</span>
                    </button>
                </div>
                <div
                    v-if="picker"
                    class="absolute z-50 mt-1 w-full rounded-md border bg-background p-2 shadow sm:w-[22rem]"
                >
                    <div class="mb-1 text-xs text-muted-foreground">
                        {{
                            picker === 'assign'
                                ? 'Choose a mentor for this PDP'
                                : 'Choose who gets a copy of this PDP'
                        }}
                    </div>
                    <input
                        ref="searchInput"
                        :value="curatorEmail"
                        @input="
                            onEmailInput(
                                ($event.target as HTMLInputElement).value,
                            )
                        "
                        @keydown.esc.prevent="picker = null"
                        @keydown.enter.prevent="
                            userOptions[0] && pick(userOptions[0])
                        "
                        type="text"
                        placeholder="Search by name or email"
                        aria-label="Search users"
                        class="w-full rounded border px-2 py-1 text-sm"
                        autocomplete="off"
                        autocapitalize="none"
                        autocorrect="off"
                        spellcheck="false"
                    />
                    <ul class="mt-1 max-h-56 overflow-auto">
                        <li v-for="u in userOptions" :key="u.id">
                            <button
                                type="button"
                                class="flex w-full items-center justify-between rounded px-2 py-1 text-left text-sm hover:bg-muted"
                                @click="pick(u)"
                            >
                                <span class="font-medium">{{
                                    u.name || u.email
                                }}</span>
                                <span
                                    v-if="u.name"
                                    class="ml-2 text-xs text-muted-foreground"
                                    >{{ u.email }}</span
                                >
                            </button>
                        </li>
                        <li
                            v-if="!userOptions.length"
                            class="px-2 py-1 text-xs text-muted-foreground"
                        >
                            No matches
                        </li>
                    </ul>
                </div>
            </div>
            <div v-if="curators.length" class="mt-2 flex flex-wrap gap-2">
                <span
                    v-for="c in curators"
                    :key="c.id"
                    class="inline-flex items-center gap-2 rounded-md border px-2 py-0.5 text-xs"
                >
                    <span class="font-medium">{{ c.name || c.email }}</span>
                    <span v-if="c.name" class="text-muted-foreground">{{
                        c.email
                    }}</span>
                    <button
                        class="ml-1 inline-flex h-5 w-5 items-center justify-center rounded-md border text-[11px] hover:bg-muted"
                        title="Remove mentor"
                        @click="emit('removeCurator', c)"
                    >
                        ×
                    </button>
                </span>
            </div>
        </div>

        <div v-if="skills.length">
            <!-- Desktop/tablet: table view -->
            <div class="hidden overflow-x-auto lg:block">
                <table class="min-w-full text-xs sm:text-sm">
                    <thead class="sticky top-0 z-10 bg-background">
                        <tr class="border-b text-left text-muted-foreground">
                            <th
                                class="sticky left-0 bg-background px-2 py-1 sm:px-3 sm:py-2"
                            >
                                Skill to achieve
                            </th>
                            <th class="px-2 py-1 sm:px-3 sm:py-2">
                                Description
                            </th>
                            <th class="px-2 py-1 sm:px-3 sm:py-2">
                                Win Criteria
                            </th>
                            <th class="px-2 py-1 sm:px-3 sm:py-2">Prio</th>
                            <th class="px-2 py-1 sm:px-3 sm:py-2">ETA</th>
                            <th class="px-2 py-1 sm:px-3 sm:py-2">Status</th>
                            <th class="px-2 py-1 sm:px-3 sm:py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="s in skills"
                            :key="s.id"
                            class="border-b align-top"
                        >
                            <td
                                class="sticky left-0 bg-background px-2 py-2 font-medium sm:px-3 sm:py-3"
                            >
                                {{ s.skill }}
                            </td>
                            <td
                                class="px-2 py-2 break-words whitespace-pre-line sm:px-3 sm:py-3"
                            >
                                {{ s.description }}
                            </td>
                            <td class="px-2 py-2 sm:px-3 sm:py-3">
                                <div
                                    v-if="parseCriteriaItems(s.criteria).length"
                                    class="flex flex-col gap-1.5"
                                >
                                    <div
                                        v-for="(c, i) in parseCriteriaItems(
                                            s.criteria,
                                        )"
                                        :key="i"
                                        class="flex w-full items-start gap-1"
                                    >
                                        <button
                                            type="button"
                                            class="inline-flex flex-1 cursor-pointer items-start justify-between rounded-md border border-border bg-muted px-2 py-1 text-left text-xs hover:bg-muted/70"
                                            :title="'Click to add/view progress'"
                                            @click="
                                                emit('openProgressModal', s, i)
                                            "
                                        >
                                            <span
                                                class="break-words whitespace-normal"
                                                >{{ c.text }}</span
                                            >
                                            <!-- attention dot for unseen curator actions for this criterion -->
                                            <span
                                                v-if="
                                                    unseenByCriterion &&
                                                    unseenByCriterion[
                                                        `${s.pdp_id}:${s.id}:${i}`
                                                    ]
                                                "
                                                class="ml-2 inline-flex items-center"
                                            >
                                                <span
                                                    class="relative inline-flex"
                                                >
                                                    <span
                                                        class="absolute inline-flex h-2 w-2 animate-ping rounded-full bg-amber-400 opacity-75"
                                                    ></span>
                                                    <span
                                                        class="relative inline-flex h-2 w-2 rounded-full bg-amber-500"
                                                    ></span>
                                                </span>
                                            </span>
                                            <span
                                                v-if="c.comment"
                                                class="ml-2 shrink-0 text-muted-foreground"
                                                >•</span
                                            >
                                        </button>
                                        <button
                                            v-if="selectedPdpIsEditable"
                                            type="button"
                                            class="inline-flex h-[22px] w-[22px] flex-none items-center justify-center rounded-md border text-[10px] hover:bg-muted"
                                            :title="
                                                c.done
                                                    ? 'Mark as not done'
                                                    : 'Mark as done'
                                            "
                                            @click.stop="
                                                emit(
                                                    'toggleCriterionDone',
                                                    s,
                                                    i,
                                                    !c.done,
                                                )
                                            "
                                        >
                                            <svg
                                                v-if="!c.done"
                                                xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 20 20"
                                                fill="currentColor"
                                                class="h-3 w-3"
                                            >
                                                <path
                                                    fill-rule="evenodd"
                                                    d="M3.5 10a6.5 6.5 0 1113 0 6.5 6.5 0 01-13 0zm9.204-2.79a1 1 0 10-1.414-1.414L8.5 8.586 7.21 7.296a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l3.494-3.5z"
                                                    clip-rule="evenodd"
                                                />
                                            </svg>
                                            <svg
                                                v-else
                                                xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 20 20"
                                                fill="currentColor"
                                                class="h-3 w-3 text-green-600"
                                            >
                                                <path
                                                    fill-rule="evenodd"
                                                    d="M16.704 5.29a1 1 0 010 1.414l-7.5 7.5a1 1 0 01-1.414 0l-3-3a1 1 0 111.414-1.414l2.293 2.293 6.793-6.793a1 1 0 011.414 0z"
                                                    clip-rule="evenodd"
                                                />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <span v-else class="text-muted-foreground"
                                    >—</span
                                >
                            </td>
                            <td class="px-2 py-2 sm:px-3 sm:py-3">
                                {{ s.priority }}
                            </td>
                            <td class="px-2 py-2 sm:px-3 sm:py-3">
                                {{ s.eta }}
                            </td>
                            <td class="px-2 py-2 sm:px-3 sm:py-3">
                                <span
                                    class="inline-flex items-center rounded-md px-2 py-0.5 text-[11px] whitespace-nowrap"
                                    :class="statusBadgeClass(s.status)"
                                >
                                    {{ s.status }}
                                </span>
                            </td>
                            <td class="px-2 py-2 text-right sm:px-3 sm:py-3">
                                <div
                                    class="flex justify-end gap-2"
                                    v-if="selectedPdpIsEditable"
                                >
                                    <button
                                        class="rounded border px-2 py-1 text-xs hover:bg-muted"
                                        @click="emit('openEditSkill', s)"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        class="rounded border px-2 py-1 text-xs text-destructive hover:bg-destructive hover:text-destructive-foreground"
                                        @click="emit('deleteSkill', s.id)"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile: cards view -->
            <div class="space-y-3 lg:hidden">
                <article
                    v-for="s in skills"
                    :key="s.id"
                    class="rounded-lg border bg-background p-3 shadow-sm"
                >
                    <header class="flex items-start justify-between gap-2">
                        <h4 class="text-base leading-snug font-semibold">
                            {{ s.skill }}
                        </h4>
                        <span
                            class="inline-flex items-center rounded-md px-2 py-0.5 text-[11px] whitespace-nowrap"
                            :class="statusBadgeClass(s.status)"
                            >{{ s.status }}</span
                        >
                    </header>
                    <p
                        v-if="s.description"
                        class="mt-1 text-sm whitespace-pre-line text-muted-foreground"
                    >
                        {{ s.description }}
                    </p>
                    <div
                        v-if="parseCriteriaItems(s.criteria).length"
                        class="mt-2 space-y-1.5"
                    >
                        <div class="text-xs text-muted-foreground">
                            Win criteria
                        </div>
                        <div
                            v-for="(c, i) in parseCriteriaItems(s.criteria)"
                            :key="i"
                            class="flex w-full items-start gap-1"
                        >
                            <button
                                type="button"
                                class="inline-flex flex-1 cursor-pointer items-start justify-between rounded-md border border-border bg-muted px-2 py-1 text-left text-xs hover:bg-muted/70"
                                :title="'Click to add/view progress'"
                                @click="emit('openProgressModal', s, i)"
                            >
                                <span class="break-words whitespace-normal">{{
                                    c.text
                                }}</span>
                                <span
                                    v-if="c.comment"
                                    class="ml-2 shrink-0 text-muted-foreground"
                                    >•</span
                                >
                            </button>
                            <button
                                v-if="selectedPdpIsEditable"
                                type="button"
                                class="inline-flex h-[22px] w-[22px] flex-none items-center justify-center rounded-md border text-[10px] hover:bg-muted"
                                :title="
                                    c.done ? 'Mark as not done' : 'Mark as done'
                                "
                                @click.stop="
                                    emit('toggleCriterionDone', s, i, !c.done)
                                "
                            >
                                <svg
                                    v-if="!c.done"
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                    class="h-3 w-3"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M3.5 10a6.5 6.5 0 1113 0 6.5 6.5 0 01-13 0zm9.204-2.79a1 1 0 10-1.414-1.414L8.5 8.586 7.21 7.296a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l3.494-3.5z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                                <svg
                                    v-else
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                    class="h-3 w-3 text-green-600"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M16.704 5.29a1 1 0 010 1.414l-7.5 7.5a1 1 0 01-1.414 0l-3-3a1 1 0 111.414-1.414l2.293 2.293 6.793-6.793a1 1 0 011.414 0z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <footer
                        class="mt-3 flex flex-wrap items-center justify-between gap-2"
                    >
                        <div class="text-xs text-muted-foreground">
                            Prio:
                            <span class="font-medium">{{ s.priority }}</span
                            ><span v-if="s.eta"> · ETA: {{ s.eta }}</span>
                        </div>
                        <div class="flex gap-2" v-if="selectedPdpIsEditable">
                            <button
                                class="rounded border px-2 py-1 text-xs hover:bg-muted"
                                @click="emit('openEditSkill', s)"
                            >
                                Edit
                            </button>
                            <button
                                class="rounded border px-2 py-1 text-xs text-destructive hover:bg-destructive hover:text-destructive-foreground"
                                @click="emit('deleteSkill', s.id)"
                            >
                                Delete
                            </button>
                        </div>
                    </footer>
                </article>
            </div>
        </div>
        <p v-else class="text-sm text-muted-foreground">
            No skills in this PDP. Add the first one.
        </p>
    </template>
    <p v-else class="text-sm text-muted-foreground">
        Select a PDP to view its skills.
    </p>
</template>
