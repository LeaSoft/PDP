<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import AssignTemplateModal from '@/components/pdps/modals/AssignTemplateModal.vue';
import CreateTemplateModal from '@/components/pdps/modals/CreateTemplateModal.vue';
import EditTemplateModal from '@/components/pdps/modals/EditTemplateModal.vue';
import { notifyError, notifySuccess } from '@/composables/useNotify';
import AppLayout from '@/layouts/AppLayout.vue';
import { fetchJson } from '@/lib/csrf';
import { Head, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

interface TemplateItem {
    key: string;
    title: string;
    description?: string;
    priority: 'Low' | 'Medium' | 'High';
    status: 'Planned' | 'In Progress' | 'Done' | 'Blocked';
    skills_count: number;
}

const templates = ref<TemplateItem[]>([]);
const loading = ref(false);

const page = usePage<{ auth: { user: { is_moderator: boolean } } }>();
const isModerator = computed(() =>
    Boolean(page.props.auth?.user?.is_moderator),
);

const breadcrumbs = computed(() => [
    { title: 'Skill List', href: '/pdps/templates' },
]);

// Modal state
const showCreateModal = ref(false);
const showEditModal = ref(false);
const editingKey = ref<string | null>(null);
const showAssignModal = ref(false);
const assignTemplateKey = ref<string | null>(null);

function openEdit(key: string) {
    editingKey.value = key;
    showEditModal.value = true;
}

function onEditModalUpdate(v: boolean) {
    showEditModal.value = v;
    if (!v) editingKey.value = null;
}

function openAssignModal(key: string) {
    assignTemplateKey.value = key;
    showAssignModal.value = true;
}

function onAssignModalUpdate(v: boolean) {
    showAssignModal.value = v;
    if (!v) assignTemplateKey.value = null;
}

async function onDeleteTemplate(key: string) {
    const ok = window.confirm(
        'Видалити цей шаблон?\n\nБуде також видалено пов\u2019язані скіли у всіх PDP (окрім тих, що були змінені вручну). Це дію не можна скасувати.',
    );
    if (!ok) return;
    try {
        await fetchJson(`/pdps/templates/${encodeURIComponent(key)}.json`, {
            method: 'DELETE',
        });
        notifySuccess('Template deleted');
        await loadTemplates();
    } catch (e: any) {
        notifyError('Failed to delete template: ' + (e?.message || 'Error'));
    }
}

async function loadTemplates() {
    loading.value = true;
    try {
        templates.value = await fetchJson('/pdps/templates.json');
    } catch (e: any) {
        notifyError('Failed to load templates: ' + (e?.message || 'Error'));
    } finally {
        loading.value = false;
    }
}

onMounted(loadTemplates);
</script>

<template>
    <Head title="Skill List" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 rounded-xl p-4">
            <Heading
                title="Skill List"
                description="Skill template catalogue. Add the necessary skills to your PDP, edit or delete templates."
            />

            <div
                class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-semibold">Skill List</h2>
                    <button
                        v-if="isModerator"
                        class="rounded-md bg-primary px-3 py-2 text-xs font-medium text-primary-foreground hover:opacity-90"
                        @click="showCreateModal = true"
                    >
                        + Create Skill Template
                    </button>
                </div>

                <div v-if="loading" class="text-sm text-muted-foreground">
                    Loading…
                </div>
                <div v-else>
                    <div
                        v-if="templates.length"
                        class="grid grid-cols-1 gap-3 md:grid-cols-2"
                    >
                        <div
                            v-for="t in templates"
                            :key="t.key"
                            class="rounded-md border p-3"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="font-medium">{{ t.title }}</div>
                                    <div
                                        v-if="t.description"
                                        class="mt-0.5 text-xs text-muted-foreground"
                                    >
                                        {{ t.description }}
                                    </div>
                                    <div
                                        class="mt-1 text-[11px] text-muted-foreground"
                                    >
                                        {{ t.skills_count }} skills ·
                                        {{ t.priority }} · {{ t.status }}
                                    </div>
                                </div>
                                <div
                                    class="flex shrink-0 flex-col items-end gap-2"
                                >
                                    <button
                                        v-if="isModerator"
                                        class="w-32 rounded-md border px-3 py-1.5 text-center text-xs hover:bg-muted"
                                        @click="openEdit(t.key)"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        v-if="isModerator"
                                        class="w-32 rounded-md border px-3 py-1.5 text-center text-xs text-destructive hover:bg-destructive hover:text-destructive-foreground"
                                        @click="onDeleteTemplate(t.key)"
                                    >
                                        Delete
                                    </button>
                                    <button
                                        class="w-32 rounded-md bg-primary px-3 py-1.5 text-center text-xs font-medium text-primary-foreground hover:opacity-90"
                                        @click="openAssignModal(t.key)"
                                    >
                                        Add to my PDP
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">
                        No templates yet.
                    </p>
                </div>
            </div>
        </div>

        <CreateTemplateModal
            :open="showCreateModal"
            @update:open="showCreateModal = $event"
            @saved="loadTemplates"
        />
        <EditTemplateModal
            :open="showEditModal"
            :template-key="editingKey"
            @update:open="onEditModalUpdate"
            @saved="loadTemplates"
        />
        <AssignTemplateModal
            :open="showAssignModal"
            :template-key="assignTemplateKey"
            @update:open="onAssignModalUpdate"
        />
    </AppLayout>
</template>
