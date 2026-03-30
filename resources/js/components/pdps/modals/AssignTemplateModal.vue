<script setup lang="ts">
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { notifyError, notifySuccess } from '@/composables/useNotify';
import { fetchJson } from '@/lib/csrf';
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    templateKey: string | null;
}>();
const emit = defineEmits<{
    (e: 'update:open', v: boolean): void;
}>();

type PdpOption = {
    id: number;
    title: string;
    status: 'Planned' | 'In Progress' | 'Done' | 'Blocked';
    skills_count?: number;
};

const pdpOptions = ref<PdpOption[]>([]);
const selectedPdpId = ref<number>(0);
const assignLoading = ref(false);
const submitting = ref(false);
const canSubmit = computed(() => !!selectedPdpId.value);

watch(
    () => props.open,
    async (isOpen) => {
        if (!isOpen) return;
        selectedPdpId.value = 0;
        pdpOptions.value = [];
        try {
            assignLoading.value = true;
            const list: any[] = await fetchJson('/pdps.json');
            pdpOptions.value = (Array.isArray(list) ? list : []).filter(
                (p: any) => p && p.status !== 'Done',
            );
        } catch (e: any) {
            notifyError(
                'Failed to load your PDPs: ' + (e?.message || 'Error'),
            );
        } finally {
            assignLoading.value = false;
        }
    },
);

async function submitAssign() {
    if (!props.templateKey || !selectedPdpId.value) return;
    submitting.value = true;
    try {
        await fetchJson(
            `/pdps/${selectedPdpId.value}/templates/${encodeURIComponent(props.templateKey)}/assign.json`,
            { method: 'POST', body: JSON.stringify({}) },
        );
        notifySuccess('Template skills have been added to the selected PDP');
        emit('update:open', false);
        router.visit('/pdps');
    } catch (e: any) {
        notifyError(
            'Failed to add template skills: ' + (e?.message || 'Error'),
        );
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Add template skills to PDP</DialogTitle>
                <DialogDescription>
                    All skills from the selected template will be added to the
                    chosen PDP. Existing skills from this template will be
                    skipped.
                </DialogDescription>
            </DialogHeader>

            <div>
                <label class="mb-1 block text-xs font-medium">Target PDP</label>
                <select
                    v-model.number="selectedPdpId"
                    class="w-full rounded-md border px-3 py-2 text-sm"
                    :disabled="assignLoading"
                >
                    <option :value="0" disabled>Select your PDP</option>
                    <option
                        v-for="p in pdpOptions"
                        :key="p.id"
                        :value="p.id"
                    >
                        {{ p.title }} · {{ p.status }} ·
                        {{ p.skills_count ?? 0 }} skills
                    </option>
                </select>
                <p
                    v-if="assignLoading"
                    class="mt-1 text-xs text-muted-foreground"
                >
                    Loading your PDPs…
                </p>
                <p
                    v-else-if="pdpOptions.length === 0"
                    class="mt-1 text-xs text-muted-foreground"
                >
                    You have no editable PDPs (only non-completed are
                    available).
                </p>
            </div>

            <DialogFooter>
                <button
                    class="rounded-md border px-3 py-2 text-xs hover:bg-muted"
                    @click="$emit('update:open', false)"
                >
                    Cancel
                </button>
                <button
                    class="rounded-md bg-primary px-3 py-2 text-xs font-medium text-primary-foreground hover:opacity-90 disabled:opacity-60"
                    :disabled="!canSubmit || submitting"
                    @click="submitAssign"
                >
                    Add to PDP
                </button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
