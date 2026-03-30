<script setup lang="ts">
import {
    Dialog,
    DialogContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { notifyError, notifySuccess } from '@/composables/useNotify';
import { fetchJson } from '@/lib/csrf';
import { GripVertical } from 'lucide-vue-next';
import { nextTick, ref } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';

defineProps<{ open: boolean }>();
const emit = defineEmits<{
    (e: 'update:open', v: boolean): void;
    (e: 'saved'): void;
}>();

interface NewSkill {
    skill: string;
    description?: string;
    criteriaText?: string;
}

const skillsContainer = ref<HTMLDivElement | null>(null);
const newTemplate = ref<{
    title: string;
    description: string;
    skills: NewSkill[];
}>({ title: '', description: '', skills: [] });

function close() {
    emit('update:open', false);
}

function reset() {
    newTemplate.value = { title: '', description: '', skills: [] };
}

async function addSkillRow() {
    newTemplate.value.skills.push({
        skill: '',
        description: '',
        criteriaText: '',
    });
    await nextTick();
    skillsContainer.value?.lastElementChild?.scrollIntoView({
        behavior: 'smooth',
    });
}

function removeSkillRow(i: number) {
    newTemplate.value.skills.splice(i, 1);
}

async function createTemplate() {
    const title = newTemplate.value.title.trim();
    if (!title) {
        notifyError('Please enter template title');
        return;
    }
    const skillsPayload = newTemplate.value.skills.map((s, idx) => {
        const criteriaItems = (s.criteriaText || '')
            .split(/\n+/)
            .map((t) => t.trim())
            .filter(Boolean)
            .map((t) => ({ text: t }));
        return {
            skill: s.skill || `Skill ${idx + 1}`,
            description: s.description || null,
            criteria: criteriaItems.length
                ? JSON.stringify(criteriaItems)
                : null,
            priority: 'Medium',
            eta: null,
            status: 'Planned',
            order_column: idx,
        };
    });
    try {
        await fetchJson('/pdps/templates.json', {
            method: 'POST',
            body: JSON.stringify({
                version: 1,
                pdp: {
                    title,
                    description: newTemplate.value.description || '',
                    priority: 'Medium',
                    eta: null,
                    status: 'Planned',
                },
                skills: skillsPayload,
            }),
        });
        notifySuccess('Template created');
        close();
        reset();
        emit('saved');
    } catch (e: any) {
        notifyError('Failed to create template: ' + (e?.message || 'Error'));
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent
            class="flex max-h-[85vh] max-w-3xl flex-col gap-0 p-0"
        >
            <div
                class="flex items-center rounded-t-lg border-b bg-background px-6 py-4 pr-14"
            >
                <DialogTitle class="text-base font-semibold"
                    >Create Skill Template</DialogTitle
                >
            </div>

            <div class="flex-1 overflow-y-auto">
            <div class="space-y-4 px-6 py-4">
                <div>
                    <label class="mb-1 block text-xs font-medium"
                        >Template title</label
                    >
                    <input
                        v-model="newTemplate.title"
                        type="text"
                        class="w-full rounded-md border px-3 py-2 text-sm"
                        placeholder="e.g. Frontend Intern PDP"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium"
                        >Description</label
                    >
                    <textarea
                        v-model="newTemplate.description"
                        rows="2"
                        class="w-full rounded-md border px-3 py-2 text-sm"
                        placeholder="Optional short description"
                    ></textarea>
                </div>

                <div>
                    <label class="mb-2 block text-xs font-semibold"
                        >Skill items</label
                    >
                    <div
                        v-if="newTemplate.skills.length === 0"
                        class="text-xs text-muted-foreground"
                    >
                        No skills yet. Add the first one.
                    </div>
                    <div ref="skillsContainer">
                        <VueDraggable
                            v-model="newTemplate.skills"
                            handle=".drag-handle"
                            :animation="150"
                        >
                            <div
                                v-for="(s, i) in newTemplate.skills"
                                :key="i"
                                class="mb-3 rounded-md border p-3"
                            >
                                <div class="mb-2 flex items-start gap-2">
                                    <GripVertical
                                        class="drag-handle mt-1 size-4 shrink-0 cursor-grab select-none text-muted-foreground active:cursor-grabbing"
                                    />
                                    <div
                                        class="grid min-w-0 flex-1 grid-cols-1 gap-2 md:grid-cols-2"
                                    >
                                        <div>
                                            <label
                                                class="mb-1 block text-xs font-medium"
                                                >Skill</label
                                            >
                                            <input
                                                v-model="s.skill"
                                                type="text"
                                                class="w-full rounded-md border px-3 py-2 text-sm"
                                                placeholder="e.g. Vue Basics"
                                            />
                                        </div>
                                        <div>
                                            <label
                                                class="mb-1 block text-xs font-medium"
                                                >Description</label
                                            >
                                            <input
                                                v-model="s.description"
                                                type="text"
                                                class="w-full rounded-md border px-3 py-2 text-sm"
                                                placeholder="Optional"
                                            />
                                        </div>
                                    </div>
                                </div>
                                <div class="pl-6">
                                    <label
                                        class="mb-1 block text-xs font-medium"
                                        >Win Criteria (one per line)</label
                                    >
                                    <textarea
                                        v-model="s.criteriaText"
                                        rows="3"
                                        class="w-full rounded-md border px-3 py-2 text-sm"
                                        placeholder="Explain tasks or acceptance criteria, each on a new line"
                                    ></textarea>
                                </div>
                                <div class="mt-2 flex justify-end pl-6">
                                    <button
                                        class="rounded-md border px-2 py-1 text-xs text-destructive hover:bg-destructive hover:text-destructive-foreground"
                                        @click="removeSkillRow(i)"
                                    >
                                        Remove
                                    </button>
                                </div>
                            </div>
                        </VueDraggable>
                        <div class="mt-2 flex justify-center">
                            <button
                                class="rounded-md border px-3 py-1.5 text-xs hover:bg-muted"
                                @click="addSkillRow"
                            >
                                + Add skill
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            </div>

            <div
                class="flex justify-end gap-2 rounded-b-lg border-t bg-background px-6 py-4"
            >
                <button
                    class="rounded-md border px-3 py-2 text-xs hover:bg-muted"
                    @click="close"
                >
                    Cancel
                </button>
                <button
                    class="rounded-md bg-primary px-3 py-2 text-xs font-medium text-primary-foreground hover:opacity-90"
                    @click="createTemplate"
                >
                    Create
                </button>
            </div>
        </DialogContent>
    </Dialog>
</template>
