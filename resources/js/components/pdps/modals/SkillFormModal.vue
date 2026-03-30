<script setup lang="ts">
import {
    Dialog,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import type { PdpSkill } from '@/pages/pdps/Index.vue';
import { reactive, watch } from 'vue';

interface CriteriaItem {
    text: string;
    comment?: string;
    done?: boolean;
}

const props = defineProps<{
    open: boolean;
    form: PdpSkill;
    criteriaItems: CriteriaItem[];
    criteriaTextInput: string;
    addCriteriaFromInput: () => void;
    removeCriteriaAt: (i: number) => void;
    updateCriteriaAt: (i: number, text: string) => void;
}>();

const emit = defineEmits<{
    (e: 'update:open', v: boolean): void;
    (e: 'update:criteria-text-input', v: string): void;
    (e: 'save', v: PdpSkill): void;
}>();

const localForm = reactive<PdpSkill>({
    id: 0,
    pdp_id: 0,
    skill: '',
    description: '',
    criteria: '',
    priority: 'Medium',
    eta: '',
    status: 'Planned',
});

watch(
    () => props.open,
    (v) => {
        if (v) Object.assign(localForm, props.form);
    },
);

function close() {
    emit('update:open', false);
}
function save() {
    emit('save', { ...localForm });
}
</script>

<template>
    <Dialog :open="props.open" @update:open="$emit('update:open', $event)">
        <DialogScrollContent class="sm:max-w-lg gap-0 p-0">
            <div
                class="sticky top-0 z-10 border-b bg-background px-6 py-4 pr-14 sm:rounded-t-lg"
            >
                <DialogHeader>
                    <DialogTitle>
                        {{ localForm.id ? 'Edit Skill' : 'Add Skill' }}
                    </DialogTitle>
                </DialogHeader>
            </div>

            <div class="px-6 py-4">
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div class="md:col-span-1">
                        <label class="mb-1 block text-xs font-medium"
                            >Skill to achieve</label
                        >
                        <input
                            v-model="localForm.skill"
                            type="text"
                            class="w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                            placeholder="e.g. Intermediate level of English"
                        />
                    </div>
                    <div class="md:col-span-1">
                        <label class="mb-1 block text-xs font-medium"
                            >Prio</label
                        >
                        <select
                            v-model="localForm.priority"
                            class="w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                        >
                            <option>Low</option>
                            <option>Medium</option>
                            <option>High</option>
                        </select>
                    </div>

                    <div class="md:col-span-1">
                        <label class="mb-1 block text-xs font-medium"
                            >ETA</label
                        >
                        <input
                            v-model="localForm.eta"
                            type="text"
                            class="w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                            placeholder="e.g. Q4 2025 or 31.12.2025"
                        />
                    </div>
                    <div class="md:col-span-1">
                        <label class="mb-1 block text-xs font-medium"
                            >Status</label
                        >
                        <select
                            v-model="localForm.status"
                            class="w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                        >
                            <option>Planned</option>
                            <option>In Progress</option>
                            <option>Done</option>
                            <option>Blocked</option>
                        </select>
                    </div>

                    <div class="md:col-span-1">
                        <label class="mb-1 block text-xs font-medium"
                            >Description</label
                        >
                        <textarea
                            v-model="localForm.description"
                            rows="6"
                            class="w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                            placeholder="Short description"
                        ></textarea>
                    </div>
                    <div class="md:col-span-1">
                        <label class="mb-1 block text-xs font-medium"
                            >Win Criteria</label
                        >
                        <div class="space-y-2 rounded-md border px-2 py-2">
                            <div
                                v-if="props.criteriaItems.length"
                                class="space-y-2"
                            >
                                <div
                                    v-for="(item, i) in props.criteriaItems"
                                    :key="i"
                                    class="flex items-start gap-2"
                                >
                                    <input
                                        :value="item.text"
                                        type="text"
                                        class="flex-1 rounded-md border bg-transparent px-2 py-1 text-xs"
                                        placeholder="Criterion"
                                        @input="
                                            props.updateCriteriaAt(
                                                i,
                                                ($event.target as HTMLInputElement)
                                                    .value,
                                            )
                                        "
                                    />
                                    <button
                                        type="button"
                                        class="rounded border px-2 py-1 text-[11px]"
                                        @click="props.removeCriteriaAt(i)"
                                    >
                                        Remove
                                    </button>
                                </div>
                            </div>
                            <div class="flex items-start gap-2">
                                <input
                                    :value="props.criteriaTextInput"
                                    type="text"
                                    class="flex-1 rounded-md border bg-transparent px-2 py-1 text-sm"
                                    placeholder="New criterion"
                                    @input="
                                        emit(
                                            'update:criteria-text-input',
                                            ($event.target as HTMLInputElement)
                                                .value,
                                        )
                                    "
                                    @keydown.enter.prevent="
                                        props.addCriteriaFromInput
                                    "
                                />
                                <button
                                    type="button"
                                    class="rounded bg-primary px-2 py-1 text-xs text-primary-foreground hover:opacity-90"
                                    @click="props.addCriteriaFromInput"
                                >
                                    Add
                                </button>
                            </div>
                            <p class="text-[11px] text-muted-foreground">
                                Comments can be added while working by clicking
                                the criterion badge.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="sticky bottom-0 z-10 flex justify-end gap-2 border-t bg-background px-6 py-4 sm:rounded-b-lg"
            >
                <button
                    class="rounded border px-3 py-2 text-sm hover:bg-muted"
                    @click="close"
                >
                    Cancel
                </button>
                <button
                    class="rounded bg-primary px-3 py-2 text-sm text-primary-foreground hover:opacity-90"
                    @click="save"
                >
                    Save
                </button>
            </div>
        </DialogScrollContent>
    </Dialog>
</template>
