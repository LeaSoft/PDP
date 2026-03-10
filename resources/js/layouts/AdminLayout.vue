<script setup lang="ts">
import Toaster from '@/components/ui/Toaster.vue';
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItemType } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

type AdminTab = {
    label: string;
    href: string;
};

const tabs: AdminTab[] = [
    { label: 'Overview', href: '/admin' },
    { label: 'Users', href: '/admin/users' },
    { label: 'Entries', href: '/admin/entries' },
    { label: 'Mentors', href: '/admin/curators' },
    { label: 'PDPs', href: '/admin/pdps' },
];

const page = usePage();
const currentPath = computed(() => {
    const raw = String(page.url || '/admin');
    return raw.split('?')[0] || '/admin';
});

function isTabActive(href: string): boolean {
    if (href === '/admin') return currentPath.value === '/admin';
    return currentPath.value.startsWith(href);
}
</script>

<template>
    <!-- Reuse the unified sidebar layout to avoid duplicate shells/overlapping -->
    <AppSidebarLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-4 p-6">
            <nav
                class="inline-flex w-full flex-wrap gap-2 rounded-lg border border-border/70 bg-muted/20 p-1 dark:border-border/60 dark:bg-muted/10"
            >
                <Link
                    v-for="tab in tabs"
                    :key="tab.href"
                    :href="tab.href"
                    class="rounded-md px-3 py-1.5 text-sm transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                    :class="
                        isTabActive(tab.href)
                            ? 'bg-primary text-primary-foreground hover:bg-primary/95 dark:hover:bg-primary/95'
                            : 'text-muted-foreground hover:bg-muted hover:text-foreground dark:hover:bg-muted/80'
                    "
                    :aria-current="isTabActive(tab.href) ? 'page' : undefined"
                >
                    {{ tab.label }}
                </Link>
            </nav>
            <slot />
        </div>
        <Toaster />
    </AppSidebarLayout>
</template>
