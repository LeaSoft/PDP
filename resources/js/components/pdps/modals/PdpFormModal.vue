<script setup lang="ts">
import { useEscapableModal } from '@/composables/useEscapableModal';
import type { Pdp } from '@/pages/pdps/Index.vue';
import { computed, defineEmits, defineProps, reactive, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    form: Pdp;
    editingId: number | null;
}>();
const emit = defineEmits<{
    (e: 'update:open', v: boolean): void;
    (e: 'save', v: Pdp): void;
    (e: 'request-delete', id: number): void;
}>();

const localForm = reactive<Pdp>({
    id: 0,
    title: '',
    description: '',
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
watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    },
);

function close() {
    emit('update:open', false);
}
function save() {
    emit('save', { ...localForm });
}

function requestDelete() {
    if (!props.editingId) return;
    emit('request-delete', props.editingId);
}

useEscapableModal(
    computed(() => props.open),
    close,
);
</script>

<template>
    <div
        v-if="props.open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
    >
        <div
            class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-lg bg-background p-4 shadow-xl"
        >
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-base font-semibold">
                    {{ props.editingId ? 'Edit PDP' : 'Create PDP' }}
                </h3>
                <button
                    class="rounded p-1 text-muted-foreground hover:bg-muted"
                    @click="close"
                >
                    ✕
                </button>
            </div>

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
                    <label class="mb-1 block text-xs font-medium">Prio</label>
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
                    <label class="mb-1 block text-xs font-medium">ETA</label>
                    <input
                        v-model="localForm.eta"
                        type="text"
                        class="w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                        placeholder="e.g. Q4 2025 or 31.12.2025"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium">Status</label>
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
                        class="rounded border border-destructive/40 px-3 py-1.5 text-xs text-destructive hover:bg-destructive hover:text-destructive-foreground"
                        @click="requestDelete"
                    >
                        Delete PDP
                    </button>
                </div>
            </details>

            <div class="mt-4 flex justify-end gap-2">
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
        </div>
    </div>
</template>
