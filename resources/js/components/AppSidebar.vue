<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3'
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
import { dashboard } from '@/routes';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { BookOpen, LayoutGrid, List, FileText, Layers, Users } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import { fetchJson } from '@/lib/csrf';
import { usePendingApprovalsCount } from '@/composables/usePendingApprovalsCount';

const isCurator = ref(false);
const page = usePage<{ auth: { user: { super_admin?: boolean } | null } }>()
const isSuperAdmin = computed(() => !!page.props.auth?.user?.super_admin)
const { pendingApprovalsCount, loadPendingApprovalsCount } = usePendingApprovalsCount();

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
            title: 'Annex',
            href: '/pdps?tab=annex',
            icon: FileText,
        },
    ];

    // Add "My Mentees" if user is a curator
    if (isCurator.value) {
        items.push({
            title: 'My Mentees',
            href: '/curator/mentees',
            icon: Users,
            badge: pendingApprovalsCount.value > 0 ? pendingApprovalsCount.value : undefined,
        });
    }

    // Add Admin only for super admins
    if (isSuperAdmin.value) {
        items.push({
            title: 'Admin',
            href: '/admin',
            icon: LayoutGrid,
        })
    }

    // Always add "Skill Templates" last
    items.push({
        title: 'Skill Templates',
        href: '/pdps/templates',
        icon: Layers,
    });

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
        if (Array.isArray(shared) && shared.length > 0) {
            isCurator.value = true;
            // Load pending approvals count for curators
            await loadPendingApprovalsCount();
        }
    } catch {
        // Silently fail - user is not a curator or error occurred
        isCurator.value = false;
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
