<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
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
    <AppLayout :breadcrumbs="breadcrumbs" :collapse-sidebar="collapseSidebar">
        <slot />
    </AppLayout>
</template>
