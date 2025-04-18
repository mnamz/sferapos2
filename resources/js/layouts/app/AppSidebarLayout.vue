<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
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
