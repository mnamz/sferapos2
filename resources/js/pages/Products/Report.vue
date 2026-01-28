<template>
    <Head title="Product Sales Report" />
 
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Date Range Filter -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6 p-4">
                    <div class="flex flex-col gap-4">
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Search Products
                            </label>
                            <input 
                                type="text" 
                                v-model="filters.search"
                                @keyup.enter="applyFilters"
                                placeholder="Search by product name or category..."
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-3 py-2 dark:text-white"
                            >
                        </div>
                        <div class="flex flex-col sm:flex-row gap-4 items-end">
                            <div class="flex-1">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Start Date
                                </label>
                                <input 
                                    type="date" 
                                    v-model="filters.start_date"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-3 py-2 dark:text-white"
                                >
                            </div>
                            <div class="flex-1">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    End Date
                                </label>
                                <input 
                                    type="date" 
                                    v-model="filters.end_date"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-3 py-2 dark:text-white"
                                >
                            </div>
                            <div class="flex gap-2">
                                <button 
                                    @click="applyFilters"
                                    class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                >
                                    Apply Filters
                                </button>
                                <a
                                    :href="route('products.report.export', { 
                                        start_date: filters.start_date, 
                                        end_date: filters.end_date,
                                        search: filters.search
                                    })"
                                    class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 flex items-center gap-2"
                                    target="_blank"
                                >
                                    <FileSpreadsheet class="w-4 h-4" />
                                    Export CSV
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Total Products Sold</h3>
                            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ summary.total_products || 0 }}</p>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Total Quantity</h3>
                            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ formatNumber(summary.total_quantity || 0, 0) }}</p>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Total Revenue</h3>
                            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ currency }}{{ formatNumber(summary.total_revenue || 0) }}</p>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Total Profit</h3>
                            <p class="text-3xl font-bold text-green-600 dark:text-green-400">{{ currency }}{{ formatNumber(summary.total_profit || 0) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Products Table -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Products Sold</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th 
                                            v-for="column in [
                                                { key: 'product_name', label: 'Product Name' },
                                                { key: 'category_name', label: 'Category' },
                                                { key: 'total_quantity', label: 'Qty Sold' },
                                                { key: 'avg_price', label: 'Avg Price' },
                                                { key: 'avg_cost_price', label: 'Avg Cost' },
                                                { key: 'total_revenue', label: 'Revenue' },
                                                { key: 'total_cost', label: 'Cost' },
                                                { key: 'total_profit', label: 'Profit' }
                                            ]" 
                                            :key="column.key"
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600"
                                            @click="handleSort(column.key)"
                                        >
                                            <div class="flex items-center gap-1">
                                                {{ column.label }}
                                                <component 
                                                    :is="getSortIcon(column.key)" 
                                                    class="w-4 h-4"
                                                    :class="{
                                                        'text-indigo-600 dark:text-indigo-400': sortColumn === column.key
                                                    }"
                                                />
                                            </div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-for="product in products.data" :key="product.product_id || product.product_name">
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ product.product_name }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-500 dark:text-gray-300">{{ product.category_name || 'N/A' }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900 dark:text-white">{{ formatNumber(product.total_quantity, 0) }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900 dark:text-white">{{ currency }}{{ formatNumber(product.avg_price) }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900 dark:text-white">{{ currency }}{{ formatNumber(product.avg_cost_price) }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-semibold text-blue-600 dark:text-blue-400">{{ currency }}{{ formatNumber(product.total_revenue) }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900 dark:text-white">{{ currency }}{{ formatNumber(product.total_cost) }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-semibold text-green-600 dark:text-green-400">{{ currency }}{{ formatNumber(product.total_profit) }}</div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <Pagination :links="products.links" class="mt-6" />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Pagination from '@/Components/Pagination.vue';
import { FileSpreadsheet, ArrowUpDown, ArrowUp, ArrowDown } from 'lucide-vue-next';

const props = defineProps({
    summary: Object,
    products: Object,
    filters: Object,
});

const page = usePage();
const currency = computed(() => page.props.settings?.currency || 'RM');

// Initialize sort parameters from URL
const sortColumn = ref(props.filters?.sort_column || 'total_revenue');
const sortDirection = ref(props.filters?.sort_direction || 'desc');

// Initialize filters with current month
const filters = ref({
    start_date: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0],
    end_date: new Date().toISOString().split('T')[0],
    search: '',
    ...props.filters
});

const breadcrumbs = [
    {
        title: 'Products',
        href: route('products.index'),
    },
    {
        title: 'Product Sales Report',
        href: route('products.report'),
    },
];

function formatNumber(number, decimals = 2) {
    return parseFloat(number || 0).toLocaleString('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    });
}

function applyFilters(resetPage = true) {
    router.get(route('products.report'), {
        start_date: filters.value.start_date,
        end_date: filters.value.end_date,
        search: filters.value.search || undefined,
        sort_column: sortColumn.value,
        sort_direction: sortDirection.value,
        page: resetPage ? 1 : undefined
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true
    });
}

function handleSort(column) {
    if (sortColumn.value === column) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortColumn.value = column;
        sortDirection.value = 'asc';
    }
    applyFilters(true);
}

function getSortIcon(column) {
    if (sortColumn.value !== column) {
        return ArrowUpDown;
    }
    return sortDirection.value === 'asc' ? ArrowUp : ArrowDown;
}
</script>
