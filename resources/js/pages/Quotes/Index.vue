<template>
    <Head title="Quotations" />
    <AppLayout :breadcrumbs="[
        { name: 'Quotations', href: route('quotes.index') }
    ]">
        <div class="container mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Quotations</h1>
                <div class="flex gap-2 print:hidden">
                    <Link
                        :href="route('quotes.create')"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition text-center"
                    >
                        Add New Quote
                    </Link>
                </div>
            </div>
            <div v-if="$page.props.flash?.success" class="mb-4">
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ $page.props.flash.success }}</span>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                <div class="p-4">
                    <div class="flex items-center mb-4 gap-2">
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search quotes..."
                            class="w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                            @input="debouncedSearch"
                        >
                        <input
                            v-model="customerSearch"
                            type="text"
                            placeholder="Search by customer name"
                            class="w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                            @input="debouncedSearch"
                        >
                        <input
                            v-model="startDate"
                            type="date"
                            class="px-2 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                            @change="debouncedSearch"
                        >
                        <input
                            v-model="endDate"
                            type="date"
                            class="px-2 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                            @change="debouncedSearch"
                        >
                    </div>
                    <div class="overflow-x-auto print-orders-table">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th v-for="column in columns" :key="column.key" class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600" @click="handleSort(column.key)">
                                        <div class="flex items-center gap-1">
                                            {{ column.label }}
                                            <component :is="getSortIcon(column.key)" class="w-4 h-4" :class="{'text-indigo-600 dark:text-indigo-400': sortColumn === column.key}" />
                                        </div>
                                    </th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider print:hidden">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                                <tr v-for="quote in quotes.data" :key="quote.id">
                                    <td class="px-4 py-2 text-gray-900 dark:text-gray-100">
                                        <Link :href="route('quotes.show', quote.id)" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300">
                                            {{ quote.id }}
                                        </Link>
                                    </td>
                                    <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ quote.customer?.name || '-' }}</td>
                                    <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ quote.total }}</td>
                                    <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ quote.status }}</td>
                                    <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ quote.created_at }}</td>
                                    <td class="px-4 py-2 print:hidden">
                                        <div class="flex gap-2">
                                            <Link :href="route('quotes.show', quote.id)" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300">View</Link>
                                            <Link :href="route('quotes.edit', quote.id)" class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300">Edit</Link>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <Pagination v-if="quotes.links" :links="quotes.links" class="mt-6 print:hidden" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import Pagination from '@/Components/Pagination.vue';
import debounce from 'lodash/debounce';
import { ArrowUpDown, ArrowUp, ArrowDown } from 'lucide-vue-next';

const props = defineProps({
    quotes: Object
});

const search = ref('');
const customerSearch = ref('');
const startDate = ref('');
const endDate = ref('');
const sortColumn = ref('');
const sortDirection = ref('asc');

const columns = computed(() => [
    { key: 'id', label: 'Quote #' },
    { key: 'customer', label: 'Customer' },
    { key: 'total', label: 'Total' },
    { key: 'status', label: 'Status' },
    { key: 'created_at', label: 'Date' }
]);

const debouncedSearch = debounce(() => {
    router.get(
        route('quotes.index'),
        {
            search: search.value,
            customer: customerSearch.value,
            start_date: startDate.value,
            end_date: endDate.value,
            sort_column: sortColumn.value,
            sort_direction: sortDirection.value
        },
        { preserveState: true, preserveScroll: true }
    );
}, 300);

function handleSort(column) {
    if (sortColumn.value === column) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortColumn.value = column;
        sortDirection.value = 'asc';
    }
    debouncedSearch();
}

function getSortIcon(column) {
    if (sortColumn.value !== column) {
        return ArrowUpDown;
    }
    return sortDirection.value === 'asc' ? ArrowUp : ArrowDown;
}
</script> 