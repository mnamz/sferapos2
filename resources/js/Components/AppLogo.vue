<script setup lang="ts">
import AppLogoIcon from '@/Components/AppLogoIcon.vue';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { PageProps as InertiaPageProps } from '@inertiajs/core';

interface Settings {
    shop_name?: string;
    logo_path?: string;
}

interface PageProps extends InertiaPageProps {
    settings?: Settings;
}

const page = usePage<PageProps>();
const shopName = computed(() => {
    const settings = page.props.settings;
    return settings?.shop_name || 'POS System';
});

const logoPath = computed(() => {
    const settings = page.props.settings;
    return settings?.logo_path ? `/storage/${settings.logo_path}` : null;
});
</script>

<template>
    <div class="flex items-center gap-2">
        <div v-if="logoPath" class="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-md">
            <img :src="logoPath" :alt="shopName" class="h-full w-full object-contain" />
        </div>
        <div v-else class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
            <AppLogoIcon class="size-5 fill-current text-white dark:text-black" />
        </div>
        <div class="ml-1 grid flex-1 text-left text-sm">
            <span class="mb-0.5 truncate font-semibold leading-none">{{ shopName }}</span>
        </div>
    </div>
</template>
