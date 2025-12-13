import { ref } from 'vue';
import { fetchJson } from '@/lib/csrf';

const pendingApprovalsCount = ref(0);
const isLoading = ref(false);

export function usePendingApprovalsCount() {
    async function loadPendingApprovalsCount() {
        try {
            isLoading.value = true;
            const response = await fetchJson('/curator/mentees/pending-approvals/count.json');
            pendingApprovalsCount.value = response?.total || 0;
        } catch (error) {
            console.error('Failed to load pending approvals count:', error);
            pendingApprovalsCount.value = 0;
        } finally {
            isLoading.value = false;
        }
    }

    async function refreshPendingApprovalsCount() {
        await loadPendingApprovalsCount();
    }

    return {
        pendingApprovalsCount,
        isLoading,
        loadPendingApprovalsCount,
        refreshPendingApprovalsCount,
    };
}
