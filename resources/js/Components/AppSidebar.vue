<script setup lang="ts">
import NavFooter from '@/Components/NavFooter.vue';
import NavMain from '@/Components/NavMain.vue';
import NavUser from '@/Components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/Components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Settings, LayoutGrid, ShoppingCart, Package, ClipboardList, Tags, Users, Truck, Receipt, BarChart3, FolderTree, Users2 } from 'lucide-vue-next';
import { onMounted, ref, watch } from 'vue';
import AppLogo from './AppLogo.vue';

interface Props {
    collapsed?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    collapsed: false,
});

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

const footerNavItems: NavItem[] = [
    {
        title: 'Shop Settings',
        href: route('pos.settings'),
        icon: Settings,
    },
];
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
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
