<template>
    <Head title="Consolidated Invoices" />

    <AppLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Consolidated Invoices (MyInvois)
                </h2>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Search Bar -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <form @submit.prevent="search" class="flex gap-4">
                            <div class="flex-1">
                                <input
                                    type="text"
                                    v-model="form.search"
                                    placeholder="Search by invoice code, submission UID, UUID, or order number..."
                                    class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 shadow-sm py-2 px-3"
                                >
                            </div>
                            <button
                                type="submit"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
                            >
                                Search
                            </button>
                            <button
                                v-if="form.search"
                                type="button"
                                @click="clearSearch"
                                class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600"
                            >
                                Clear
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                <div class="text-sm text-gray-600 dark:text-gray-400">Total Invoices</div>
                                <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ invoices.total }}</div>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                <div class="text-sm text-gray-600 dark:text-gray-400">Showing</div>
                                <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ invoices.from }} - {{ invoices.to }}</div>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                <div class="text-sm text-gray-600 dark:text-gray-400">Total Amount</div>
                                <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                                    {{ currency }}{{ totalAmount }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Invoices Table -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Order</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Customer</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Invoice Code</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Submission UID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Pushed At</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                                <tr v-for="invoice in invoices.data" :key="invoice.id">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                        <Link :href="route('orders.show', invoice.order_id)" class="text-indigo-600 hover:text-indigo-900">
                                            #{{ invoice.order_number || invoice.order_id }}
                                        </Link>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                        <div>{{ invoice.customer_name }}</div>
                                        <div v-if="invoice.customer_email" class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ invoice.customer_email }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                        <span v-if="invoice.invoice_code_number" class="font-mono text-xs">
                                            {{ invoice.invoice_code_number }}
                                        </span>
                                        <span v-else class="text-gray-400">-</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                        <span v-if="invoice.submission_uid" class="font-mono text-xs">
                                            {{ invoice.submission_uid.substring(0, 20) }}...
                                        </span>
                                        <span v-else class="text-gray-400">-</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                        {{ currency }}{{ invoice.order_total }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                        {{ invoice.pushed_at }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <Link
                                            :href="route('myinvois.consolidated.show', invoice.id)"
                                            class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400"
                                        >
                                            View Details
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="bg-white dark:bg-gray-800 px-4 py-3 border-t border-gray-200 dark:border-gray-700 sm:px-6">
                        <div class="flex items-center justify-between">
                            <div class="flex-1 flex justify-between sm:hidden">
                                <Link
                                    v-if="invoices.prev_page_url"
                                    :href="invoices.prev_page_url"
                                    class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                                >
                                    Previous
                                </Link>
                                <Link
                                    v-if="invoices.next_page_url"
                                    :href="invoices.next_page_url"
                                    class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                                >
                                    Next
                                </Link>
                            </div>
                            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm text-gray-700 dark:text-gray-300">
                                        Showing <span class="font-medium">{{ invoices.from }}</span> to
                                        <span class="font-medium">{{ invoices.to }}</span> of
                                        <span class="font-medium">{{ invoices.total }}</span> results
                                    </p>
                                </div>
                                <div>
                                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                        <Link
                                            v-if="invoices.prev_page_url"
                                            :href="invoices.prev_page_url"
                                            class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700"
                                        >
                                            Previous
                                        </Link>
                                        <Link
                                            v-if="invoices.next_page_url"
                                            :href="invoices.next_page_url"
                                            class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700"
                                        >
                                            Next
                                        </Link>
                                    </nav>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    invoices: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();
const currency = computed(() => page.props.settings?.currency || 'MYR');

const form = ref({
    search: props.filters.search || '',
});

const totalAmount = computed(() => {
    return props.invoices.data.reduce((sum, invoice) => {
        return sum + parseFloat(invoice.order_total.replace(/,/g, ''));
    }, 0).toFixed(2);
});

const search = () => {
    router.get(route('myinvois.consolidated.index'), form.value, {
        preserveState: true,
        replace: true,
    });
};

const clearSearch = () => {
    form.value.search = '';
    router.get(route('myinvois.consolidated.index'), {}, {
        preserveState: true,
        replace: true,
    });
};
</script>

