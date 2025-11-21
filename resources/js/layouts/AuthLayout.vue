<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import BaseAuthLayout from '@/layouts/auth/AuthSimpleLayout.vue'
import type { User } from '@/types'
import { notifyError, notifySuccess } from '@/composables/useNotify'
import { fetchJson } from '@/lib/csrf'

defineProps<{
    title?: string;
    description?: string;
}>();

// Show the same initial professional level modal on auth pages (e.g., Verify Email)
const page = usePage<{ auth: { user: User | null } }>()
const showLevelModal = ref(false)
const levels = ref<{ key: string; title: string; threshold: number }[]>([])
const selectedLevelKey = ref('')
const loadingLevels = ref(false)
const savingLevel = ref(false)

async function fetchLevels() {
    loadingLevels.value = true
    try {
        const data = await fetchJson('/profile/pro-level.json')
        const list = Array.isArray(data?.levels) ? data.levels : []
        levels.value = list.map((l: any) => ({ key: String(l.key), title: String(l.title), threshold: Number(l.threshold ?? 0) }))
        if (levels.value.length && !selectedLevelKey.value) {
            selectedLevelKey.value = levels.value[0].key
        }
    } catch (e: any) {
        notifyError(e?.message || 'Не вдалося завантажити рівні')
    } finally {
        loadingLevels.value = false
    }
}

async function submitLevel() {
    if (!selectedLevelKey.value) return
    savingLevel.value = true
    try {
        await fetchJson('/profile/pro-level.json', {
            method: 'POST',
            body: JSON.stringify({ level_key: selectedLevelKey.value })
        })
        notifySuccess('Початковий рівень збережено')
        showLevelModal.value = false
        // Сповістити будь-які віджети (наприклад, Dashboard) оновити рівень негайно
        window.dispatchEvent(new CustomEvent('pro-level:updated'))
        router.reload({ only: ['auth'] })
    } catch (e: any) {
        notifyError(e?.message || 'Помилка збереження рівня')
    } finally {
        savingLevel.value = false
    }
}

onMounted(async () => {
    const user = page.props.auth?.user
    if (user && user.pro_level_key === null) {
        await fetchLevels()
        showLevelModal.value = true
    }
})
</script>

<template>
    <BaseAuthLayout :title="title" :description="description">
        <slot />

        <!-- Initial Professional Level Modal (auth pages) -->
        <div v-if="showLevelModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-lg rounded-xl border border-border bg-background p-4 shadow-xl">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-base font-semibold">Оберіть свій стартовий рівень</h3>
                </div>
                <p class="mb-3 text-sm text-muted-foreground">
                    Вкажіть поточний професійний рівень. Прогрес буде рахуватись від його порогу.
                </p>

                <div v-if="loadingLevels" class="text-sm text-muted-foreground">Завантаження…</div>
                <div v-else>
                    <div v-if="levels.length === 0" class="text-sm text-muted-foreground">
                        Немає доступних рівнів. Спробуйте пізніше.
                    </div>
                    <div v-else class="space-y-2">
                        <label v-for="lvl in levels" :key="lvl.key" class="flex cursor-pointer items-center gap-2 rounded-md border p-2 hover:bg-muted">
                            <input type="radio" class="h-4 w-4" :value="lvl.key" v-model="selectedLevelKey" />
                            <div>
                                <div class="text-sm font-medium">{{ lvl.title }}</div>
                                <div class="text-xs text-muted-foreground">Поріг: {{ lvl.threshold }}</div>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <button
                        class="rounded-md bg-primary px-3 py-2 text-xs font-medium text-primary-foreground hover:opacity-90 disabled:opacity-60"
                        :disabled="savingLevel || !selectedLevelKey"
                        @click="submitLevel"
                    >
                        Підтвердити
                    </button>
                </div>
            </div>
        </div>
    </BaseAuthLayout>
</template>
