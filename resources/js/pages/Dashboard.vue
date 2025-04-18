<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';
import { onMounted, ref, computed } from 'vue';
import Chart from 'chart.js/auto';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

const props = defineProps({
    todayStats: Object,
    monthlyStats: Object,
    recentOrders: Array,
    topProducts: Array,
    lowStockProducts: Array,
    salesChart: Array,
});

const page = usePage();
const currency = computed(() => page.props.settings?.currency || 'USD');
const salesChart = ref(null);
const salesChartInstance = ref(null);

onMounted(() => {
    const ctx = salesChart.value.getContext('2d');
    
    // Destroy existing chart if it exists
    if (salesChartInstance.value) {
        salesChartInstance.value.destroy();
    }

    salesChartInstance.value = new Chart(ctx, {
        type: 'line',
        data: {
            labels: props.salesChart.map(item => item.date),
            datasets: [
                {
                    label: 'Sales',
                    data: props.salesChart.map(item => item.sales),
                    borderColor: '#4F46E5',
                    tension: 0.4,
                    fill: false
                },
                {
                    label: 'Orders',
                    data: props.salesChart.map(item => item.orders),
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

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Stats Overview -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
                    <!-- Today's Stats -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Today's Overview</h3>
                            <div class="space-y-4">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600 dark:text-gray-400">Sales</span>
                                    <span class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ currency }}{{ todayStats.sales }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600 dark:text-gray-400">Orders</span>
                                    <span class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ todayStats.orders }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600 dark:text-gray-400">New Customers</span>
                                    <span class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ todayStats.customers }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Monthly Stats -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">This Month</h3>
                            <div class="space-y-4">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600 dark:text-gray-400">Sales</span>
                                    <span class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ currency }}{{ monthlyStats.sales }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600 dark:text-gray-400">Orders</span>
                                    <span class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ monthlyStats.orders }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600 dark:text-gray-400">New Customers</span>
                                    <span class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ monthlyStats.customers }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Low Stock Alerts -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Low Stock Alerts</h3>
                            <div class="space-y-4">
                                <div v-if="lowStockProducts.length === 0" class="text-gray-500 dark:text-gray-400">
                                    No products are low in stock
                                </div>
                                <div v-else class="space-y-2">
                                    <div v-for="product in lowStockProducts" :key="product.id" class="flex justify-between items-center">
                                        <span class="text-gray-600 dark:text-gray-400">{{ product.name }}</span>
                                        <span class="text-red-600 dark:text-red-400 font-semibold">{{ product.stock }} left</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sales Chart -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Sales Last 7 Days</h3>
                        <div class="h-64">
                            <canvas ref="salesChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Recent Orders and Top Products -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Recent Orders -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Recent Orders</h3>
                            <div class="space-y-4">
                                <div v-for="order in recentOrders" :key="order.id" class="flex justify-between items-center border-b dark:border-gray-700 pb-3">
                                    <div>
                                        <Link :href="route('orders.show', order.id)" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">
                                            #{{ order.order_number }}
                                        </Link>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ order.customer_name }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-500">{{ order.created_at }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-semibold text-gray-900 dark:text-gray-100">{{ currency }}{{ order.total }}</p>
                                        <span :class="{
                                            'px-2 py-1 text-xs rounded-full': true,
                                            'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': order.status === 'completed',
                                            'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200': order.status === 'processing',
                                            'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': order.status === 'pending',
                                            'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': order.status === 'cancelled'
                                        }">
                                            {{ order.status }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Top Products -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Top Selling Products</h3>
                            <div class="space-y-4">
                                <div v-for="product in topProducts" :key="product.product_name" class="flex justify-between items-center border-b dark:border-gray-700 pb-3">
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-gray-100">{{ product.product_name }}</p>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ product.total_quantity }} units sold</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-semibold text-gray-900 dark:text-gray-100">{{ currency }}{{ product.total_sales }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
