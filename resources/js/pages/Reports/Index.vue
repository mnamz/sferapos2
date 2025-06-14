<template>
    <Head title="Reports" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Date Range Filter -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6 p-4">
                    <div class="flex flex-col sm:flex-row gap-4 items-end">
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Start Date
                            </label>
                            <input 
                                type="date" 
                                v-model="filters.start_date"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-3 py-2"
                            >
                        </div>
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                End Date
                            </label>
                            <input 
                                type="date" 
                                v-model="filters.end_date"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-3 py-2"
                            >
                        </div>
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Delivery Method
                            </label>
                            <select 
                                v-model="filters.delivery_method"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-3 py-2"
                            >
                                <option value="">All Methods</option>
                                <option value="in-store">In-Store (Walk-in & Delivery)</option>
                                <option value="shopee">Shopee</option>
                                <option value="lazada">Lazada</option>
                                <option value="tiktok">TikTok</option>
                            </select>
                        </div>
                        <div class="flex gap-2">
                            <button 
                                @click="applyFilters"
                                class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                            >
                                Apply Filters
                            </button>
                            <a
                                :href="route('reports.export', { 
                                    start_date: filters.start_date, 
                                    end_date: filters.end_date,
                                    delivery_method: filters.delivery_method 
                                })"
                                class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 flex items-center gap-2"
                                target="_blank"
                            >
                                <FileSpreadsheet class="w-4 h-4" />
                                Export Excel
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Total Sales</h3>
                            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ currency }}{{ formatNumber(summary.total_sales) }}</p>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Total Orders</h3>
                            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ summary.total_orders }}</p>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Total Tax</h3>
                            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ currency }}{{ formatNumber(summary.total_tax) }}</p>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Total Profit</h3>
                            <p class="text-3xl font-bold text-green-600 dark:text-green-400">{{ currency }}{{ formatNumber(summary.total_profit) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Orders Table -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Orders</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th 
                                            v-for="column in [
                                                { key: 'id', label: 'Order #' },
                                                { key: 'customer_name', label: 'Customer' },
                                                { key: 'subtotal', label: 'Subtotal' },
                                                { key: 'tax', label: 'Tax' },
                                                { key: 'total', label: 'Total' },
                                                { key: 'profit', label: 'Profit' },
                                                { key: 'due', label: 'Due' },
                                                { key: 'status', label: 'Status' },
                                                { key: 'payment_status', label: 'Payment' },
                                                { key: 'cashier_name', label: 'Cashier' },
                                                { key: 'created_at', label: 'Date' }
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
                                    <tr v-for="order in orders.data" :key="order.id">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <Link :href="route('orders.show', order.id)" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300">
                                                #{{ order.id }}
                                            </Link>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ order.customer_name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ currency }}{{ order.subtotal }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ currency }}{{ order.tax }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ currency }}{{ order.total }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-green-600 dark:text-green-400">{{ currency }}{{ order.profit }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-red-600 dark:text-red-400">{{ currency }}{{ order.due }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span :class="{
                                                'px-2 py-1 text-xs rounded-full': true,
                                                'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': order.status === 'completed',
                                                'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200': order.status === 'processing',
                                                'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': order.status === 'pending',
                                                'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': order.status === 'cancelled'
                                            }">
                                                {{ order.status }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span :class="{
                                                'px-2 py-1 text-xs rounded-full': true,
                                                'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': order.payment_status === 'paid',
                                                'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': order.payment_status === 'partial',
                                                'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': order.payment_status === 'pending'
                                            }">
                                                {{ order.payment_status }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ order.cashier_name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ order.created_at }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <Pagination :links="orders.links" class="mt-6" />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import Chart from 'chart.js/auto';
import Pagination from '@/Components/Pagination.vue';
import { FileSpreadsheet, ArrowUpDown, ArrowUp, ArrowDown } from 'lucide-vue-next';

const props = defineProps({
    summary: Object,
    dailySales: Array,
    orders: Object,
    filters: Object,
    profitDetails: Array,
});

const page = usePage();
const currency = computed(() => page.props.settings?.currency || 'USD');
const salesChart = ref(null);
const salesChartInstance = ref(null);

const sortColumn = ref('');
const sortDirection = ref('asc');

// Initialize filters with current month
const filters = ref({
    start_date: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0],
    end_date: new Date().toISOString().split('T')[0],
    delivery_method: '',
    ...props.filters
});

const breadcrumbs = [
    {
        title: 'Reports',
        href: route('reports.index'),
    },
];

function formatNumber(number) {
    return parseFloat(number || 0).toFixed(2);
}

function applyFilters(resetPage = true) {
    router.get(route('reports.index'), {
        start_date: filters.value.start_date,
        end_date: filters.value.end_date,
        delivery_method: filters.value.delivery_method,
        sort_column: sortColumn.value,
        sort_direction: sortDirection.value,
        page: resetPage ? 1 : undefined // Only reset page when explicitly applying filters
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
    applyFilters(true); // Reset to page 1 when sorting
}

function getSortIcon(column) {
    if (sortColumn.value !== column) {
        return ArrowUpDown;
    }
    return sortDirection.value === 'asc' ? ArrowUp : ArrowDown;
}

onMounted(() => {
    applyFilters();
    
    const ctx = salesChart.value.getContext('2d');
    
    if (salesChartInstance.value) {
        salesChartInstance.value.destroy();
    }

    salesChartInstance.value = new Chart(ctx, {
        type: 'line',
        data: {
            labels: props.dailySales.map(item => item.date),
            datasets: [
                {
                    label: 'Sales',
                    data: props.dailySales.map(item => item.total_sales),
                    borderColor: '#4F46E5',
                    tension: 0.4,
                    fill: false
                },
                {
                    label: 'Orders',
                    data: props.dailySales.map(item => item.orders),
                    borderColor: '#10B981',
                    tension: 0.4,
                    fill: false
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'top'
                }
            }
        }
    });
});
</script> 