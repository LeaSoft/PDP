<script setup lang="ts">
import {
    Dialog,
    DialogContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { notifyError, notifySuccess } from '@/composables/useNotify';
import { fetchJson } from '@/lib/csrf';
import { GripVertical } from 'lucide-vue-next';
import { nextTick, ref, watch } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';

const props = defineProps<{
    open: boolean;
    templateKey: string | null;
}>();
const emit = defineEmits<{
    (e: 'update:open', v: boolean): void;
    (e: 'saved'): void;
}>();

interface EditSkill {
    key?: string;
    skill: string;
    description?: string;
    criteriaText?: string;
    order_column?: number;
}

const editSkillsContainer = ref<HTMLDivElement | null>(null);
const editTemplate = ref<{
    title: string;
    description: string;
    skills: EditSkill[];
}>({ title: '', description: '', skills: [] });

async function loadTemplate(key: string) {
    try {
        const data = await fetchJson(
            `/pdps/templates/${encodeURIComponent(key)}.json`,
        );
        const payload = data?.data || {};
        const p = payload.pdp || {};
        const skills: any[] = payload.skills || [];
        editTemplate.value = {
            title: String(p.title || data.title || ''),
            description: p.description || data.description || '',
            skills: skills.map((s, idx) => {
                let criteriaText = '';
                try {
                    const items = s.criteria ? JSON.parse(s.criteria) : [];
                    if (Array.isArray(items)) {
                        criteriaText = items
                            .map((it: any) => it?.text ?? '')
                            .filter(Boolean)
                            .join('\n');
                    }
                } catch {}
                return {
                    key: s.key,
                    skill: s.skill || `Skill ${idx + 1}`,
                    description: s.description || '',
                    criteriaText,
                    order_column:
                        typeof s.order_column === 'number'
                            ? s.order_column
                            : idx,
                };
            }),
        };
    } catch (e: any) {
        notifyError(
            'Failed to load template for edit: ' + (e?.message || 'Error'),
        );
    }
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen && props.templateKey) {
            loadTemplate(props.templateKey);
        }
    },
);

function recalcEditOrder() {
    editTemplate.value.skills.forEach((s, idx) => {
        s.order_column = idx;
    });
}

async function addEditSkillRow() {
    editTemplate.value.skills.push({
        skill: '',
        description: '',
        criteriaText: '',
        order_column: editTemplate.value.skills.length,
    });
    await nextTick();
    editSkillsContainer.value?.lastElementChild?.scrollIntoView({
        behavior: 'smooth',
    });
}

function removeEditSkillRow(i: number) {
    editTemplate.value.skills.splice(i, 1);
    recalcEditOrder();
}

function close() {
    emit('update:open', false);
}

async function saveEdit() {
    if (!props.templateKey) return;
    const title = editTemplate.value.title.trim();
    if (!title) {
        notifyError('Please enter template title');
        return;
    }
    const skillsPayload = editTemplate.value.skills.map((s, idx) => {
        const criteriaItems = (s.criteriaText || '')
            .split(/\n+/)
            .map((t) => t.trim())
            .filter(Boolean)
            .map((t) => ({ text: t }));
        return {
            key: s.key,
            skill: s.skill || `Skill ${idx + 1}`,
            description: s.description || null,
            criteria: criteriaItems.length
                ? JSON.stringify(criteriaItems)
                : null,
            priority: 'Medium',
            eta: null,
            status: 'Planned',
            order_column:
                typeof s.order_column === 'number' ? s.order_column : idx,
        };
    });
    try {
        await fetchJson(
            `/pdps/templates/${encodeURIComponent(props.templateKey)}.json`,
            {
                method: 'PUT',
                body: JSON.stringify({
                    version: 1,
                    pdp: {
                        title,
                        description: editTemplate.value.description || '',
                        priority: 'Medium',
                        eta: null,
                        status: 'Planned',
                    },
                    skills: skillsPayload,
                }),
            },
        );
        notifySuccess('Template updated');
        close();
        emit('saved');
    } catch (e: any) {
        notifyError('Failed to update template: ' + (e?.message || 'Error'));
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent
            class="flex max-h-[85vh] max-w-3xl flex-col gap-0 p-0"
        >
            <div
                class="sticky top-0 z-10 flex items-center rounded-t-lg border-b bg-background px-6 py-4 pr-14"
            >
                <DialogTitle class="text-base font-semibold"
                    >Edit Skill Template</DialogTitle
                >
            </div>

            <div class="flex-1 overflow-y-auto">
            <div class="space-y-4 px-6 py-4">
                <div>
                    <label class="mb-1 block text-xs font-medium"
                        >Template title</label
                    >
                    <input
                        v-model="editTemplate.title"
                        type="text"
                        class="w-full rounded-md border px-3 py-2 text-sm"
                        placeholder="Template title"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium"
                        >Description</label
                    >
                    <textarea
                        v-model="editTemplate.description"
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
                        v-if="editTemplate.skills.length === 0"
                        class="text-xs text-muted-foreground"
                    >
                        No skills yet. Add the first one.
                    </div>
                    <div ref="editSkillsContainer">
                        <VueDraggable
                            v-model="editTemplate.skills"
                            handle=".drag-handle"
                            :animation="150"
                            @end="recalcEditOrder"
                        >
                            <div
                                v-for="(s, i) in editTemplate.skills"
                                :key="s.key || i"
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
                                        @click="removeEditSkillRow(i)"
                                    >
                                        Remove
                                    </button>
                                </div>
                            </div>
                        </VueDraggable>
                        <div class="mt-2 flex justify-center">
                            <button
                                class="rounded-md border px-3 py-1.5 text-xs hover:bg-muted"
                                @click="addEditSkillRow"
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
                    @click="saveEdit"
                >
                    Save
                </button>
            </div>
        </DialogContent>
    </Dialog>
</template>
