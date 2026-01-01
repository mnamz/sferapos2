<script setup lang="ts">
import AppLayout from '@/Layouts/app/AppSidebarLayout.vue';
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
        <template v-if="$slots.header" #header>
            <slot name="header" />
        </template>
        <slot />
    </AppLayout>
</template>
