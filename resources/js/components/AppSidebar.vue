<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { usePendingApprovalsCount } from '@/composables/usePendingApprovalsCount';
import { fetchJson } from '@/lib/csrf';
import { dashboard } from '@/routes';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    FileText,
    Layers,
    LayoutGrid,
    List,
    Users,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import AppLogo from './AppLogo.vue';

const CURATOR_STATUS_CACHE_KEY = 'pdp:is_curator';

function readCuratorStatusCache(): boolean {
    try {
        return sessionStorage.getItem(CURATOR_STATUS_CACHE_KEY) === '1';
    } catch {
        return false;
    }
}

function writeCuratorStatusCache(value: boolean) {
    try {
        sessionStorage.setItem(CURATOR_STATUS_CACHE_KEY, value ? '1' : '0');
    } catch {
        // Ignore storage errors; runtime state still works.
    }
}

const isCurator = ref(readCuratorStatusCache());
const page = usePage<{ auth: { user: { super_admin?: boolean } | null } }>();
const isSuperAdmin = computed(() => !!page.props.auth?.user?.super_admin);
const { pendingApprovalsCount, loadPendingApprovalsCount } =
    usePendingApprovalsCount();

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: 'PDP List',
            href: '/pdps',
            icon: List,
        },
        {
            title: 'Annex List',
            href: '/pdps?tab=annex',
            icon: FileText,
        },
    ];

    // Core item shown for all users
    items.push({
        title: 'Skill List',
        href: '/pdps/templates',
        icon: Layers,
    });

    // Add "Mentees List" for curators after all core items
    if (isCurator.value) {
        items.push({
            title: 'Mentees List',
            href: '/curator/mentees',
            icon: Users,
            badge:
                pendingApprovalsCount.value > 0
                    ? pendingApprovalsCount.value
                    : undefined,
        });
    }

    // Add Admin only for super admins and keep it last in the list
    if (isSuperAdmin.value) {
        items.push({
            title: 'Admin panel',
            href: '/admin',
            icon: LayoutGrid,
        });
    }

    return items;
});

const footerNavItems: NavItem[] = [
    {
        title: 'Documentation',
        href: 'https://leasoft.atlassian.net/wiki/spaces/LeaOrg/pages/170688519/Personal+Development+Plan+PDP',
        icon: BookOpen,
    },
];

// Check if user is a curator (has shared PDPs)
async function checkCuratorStatus() {
    try {
        const shared = await fetchJson('/pdps.shared.json');
        const nextIsCurator = Array.isArray(shared) && shared.length > 0;
        isCurator.value = nextIsCurator;
        writeCuratorStatusCache(nextIsCurator);

        if (nextIsCurator) {
            // Load pending approvals count for mentors/curators
            await loadPendingApprovalsCount();
        }
    } catch {
        // Keep cached/runtime value to avoid nav flicker on transient failures.
        // If user is already known as curator, try to refresh the badge anyway.
        if (isCurator.value) {
            await loadPendingApprovalsCount().catch(() => undefined);
        }
    }
}

onMounted(() => {
    checkCuratorStatus();
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
