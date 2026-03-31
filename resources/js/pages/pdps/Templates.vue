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
        <div class="flex flex-col gap-6 rounded-xl p-6">
            <Heading
                title="Skill List"
                description="Skill template catalogue. Add the necessary skills to your PDP, edit or delete templates."
            />

            <div
                class="rounded-xl border border-sidebar-border/70 p-5 shadow-sm dark:border-sidebar-border"
            >
                <div class="mb-5 flex items-center justify-between">
                    <h2 class="text-base font-semibold">Skill List</h2>
                    <button
                        v-if="isModerator"
                        class="inline-flex items-center gap-1 rounded-md bg-primary px-3 py-2 text-xs font-medium text-primary-foreground shadow-sm hover:opacity-90"
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
                        class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3"
                    >
                        <div
                            v-for="t in templates"
                            :key="t.key"
                            class="flex flex-col rounded-xl border p-4 transition hover:bg-muted/30"
                        >
                            <!-- Card body -->
                            <div class="flex-1">
                                <div class="mb-1 font-semibold">{{ t.title }}</div>
                                <p
                                    v-if="t.description"
                                    class="mb-2 line-clamp-2 text-xs text-muted-foreground"
                                >
                                    {{ t.description }}
                                </p>
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="rounded-full bg-muted px-2 py-0.5 text-[11px] font-medium text-muted-foreground">
                                        {{ t.skills_count }} skills
                                    </span>
                                </div>
                            </div>

                            <!-- Card actions -->
                            <div class="mt-4 flex items-center justify-between border-t pt-3">
                                <div class="flex items-center gap-3">
                                    <button
                                        v-if="isModerator"
                                        class="text-xs text-muted-foreground underline-offset-2 hover:text-foreground hover:underline"
                                        @click="openEdit(t.key)"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        v-if="isModerator"
                                        class="text-xs text-destructive underline-offset-2 hover:underline"
                                        @click="onDeleteTemplate(t.key)"
                                    >
                                        Delete
                                    </button>
                                </div>
                                <button
                                    class="rounded-md bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground shadow-sm hover:opacity-90"
                                    @click="openAssignModal(t.key)"
                                >
                                    Add to my PDP
                                </button>
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
