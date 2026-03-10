<script setup lang="ts">
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const open = ref(false);
const notifications = ref([]);
const unreadCount = ref(0);
const listRef = ref<HTMLElement | null>(null);
const showFade = ref(false);

const load = async () => {
    const res = await axios.get('/notifications.unread.json');
    notifications.value = res.data;
    unreadCount.value = res.data.filter((n) => n.read === 0).length;
};

onMounted(() => {
    load(); // <-- load count immediately on header load
});

// Listen for global notifications state changes (e.g., when user views PDP criterion)
const onNotificationsChanged = () => {
    // Refresh unread counter silently
    load();
};

onMounted(() => {
    window.addEventListener(
        'notifications:changed',
        onNotificationsChanged as any,
    );
});

onBeforeUnmount(() => {
    window.removeEventListener(
        'notifications:changed',
        onNotificationsChanged as any,
    );
});

const toggle = () => {
    open.value = !open.value;

    if (open.value) {
        load().then(() => {
            requestAnimationFrame(() => updateFade());
        });
    }
};

const markAsRead = async (id: number) => {
    await axios.post(`/notifications/${id}/read`);
    await load();
};

const markAllAsRead = async () => {
    await axios.post('/notifications/read-all');

    // Update UI immediately
    // We fetch only unread notifications for this bell,
    // so after marking all as read the visible list should be empty.
    notifications.value = [];
    unreadCount.value = 0;
    updateFade();
};

const openNotification = async (n) => {
    await markAsRead(n.id);

    if (n.url) {
        window.location.href = n.url;
    }
};
const updateFade = () => {
    const el = listRef.value;
    if (!el) return;

    showFade.value = el.scrollTop + el.clientHeight < el.scrollHeight - 5;
};

const onScroll = () => updateFade();

const hasUnread = computed(() => notifications.value.some((n) => n.read === 0));
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
        <Transition
            enter-active-class="transition ease-out duration-150"
            enter-from-class="opacity-0 -translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-2"
        >
            <div
                v-if="open"
                ref="listRef"
                @scroll="onScroll"
                class="no-scrollbar absolute top-12 right-0 z-50 h-[calc(100vh-5rem)] w-72 overflow-y-auto rounded-2xl bg-white p-1 shadow-lg dark:bg-gray-800"
            >
                <button
                    v-if="hasUnread"
                    @click="markAllAsRead"
                    class="group relative mt-3 mb-2 ml-auto flex items-center gap-2 overflow-hidden rounded-xl border border-gray-200/60 bg-white/60 px-4 py-1.5 text-sm font-medium text-gray-600 shadow-sm backdrop-blur-md transition-all duration-300 ease-out hover:scale-[1.03] hover:border-blue-300/70 hover:bg-blue-50/40 hover:text-blue-700 hover:shadow-md active:scale-[0.98] dark:border-gray-600/40 dark:bg-gray-700/40 dark:text-gray-300 dark:hover:border-blue-500/40 dark:hover:bg-blue-900/20 dark:hover:text-blue-300"
                >
                    <!-- Radial highlight -->
                    <span
                        class="pointer-events-none absolute inset-0 opacity-0 transition-opacity duration-500 group-hover:opacity-70"
                        style="
                            background: radial-gradient(
                                circle at center,
                                rgba(80, 138, 255, 0.35),
                                transparent 70%
                            );
                        "
                    ></span>

                    <!-- Glimmer sweep -->
                    <span
                        class="pointer-events-none absolute top-0 left-[-40%] h-full w-1/3 -skew-x-12 transform bg-white/30 opacity-0 transition-all duration-700 ease-out group-hover:translate-x-[220%] group-hover:opacity-40 dark:bg-white/10"
                    ></span>

                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                        class="relative h-4 w-4 transition-all duration-300 group-hover:scale-125 group-hover:text-blue-600 dark:group-hover:text-blue-300"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M4.5 12.75l6 6 9-13.5"
                        />
                    </svg>

                    <span class="relative">Mark all as read</span>
                </button>
                <p
                    v-if="notifications.length === 0"
                    class="p-4 text-center text-gray-500"
                >
                    No new notifications
                </p>

                <div
                    v-for="n in notifications"
                    :key="n.id"
                    @click="openNotification(n)"
                    :class="[
                        'group mb-3 cursor-pointer rounded-2xl border p-4 backdrop-blur-xl transition-all duration-300',
                        'shadow-[0_4px_22px_-6px_rgba(0,0,0,0.10)] hover:-translate-y-0.5 hover:shadow-[0_6px_30px_-4px_rgba(0,0,0,0.18)] dark:shadow-[0_4px_22px_-6px_rgba(0,0,0,0.35)] dark:hover:shadow-[0_6px_30px_-4px_rgba(0,0,0,0.5)]',
                        n.type === 'success' &&
                            'border-green-200/50 bg-green-50/70 hover:bg-green-50/90 dark:border-green-900/45 dark:bg-green-950/35 dark:hover:bg-green-900/40',
                        n.type === 'comment' &&
                            'border-blue-200/50 bg-blue-50/70 hover:bg-blue-50/90 dark:border-blue-900/45 dark:bg-blue-950/35 dark:hover:bg-blue-900/40',
                        n.type === 'warning' &&
                            'border-yellow-200/50 bg-yellow-50/70 hover:bg-yellow-50/90 dark:border-yellow-900/45 dark:bg-yellow-950/35 dark:hover:bg-yellow-900/40',
                        n.type === 'info' &&
                            'border-gray-200/50 bg-gray-50/70 hover:bg-gray-50/90 dark:border-gray-700/60 dark:bg-gray-900/40 dark:hover:bg-gray-800/60',
                    ]"
                >
                    <div class="flex items-start gap-3">
                        <div class="mt-1 shrink-0">
                            <div
                                v-if="n.type === 'success'"
                                class="flex h-8 w-8 items-center justify-center rounded-xl bg-green-100 text-lg text-green-600 shadow-inner dark:bg-green-900/45 dark:text-green-300"
                            >
                                ✓
                            </div>

                            <div
                                v-else-if="n.type === 'comment'"
                                class="flex h-8 w-8 items-center justify-center rounded-xl bg-blue-100 text-lg text-blue-600 shadow-inner dark:bg-blue-900/45 dark:text-blue-300"
                            >
                                💬
                            </div>

                            <div
                                v-else-if="n.type === 'warning'"
                                class="flex h-8 w-8 items-center justify-center rounded-xl bg-yellow-100 text-lg text-yellow-700 shadow-inner dark:bg-yellow-900/45 dark:text-yellow-300"
                            >
                                ⚠️
                            </div>

                            <div
                                v-else
                                class="flex h-8 w-8 items-center justify-center rounded-xl bg-gray-200 text-lg text-gray-700 shadow-inner dark:bg-gray-700 dark:text-gray-300"
                            >
                                !
                            </div>
                        </div>

                        <div class="flex-1">
                            <h3
                                class="text-[15px] leading-tight font-semibold text-gray-900 dark:text-gray-100"
                            >
                                {{ n.title }}
                            </h3>

                            <p
                                class="mt-1 text-[13px] leading-relaxed text-gray-700 dark:text-gray-300"
                            >
                                {{ n.message }}
                            </p>

                            <p
                                class="mt-2 text-[11px] text-gray-400 dark:text-gray-500"
                            >
                                {{ new Date(n.created_at).toLocaleString() }}
                            </p>
                        </div>
                    </div>
                </div>
                <!-- Bottom fade overlay -->
                <div
                    v-show="showFade"
                    class="pointer-events-none absolute right-0 bottom-0 left-0 h-16 bg-gradient-to-t from-white to-transparent transition-opacity duration-300 dark:from-gray-800"
                ></div>
            </div>
        </Transition>
    </div>
</template>
