<script setup lang="ts">
import {
    Dialog,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import type { Pdp, TemplateOption } from '@/pages/pdps/Index.vue';
import { reactive, ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    form: Pdp;
    editingId: number | null;
    templateOptions: TemplateOption[];
}>();
const emit = defineEmits<{
    (e: 'update:open', v: boolean): void;
    (e: 'save', v: Pdp): void;
    (e: 'confirm-delete', id: number): void;
}>();

const localForm = reactive<Pdp>({
    id: 0,
    title: '',
    description: '',
    priority: 'Medium',
    eta: '',
    status: 'Planned',
    template_keys: [],
});

const confirmingDelete = ref(false);

watch(
    () => props.open,
    (v) => {
        if (v) {
            Object.assign(localForm, props.form);
            localForm.template_keys = Array.isArray(props.form.template_keys)
                ? [...props.form.template_keys]
                : [];
        } else {
            confirmingDelete.value = false;
        }
    },
);

function close() {
    emit('update:open', false);
}
function save() {
    emit('save', { ...localForm });
}
function doDelete() {
    if (!props.editingId) return;
    confirmingDelete.value = false;
    emit('confirm-delete', props.editingId);
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
                        {{ props.editingId ? 'Edit PDP' : 'Create PDP' }}
                    </DialogTitle>
                </DialogHeader>
            </div>

            <div class="px-6 py-4">
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium"
                            >PDP Title</label
                        >
                        <input
                            v-model="localForm.title"
                            type="text"
                            class="w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                            placeholder="e.g. Promotion to Senior"
                        />
                    </div>
                    <div>
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
                    <div>
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
                    <div>
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
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-medium"
                            >Description</label
                        >
                        <textarea
                            v-model="localForm.description"
                            rows="4"
                            class="w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                            placeholder="Short description of this plan"
                        ></textarea>
                    </div>
                    <div v-if="!props.editingId" class="md:col-span-2">
                        <label class="mb-1 block text-xs font-medium"
                            >Skill templates</label
                        >
                        <div class="max-h-56 overflow-auto rounded-md border p-2">
                            <p
                                v-if="props.templateOptions.length === 0"
                                class="text-xs text-muted-foreground"
                            >
                                No templates available.
                            </p>
                            <label
                                v-for="template in props.templateOptions"
                                :key="template.key"
                                class="flex cursor-pointer items-start gap-2 rounded px-1 py-1.5 hover:bg-muted/50"
                            >
                                <input
                                    v-model="localForm.template_keys"
                                    type="checkbox"
                                    :value="template.key"
                                    class="mt-0.5"
                                />
                                <span class="min-w-0 text-xs">
                                    <span class="block font-medium">
                                        {{ template.title }}
                                    </span>
                                    <span class="text-muted-foreground">
                                        {{ template.skills_count }} skills
                                    </span>
                                </span>
                            </label>
                        </div>
                        <p class="mt-1 text-[11px] text-muted-foreground">
                            Selected templates will be used to pre-fill skills
                            in the new PDP.
                        </p>
                    </div>
                </div>

                <details
                    v-if="props.editingId"
                    class="mt-4 rounded-md border border-destructive/30 p-3"
                >
                    <summary
                        class="cursor-pointer text-xs font-medium text-muted-foreground"
                    >
                        Danger zone
                    </summary>
                    <div class="mt-3 flex items-center justify-between gap-2">
                        <p class="text-xs text-muted-foreground">
                            Delete this PDP and all its skills.
                        </p>
                        <button
                            v-if="!confirmingDelete"
                            class="rounded border border-destructive/40 px-3 py-1.5 text-xs text-destructive hover:bg-destructive hover:text-destructive-foreground"
                            @click="confirmingDelete = true"
                        >
                            Delete PDP
                        </button>
                        <div v-else class="flex items-center gap-2">
                            <span class="text-xs text-muted-foreground"
                                >Are you sure?</span
                            >
                            <button
                                class="rounded border px-3 py-1.5 text-xs hover:bg-muted"
                                @click="confirmingDelete = false"
                            >
                                Cancel
                            </button>
                            <button
                                class="rounded border border-destructive bg-destructive px-3 py-1.5 text-xs text-destructive-foreground hover:opacity-90"
                                @click="doDelete"
                            >
                                Yes, delete
                            </button>
                        </div>
                    </div>
                </details>
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
