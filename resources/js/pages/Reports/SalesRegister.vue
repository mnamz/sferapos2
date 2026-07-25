<template>
    <Head title="Sales Register" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                    Sales Register by Product Type
                </h1>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    groups: Array,
    grandTotal: Object,
    filterOptions: Object,
    filters: Object,
});

const breadcrumbs = [
    { title: 'Sales Register', href: route('reports.sales-register') },
];
</script>
