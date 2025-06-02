<template>
    <Head title="Orders" />

    <AppLayout :breadcrumbs="[
        { name: 'Orders', href: route('orders.index') }
    ]">
        <div class="container mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold text-gray-900">Orders</h1>
                <div class="flex gap-2 print:hidden">
                    <Link
                        :href="route('sales.index')"
                        class="px-4 py-2 bg-gray-800 text-white rounded-md hover:bg-gray-700 transition text-center"
                    >
                        My Sales
                    </Link>
                    <Link
                        :href="route('orders.create')"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition text-center"
                    >
                        Add New Order
                    </Link>
                    <button
                        @click="exportCSV"
                        class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition text-center"
                    >
                        Export CSV
                    </button>
                    <button
                        @click="printOrders"
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition text-center"
                    >
                        Print
                    </button>
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
                            placeholder="Search orders..."
                            class="w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                            @input="debouncedSearch"
                        >
                        <input
                            v-model="remarkSearch"
                            type="text"
                            placeholder="Search by product remark or S/N"
                            class="w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                            @input="debouncedSearch"
                        >
                    </div>

                    <!-- Desktop Table View -->
                    <div class="overflow-x-auto print-orders-table">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th 
                                        v-for="column in [
                                            { key: 'id', label: 'Order #' },
                                            { key: 'customer_name', label: 'Customer' },
                                            { key: 'subtotal', label: 'Subtotal' },
                                            { key: 'tax', label: 'Tax' },
                                            { key: 'total_amount', label: 'Total' },
                                            { key: 'profit', label: 'Profit' },
                                            { key: 'due', label: 'Due' },
                                            { key: 'items_count', label: 'Items' },
                                            { key: 'payment_method', label: 'Payment' },
                                            { key: 'status', label: 'Status' },
                                            { key: 'created_at', label: 'Date' }
                                        ]" 
                                        :key="column.key"
                                        class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600"
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
                                    <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider print:hidden">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                                <tr v-for="order in orders.data" :key="order.id">
                                    <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ order.id }}</td>
                                    <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ order.customer_name }}</td>
                                    <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ currency }}{{ order.subtotal }}</td>
                                    <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ currency }}{{ order.tax }}</td>
                                    <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ currency }}{{ order.total_amount }}</td>
                                    <td class="px-4 py-2 text-green-600 dark:text-green-400">{{ currency }}{{ order.profit }}</td>
                                    <td class="px-4 py-2 text-red-600 dark:text-red-400">{{ currency }}{{ order.due }}</td>
                                    <td class="px-4 py-2 text-gray-500 dark:text-gray-400 hidden md:table-cell">{{ order.items_count }}</td>
                                    <td class="px-4 py-2 text-gray-500 dark:text-gray-400 hidden lg:table-cell">{{ order.payment_method }}</td>
                                    <td class="px-4 py-2">
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
                                    <td class="px-4 py-2 text-gray-500 dark:text-gray-400 hidden md:table-cell">{{ order.created_at }}</td>
                                    <td class="px-4 py-2 print:hidden">
                                        <div class="flex gap-2">
                                            <Link
                                                :href="route('orders.show', order.id)"
                                                class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300"
                                            >
                                                View
                                            </Link>
                                            <Link
                                                :href="route('orders.edit', order.id)"
                                                class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300"
                                            >
                                                Edit
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Card View -->
                    <div class="md:hidden space-y-4 print:hidden">
                        <div v-for="order in orders.data" :key="order.id" class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow border dark:border-gray-700">
                            <div class="flex flex-col space-y-2">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h3 class="text-sm font-medium text-gray-900 dark:text-white">Order #{{ order.id }}</h3>
                                        <p class="text-sm text-gray-500 dark:text-gray-300">{{ order.customer_name }}</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-300">{{ currency }}{{ order.total_amount }}</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-300">{{ order.items_count }} items</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-300">{{ order.payment_method }}</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-300">{{ order.created_at }}</p>
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        <span :class="{
                                            'px-2 py-1 text-xs rounded-full text-center': true,
                                            'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': order.payment_status === 'paid',
                                            'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': order.payment_status === 'partial',
                                            'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': order.payment_status === 'pending'
                                        }">
                                            {{ order.payment_status }}
                                        </span>
                                        <span :class="{
                                            'px-2 py-1 text-xs rounded-full text-center': true,
                                            'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': order.status === 'completed',
                                            'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200': order.status === 'processing',
                                            'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': order.status === 'pending',
                                            'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': order.status === 'cancelled'
                                        }">
                                            {{ order.status }}
                                        </span>
                                    </div>
                                </div>
                                <div class="flex justify-end space-x-3 pt-2 border-t dark:border-gray-700">
                                    <Link
                                        :href="route('orders.show', order.id)"
                                        class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
                                    >
                                        View
                                    </Link>
                                    <Link
                                        :href="route('orders.edit', order.id)"
                                        class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                                    >
                                        Edit
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>

                    <Pagination v-if="orders.links" :links="orders.links" class="mt-6 print:hidden" />
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
    orders: {
        type: Object,
        required: true,
    },
    filters: Object,
    tax_percentage: {
        type: Number,
        default: 0
    }
});

const page = usePage();
const currency = computed(() => page.props.settings?.currency || 'USD');

const search = ref(props.filters?.search || '');
const remarkSearch = ref(props.filters?.remark || '');
const sortColumn = ref('');
const sortDirection = ref('asc');

const debouncedSearch = debounce(() => {
    router.get(
        route('orders.index'),
        { 
            search: search.value, 
            remark: remarkSearch.value,
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

function exportCSV() {
    const header = [
        'Order #',
        'Customer',
        'Total',
        'Items',
        'Payment',
        'Payment Status',
        'Status',
        'Date'
    ];
    const rows = props.orders.data.map(order => [
        order.id,
        order.customer_name,
        `${currency.value}${order.total_amount}`,
        order.items_count,
        order.payment_method,
        order.payment_status,
        order.status,
        order.created_at
    ]);
    const csvContent = [header, ...rows]
        .map(e => e.map(v => `"${String(v).replace(/"/g, '""')}"`).join(','))
        .join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.setAttribute('download', 'orders.csv');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function printOrders() {
    window.print();
}
</script>

<style>
@media print {
  .print\:hidden {
    display: none !important;
  }
  body * {
    visibility: hidden !important;
  }
  .print-orders-table, .print-orders-table * {
    visibility: visible !important;
  }
  .print-orders-table {
    position: absolute !important;
    left: 0;
    top: 0;
    width: 100vw;
    background: white;
    z-index: 9999;
    padding: 0;
    margin: 0;
  }
}
</style> 