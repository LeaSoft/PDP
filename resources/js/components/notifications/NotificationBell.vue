<script setup lang="ts">
import { ref, onMounted } from 'vue';
import axios from 'axios';

const open = ref(false);
const notifications = ref([]);
const unreadCount = ref(0);

const load = async () => {
    const res = await axios.get('/notifications.unread.json');
    notifications.value = res.data;
    unreadCount.value = res.data.length;
};

onMounted(() => {
    load(); // <-- load count immediately on header load
});

const toggle = () => {
    open.value = !open.value;
    if (open.value) load(); // reload when opening dropdown
};

const markAsRead = async (id: number) => {
    await axios.post(`/notifications/${id}/read`);
    await load();
};

const openNotification = async (n) => {
    await markAsRead(n.id);

    if (n.url) {
        window.location.href = n.url;
    }
};
</script>

<template>
    <div class="relative">
        <!-- 🛎️ Bell Icon -->
        <button
            @click="toggle"
            class="relative rounded-md p-2 hover:bg-gray-200 dark:hover:bg-gray-700"
        >
            <!-- Heroicon Bell -->
            <svg
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
                class="h-6 w-6 text-gray-700 dark:text-gray-300"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M14.857 17.657c-.628.33-1.346.513-2.107.513-1.645 0-3.067-.943-3.685-2.302M18 8a6 6 0 10-12 0c0 7-3 9-3 9h18s-3-2-3-9z"
                />
            </svg>

            <!-- Badge -->
            <span
                v-if="unreadCount > 0"
                class="absolute -top-1 -right-1 rounded-full bg-red-500 px-1 text-xs text-white"
            >
                {{ unreadCount }}
            </span>
        </button>

        <!-- Dropdown -->
        <div
            v-if="open"
            class="absolute right-0 z-50 mt-2 max-h-80 w-72 overflow-y-auto rounded bg-white shadow-lg dark:bg-gray-800"
        >
            <p
                v-if="notifications.length === 0"
                class="p-4 text-center text-gray-500"
            >
                No new notifications
            </p>

            <div
                v-for="n in notifications"
                :key="n.id"
                class="mb-2 cursor-pointer rounded-2xl border border-white/30 bg-white/70 p-4 shadow-lg backdrop-blur-md transition hover:bg-white/90 dark:border-gray-700/30 dark:bg-gray-800/40 dark:hover:bg-gray-800/60"
                @click="openNotification(n)"
            >
                <h3 class="font-semibold text-gray-900 dark:text-gray-100">
                    {{ n.title }}
                </h3>
                <p
                    class="mt-1 text-sm leading-relaxed text-gray-700 dark:text-gray-300"
                >
                    {{ n.message }}
                </p>
            </div>
        </div>
    </div>
</template>
