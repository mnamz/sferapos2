<script setup lang="ts">
import NavFooter from '@/Components/NavFooter.vue';
import NavMain from '@/Components/NavMain.vue';
import NavUser from '@/Components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/Components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Settings, LayoutGrid, ShoppingCart, Package, ClipboardList, Tags, Users, Truck, Receipt, BarChart3, FolderTree, Users2 } from 'lucide-vue-next';
import { onMounted, ref, watch, computed } from 'vue';
import AppLogo from './AppLogo.vue';
import { usePage } from '@inertiajs/vue3';
import type { PageProps as InertiaPageProps } from '@inertiajs/core';

interface Props {
    collapsed?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    collapsed: false,
});

interface User {
    role: string;
    roles?: string[];
}

interface PageProps extends InertiaPageProps {
    auth: {
        user: User;
        roles?: string[];
    };
}

const page = usePage<PageProps>();
const roles = page.props.auth?.roles || [];

const isCollapsed = ref(props.collapsed);

onMounted(() => {
    isCollapsed.value = props.collapsed;
});

// Watch for changes in the collapsed prop
watch(() => props.collapsed, (newValue) => {
    isCollapsed.value = newValue;
});

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: route('dashboard'),
        icon: LayoutGrid,
    },
    {
        title: 'POS',
        href: route('pos.index'),
        icon: ShoppingCart,
    },
    {
        title: 'Create Order',
        href: route('orders.create'),
        icon: ClipboardList,
    },
    {
        title: 'Orders',
        href: route('orders.index'),
        icon: Receipt,
    },
    {
        title: 'Reports',
        href: route('reports.index'),
        icon: BarChart3,
    },
    {
        title: 'Products',
        href: route('products.index'),
        icon: Package,
    },
    {
        title: 'Categories',
        href: route('categories.index'),
        icon: FolderTree,
    },
    {
        title: 'Customers',
        href: route('customers.index'),
        icon: Users,
    },
    {
        title: 'Suppliers',
        href: route('suppliers.index'),
        icon: Truck,
    },
    {
        title: 'Users',
        href: route('users.index'),
        icon: Users2,
    },
];

const filteredNavItems = computed(() => {
    return mainNavItems.filter(item => {
        if (item.title === 'Reports' && roles.includes('staff')) {
            return false;
        }
        if (item.title === 'Suppliers' && roles.includes('staff')) {
            return false;
        }
        if (item.title === 'Users' && roles.includes('staff')) {
            return false;
        }
        if (item.title === 'Categories' && roles.includes('staff')) {
            return false;
        }
        if (item.title === 'Shop Settings' && roles.includes('staff')) {
            return false;
        }
        return true;
    });
});

const footerNavItems: NavItem[] = [
    {
        title: 'Shop Settings',
        href: route('pos.settings'),
        icon: Settings,
        show: () => page.props.auth.user?.role === 'admin'
    },
].filter(item => item.show ? item.show() : true);
</script>

<template>
    <Sidebar collapsible="icon" :defaultCollapsed="isCollapsed" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="filteredNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
