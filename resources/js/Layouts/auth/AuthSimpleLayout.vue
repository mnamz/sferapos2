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

const year = new Date().getFullYear();

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <!-- Always light (force-light), whatever the device's theme — same look as HQ's sign-in. -->
    <div class="force-light relative flex min-h-svh flex-col items-center justify-center overflow-hidden bg-gray-50 px-4 py-10 text-foreground">
        <!-- Soft gold glow behind the logo. -->
        <div
            class="pointer-events-none absolute -top-64 left-1/2 h-[32rem] w-[32rem] -translate-x-1/2 rounded-full bg-amber-100/50 blur-3xl"
            aria-hidden="true"
        />

        <div class="relative w-full max-w-sm">
            <div class="flex flex-col items-center text-center">
                <!-- multiply drops a logo's white background into the page colour. -->
                <img v-if="logoPath" :src="logoPath" :alt="shopName" class="h-24 w-auto max-w-[16rem] object-contain mix-blend-multiply" />
                <AppLogoIcon v-else class="size-16 fill-current text-gray-900" />
                <h1 class="mt-5 text-xl font-semibold tracking-tight text-gray-900">{{ shopName }}</h1>
            </div>

            <div class="mt-8 rounded-2xl bg-white p-6 shadow-xl shadow-gray-200/70 ring-1 ring-gray-200 sm:p-8">
                <h2 v-if="title" class="text-lg font-semibold text-gray-900">{{ title }}</h2>
                <p v-if="description" class="mt-1 text-sm text-gray-500">{{ description }}</p>
                <div :class="title || description ? 'mt-6' : ''">
                    <slot />
                </div>
            </div>

            <p class="mt-8 text-center text-xs text-gray-400">© {{ year }} {{ shopName }}</p>
        </div>
    </div>
</template>
