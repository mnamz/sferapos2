<script setup lang="ts">
import AppLogoIcon from '@/Components/AppLogoIcon.vue';
import { Link, usePage } from '@inertiajs/vue3';
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

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <Link :href="route('home')" class="flex flex-col items-center gap-2 font-medium">
                        <div class="mb-1 flex h-16 w-16 items-center justify-center overflow-hidden rounded-md">
                            <img v-if="logoPath" :src="logoPath" :alt="shopName" class="h-full w-full object-contain" />
                            <AppLogoIcon v-else class="size-16 fill-current text-[var(--foreground)] dark:text-white" />
                        </div>
                        <span class="text-lg font-semibold">{{ shopName }}</span>
                    </Link>
                    <div class="space-y-2 text-center">
                        <h1 class="text-xl font-medium">{{ title }}</h1>
                        <p class="text-center text-sm text-muted-foreground">{{ description }}</p>
                    </div>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
