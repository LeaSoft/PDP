<script setup lang="ts">
import AdminTable from '@/components/admin/AdminTable.vue';
import { notifyError, notifySuccess } from '@/composables/useNotify';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { fetchJson } from '@/lib/csrf';
import { router } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

type Row = {
    user_id: number;
    name: string;
    email: string;
    pro_level_key: string | null;
    current_level_key: string | null;
    last_activity_at: string | null;
    pdp_count: number;
};

type ProLevel = {
    key: string;
    title: string;
    threshold: number;
};

const loading = ref(false);
const rows = ref<Row[]>([]);
const levels = ref<ProLevel[]>([]);
const total = ref(0);
const pageNum = ref(1);
const perPage = ref(20);
const sortBy = ref('last_activity_at');
const sortDir = ref<'asc' | 'desc'>('desc');
const changingLevelFor = ref<number | null>(null);

const activeLastDays = ref<number | null>(
    new URLSearchParams(location.search).get('active_last_days')
        ? Number(new URLSearchParams(location.search).get('active_last_days'))
        : null,
);

const columns = [
    { key: 'name', label: 'Name', sortable: true },
    { key: 'email', label: 'Email', sortable: true },
    { key: 'pro_level_key', label: 'Start Level' },
    { key: 'current_level_key', label: 'Current Level' },
    { key: 'actions', label: 'Actions', width: '190px' },
    { key: 'last_activity_at', label: 'Last Activity', sortable: true },
    { key: 'pdp_count', label: 'PDPs', sortable: true },
];

const levelTitles = computed<Record<string, string>>(() => {
    const map: Record<string, string> = {};
    for (const lvl of levels.value) map[lvl.key] = lvl.title;
    return map;
});

function toQuery() {
    const p = new URLSearchParams();
    if (activeLastDays.value)
        p.set('active_last_days', String(activeLastDays.value));
    p.set('page', String(pageNum.value));
    p.set('per_page', String(perPage.value));
    p.set('sort_by', sortBy.value);
    p.set('sort_dir', sortDir.value);
    return p;
}

function levelLabel(key: string | null): string {
    if (!key) return '—';
    return levelTitles.value[key] || key;
}

async function loadLevels() {
    try {
        const res = await fetchJson('/profile/pro-level.json');
        const list = Array.isArray(res?.levels) ? res.levels : [];
        levels.value = list.map((it: any) => ({
            key: String(it?.key),
            title: String(it?.title),
            threshold: Number(it?.threshold ?? 0),
        }));
    } catch {
        levels.value = [];
    }
}

async function load() {
    loading.value = true;
    try {
        const qs = toQuery().toString();
        const res = await fetchJson(`/admin/users.json?${qs}`);
        rows.value = Array.isArray(res?.data) ? res.data : [];
        total.value = Number(res?.meta?.total || 0);
    } finally {
        loading.value = false;
    }
}

function syncUrl() {
    const q = toQuery().toString();
    router.visit(`/admin/users?${q}`, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: [],
    });
}

watch([pageNum, perPage, sortBy, sortDir], () => {
    syncUrl();
    load();
});

async function setUserLevel(row: Row, levelKey: string) {
    if (!levelKey) return;
    changingLevelFor.value = row.user_id;
    try {
        const res = await fetchJson(`/users/${row.user_id}/pro-level.json`, {
            method: 'PUT',
            body: JSON.stringify({ level_key: levelKey }),
        });
        row.pro_level_key = levelKey;
        row.current_level_key =
            typeof res?.key === 'string' ? res.key : row.current_level_key;
        notifySuccess(`Level for ${row.name} updated`);
    } catch (e: any) {
        notifyError(e?.message || 'Failed to update level');
        await load();
    } finally {
        changingLevelFor.value = null;
    }
}

onMounted(() => {
    const url = new URL(location.href);
    pageNum.value = Number(url.searchParams.get('page') || 1);
    perPage.value = Number(url.searchParams.get('per_page') || 20);
    sortBy.value = url.searchParams.get('sort_by') || 'last_activity_at';
    sortDir.value = (url.searchParams.get('sort_dir') as any) || 'desc';
    loadLevels();
    load();
});
</script>

<template>
    <AdminLayout>
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-lg font-semibold">Users</h1>
            <div class="flex items-center gap-2 text-xs">
                <label
                    >Active last (days)
                    <input
                        class="ml-1 w-20 rounded border px-2 py-1"
                        type="number"
                        min="0"
                        v-model.number="activeLastDays"
                        @change="
                            pageNum = 1;
                            syncUrl();
                            load();
                        "
                    />
                </label>
            </div>
        </div>

        <AdminTable
            :columns="columns"
            :rows="rows"
            :total="total"
            :page="pageNum"
            :per-page="perPage"
            :sort-by="sortBy"
            :sort-dir="sortDir as any"
            :loading="loading"
            @update:page="pageNum = $event"
            @update:perPage="
                perPage = $event;
                pageNum = 1;
            "
            @update:sort="
                (s) => {
                    sortBy = s.by;
                    sortDir = s.dir;
                    pageNum = 1;
                }
            "
        >
            <template #cell:last_activity_at="{ row }">
                <span>{{
                    row.last_activity_at
                        ? new Date(row.last_activity_at).toLocaleString()
                        : '—'
                }}</span>
            </template>
            <template #cell:pro_level_key="{ row }">
                <span>{{ levelLabel(row.pro_level_key) }}</span>
            </template>
            <template #cell:current_level_key="{ row }">
                <span>{{ levelLabel(row.current_level_key) }}</span>
            </template>
            <template #cell:actions="{ row }">
                <select
                    class="w-full rounded border border-border/80 bg-background px-2 py-1 text-xs text-foreground"
                    :value="row.pro_level_key ?? ''"
                    :disabled="
                        changingLevelFor === row.user_id || levels.length === 0
                    "
                    @change="
                        setUserLevel(
                            row,
                            String(
                                ($event.target as HTMLSelectElement).value ||
                                    '',
                            ),
                        )
                    "
                >
                    <option value="" disabled>Select level…</option>
                    <option
                        v-for="lvl in levels"
                        :key="lvl.key"
                        :value="lvl.key"
                    >
                        {{ lvl.title }}
                    </option>
                </select>
            </template>
        </AdminTable>
    </AdminLayout>
</template>
