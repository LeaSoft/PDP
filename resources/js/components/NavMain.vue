<script setup lang="ts">
import {
    SidebarGroup,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuBadge,
} from '@/components/ui/sidebar';
import { urlIsActive } from '@/lib/utils';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';

defineProps<{
    items: NavItem[];
}>();

const page = usePage();
</script>

<template>
    <SidebarGroup>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in items" :key="item.title" class="relative">
                <SidebarMenuButton
                    as-child
                    :is-active="urlIsActive(item.href, page.url)"
                    :tooltip="item.title"
                >
                    <Link :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>

                <!-- Badge over icon (only collapsed sidebar) -->
                <span
                    v-if="item.badge && item.badge > 0"
                    class="pointer-events-none absolute right-0 top-0 z-10 hidden -translate-y-1/4 translate-x-1/4 items-center justify-center rounded-full bg-orange-500 px-1 text-[10px] font-bold leading-tight text-white group-data-[collapsible=icon]:flex"
                    style="min-width: 1.125rem; height: 1.125rem;"
                >
                    {{ item.badge > 9 ? '9+' : item.badge }}
                </span>

                <!-- Badge for expanded state -->
                <SidebarMenuBadge
                    v-if="item.badge && item.badge > 0"
                    class="bg-orange-500 text-white"
                >
                    {{ item.badge > 9 ? '9+' : item.badge }}
                </SidebarMenuBadge>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
