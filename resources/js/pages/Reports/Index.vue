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
                        <div class="flex gap-2">
                            <button 
                                @click="applyFilters"
                                class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                            >
                                Apply Filters
                            </button>
                            <a
                                :href="route('reports.export', { start_date: filters.start_date, end_date: filters.end_date })"
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
                            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Average Order Value</h3>
                            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ currency }}{{ formatNumber(summary.average_order_value) }}</p>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Total Tax</h3>
                            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ currency }}{{ formatNumber(summary.total_tax) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Sales Chart -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Daily Sales</h3>
                        <div class="h-64">
                            <canvas ref="salesChart"></canvas>
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
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Order #</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Customer</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Tax</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Payment</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Cashier</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-for="order in orders.data" :key="order.id">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <Link 
                                                :href="route('orders.show', order.id)"
                                                class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300"
                                            >
                                                #{{ order.order_number }}
                                            </Link>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ order.customer_name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ currency }}{{ order.total }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ currency }}{{ order.tax }}</td>
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
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import Chart from 'chart.js/auto';
import Pagination from '@/components/Pagination.vue';
import { FileSpreadsheet } from 'lucide-vue-next';

const props = defineProps({
    summary: Object,
    dailySales: Array,
    orders: Object,
    filters: Object,
});

const page = usePage();
const currency = computed(() => page.props.settings?.currency || 'USD');
const salesChart = ref(null);
const salesChartInstance = ref(null);

const breadcrumbs = [
    {
        title: 'Reports',
        href: route('reports.index'),
    },
];

function formatNumber(number) {
    return parseFloat(number || 0).toFixed(2);
}

function applyFilters() {
    router.get(route('reports.index'), {
        start_date: props.filters.start_date,
        end_date: props.filters.end_date,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
}

onMounted(() => {
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