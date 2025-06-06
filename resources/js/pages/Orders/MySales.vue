<template>
    <Head title="My Sales" />

    <AppLayout :breadcrumbs="[
        { name: 'Orders', href: route('orders.index') },
        { name: 'My Sales', href: route('sales.index') }
    ]">
        <div class="container mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">My Sales</h1>
                <div class="flex gap-2">
                    <Link
                        :href="route('orders.index')"
                        class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700"
                    >
                        Back to Orders
                    </Link>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                <div class="p-4">
                    <div class="flex items-center mb-4">
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search orders..."
                            class="w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                            @input="debouncedSearch"
                        >
                    </div>

                    <!-- Desktop Table View -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Order #</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Customer</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider hidden md:table-cell">Items</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider hidden lg:table-cell">Payment</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Payment Status</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider hidden md:table-cell">Date</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                                <tr v-for="order in orders.data" :key="order.id">
                                    <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ order.id }}</td>
                                    <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ order.customer_name }}</td>
                                    <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ currency }}{{ order.total_amount }}</td>
                                    <td class="px-4 py-2 text-gray-500 dark:text-gray-400 hidden md:table-cell">{{ order.items_count }}</td>
                                    <td class="px-4 py-2 text-gray-500 dark:text-gray-400 hidden lg:table-cell">{{ order.payment_method }}</td>
                                    <td class="px-4 py-2">
                                        <span :class="{
                                            'px-2 py-1 text-xs rounded-full': true,
                                            'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': order.payment_status === 'paid',
                                            'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': order.payment_status === 'partial',
                                            'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': order.payment_status === 'pending'
                                        }">
                                            {{ order.payment_status }}
                                        </span>
                                    </td>
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
                                    <td class="px-4 py-2">
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
                    <div class="md:hidden space-y-4">
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

                    <Pagination v-if="orders.links" :links="orders.links" class="mt-6" />
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

const props = defineProps({
    orders: {
        type: Object,
        required: true,
    },
    filters: Object,
});

const page = usePage();
const currency = computed(() => page.props.settings?.currency || 'USD');

const search = ref(props.filters?.search || '');

const debouncedSearch = debounce(() => {
    router.get(
        route('sales.index'),
        { search: search.value },
        { preserveState: true, preserveScroll: true }
    );
}, 300);
</script> 