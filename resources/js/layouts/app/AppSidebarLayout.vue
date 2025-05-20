<script setup lang="ts">
import AppContent from '@/Components/AppContent.vue';
import AppShell from '@/Components/AppShell.vue';
import AppSidebar from '@/Components/AppSidebar.vue';
import AppSidebarHeader from '@/Components/AppSidebarHeader.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
    collapseSidebar?: boolean;
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
    collapseSidebar: false,
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar :collapsed="collapseSidebar" />
        <AppContent variant="sidebar">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
    </AppShell>
</template>
