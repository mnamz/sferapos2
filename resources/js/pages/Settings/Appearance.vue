<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import AppearanceTabs from '@/Components/AppearanceTabs.vue';
import HeadingSmall from '@/Components/HeadingSmall.vue';
import { type BreadcrumbItem } from '@/types';

import AppLayout from '@/Layouts/AppLayout.vue';
import SettingsLayout from '@/Layouts/settings/Layout.vue';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Appearance settings',
        href: '/settings/appearance',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Appearance settings" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall title="Appearance settings" description="Update your account's appearance settings" />
                <AppearanceTabs />
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
