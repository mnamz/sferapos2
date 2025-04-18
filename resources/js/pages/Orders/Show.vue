<template>
    <Head :title="'Order #' + order.id" />

    <AppLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Order #{{ order.order_number }}
                </h2>
                <div class="flex gap-2">
                    <Link
                        :href="route('orders.index')"
                        class="px-4 py-2 bg-gray-500 dark:bg-gray-600 text-white rounded-lg hover:bg-gray-600 dark:hover:bg-gray-500"
                    >
                        Back to Orders
                    </Link>
                    <Link
                        :href="route('orders.edit', order.id)"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
                    >
                        Edit Order
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Success Message -->
                <div v-if="$page.props.success" class="mb-4">
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
                        {{ $page.props.success }}
                    </div>
                </div>

                <!-- Error Message -->
                <div v-if="$page.props.error" class="mb-4">
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
                        {{ $page.props.error }}
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 pb-24 text-gray-900 dark:text-gray-100">
                        <!-- Order Status and Date -->
                        <div class="flex justify-between items-center mb-6">
                            <div class="flex items-center gap-4">
                                <div class="relative status-dropdown">
                                    <button
                                        @click="showStatusDropdown = !showStatusDropdown"
                                        type="button"
                                        class="status-dropdown-button"
                                        :class="{
                                            'px-3 py-1 text-sm rounded-full inline-flex items-center gap-2': true,
                                            'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': order.status === 'completed',
                                            'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200': order.status === 'processing',
                                            'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': order.status === 'pending',
                                            'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': order.status === 'cancelled'
                                        }"
                                    >
                                        {{ order.status }}
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                    <!-- Status Dropdown -->
                                    <div
                                        v-show="showStatusDropdown"
                                        class="status-dropdown-menu absolute z-10 mt-2 w-48 rounded-md shadow-lg bg-white dark:bg-gray-700 ring-1 ring-black ring-opacity-5"
                                    >
                                        <div class="py-1" role="menu">
                                            <button
                                                v-for="status in ['pending', 'processing', 'completed', 'cancelled']"
                                                :key="status"
                                                @click="updateStatus(status)"
                                                class="block w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-600"
                                                :class="{
                                                    'text-yellow-800 dark:text-yellow-200': status === 'pending',
                                                    'text-blue-800 dark:text-blue-200': status === 'processing',
                                                    'text-green-800 dark:text-green-200': status === 'completed',
                                                    'text-red-800 dark:text-red-200': status === 'cancelled'
                                                }"
                                            >
                                                {{ status.charAt(0).toUpperCase() + status.slice(1) }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <span :class="{
                                    'px-3 py-1 text-sm rounded-full': true,
                                    'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': order.payment_status === 'paid',
                                    'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': order.payment_status === 'partial',
                                    'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': order.payment_status === 'pending'
                                }">
                                    {{ order.payment_status }}
                                </span>
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                {{ order.created_at }}
                            </div>
                        </div>

                        <!-- Customer and Cashier Info -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                <h3 class="text-lg font-semibold mb-2">Customer Information</h3>
                                <div v-if="order.customer">
                                    <p><span class="font-medium">Name:</span> {{ order.customer.name }}</p>
                                    <p><span class="font-medium">Email:</span> {{ order.customer.email }}</p>
                                    <p><span class="font-medium">Phone:</span> {{ order.customer.phone }}</p>
                                    <p><span class="font-medium">Address:</span> {{ order.customer.address }}</p>
                                </div>
                                <p v-else class="text-gray-500 dark:text-gray-400">Walk-in Customer</p>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                <h3 class="text-lg font-semibold mb-2">Order Information</h3>
                                <p><span class="font-medium">Cashier:</span> {{ order.cashier.name }}</p>
                                <p><span class="font-medium">Payment Method:</span> {{ order.payment_method }}</p>
                                <p><span class="font-medium">Delivery Method:</span> {{ order.delivery_method }}</p>
                                <p v-if="order.remarks"><span class="font-medium">Remarks:</span> {{ order.remarks }}</p>
                            </div>
                        </div>

                        <!-- Order Items -->
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold mb-4">Order Items</h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Product</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Price</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Quantity</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                                        <tr v-for="item in order.items" :key="item.id">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ item.product_name }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ currency }}{{ item.price }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ item.quantity }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ currency }}{{ item.total }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Order Summary -->
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                            <h3 class="text-lg font-semibold mb-4">Order Summary</h3>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span>Subtotal</span>
                                    <span>{{ currency }}{{ order.subtotal }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Tax</span>
                                    <span>{{ currency }}{{ order.tax }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Delivery Cost</span>
                                    <span>{{ currency }}{{ order.delivery_cost }}</span>
                                </div>
                                <div class="flex justify-between font-semibold border-t dark:border-gray-600 pt-2">
                                    <span>Total</span>
                                    <span>{{ currency }}{{ order.total }}</span>
                                </div>
                                <div class="flex justify-between text-green-600 dark:text-green-400">
                                    <span>Paid Amount</span>
                                    <span>{{ currency }}{{ order.paid_amount }}</span>
                                </div>
                                <div v-if="order.due_amount > 0" class="flex justify-between text-red-600 dark:text-red-400">
                                    <span>Due Amount</span>
                                    <span>{{ currency }}{{ order.due_amount }}</span>
                                </div>
                                <div v-if="order.change_amount > 0" class="flex justify-between text-blue-600 dark:text-blue-400">
                                    <span>Change</span>
                                    <span>{{ currency }}{{ order.change_amount }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fixed Bottom Bar -->
        <div class="fixed bottom-0 left-0 right-0 py-4 px-6 bg-white dark:bg-gray-800 border-t dark:border-gray-700 shadow-lg">
            <div class="max-w-7xl mx-auto flex justify-end gap-4">
                <Link
                    :href="route('orders.index')"
                    class="px-4 py-2 bg-gray-500 dark:bg-gray-600 text-white rounded-lg hover:bg-gray-600 dark:hover:bg-gray-500"
                >
                    Back to Orders
                </Link>
                <Link
                    :href="route('orders.edit', order.id)"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
                >
                    Edit Order
                </Link>
                <a
                    :href="route('orders.invoice', order.id)"
                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700"
                    target="_blank"
                >
                    Print Invoice
                </a>
            </div>
        </div>

    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, computed } from 'vue';

const props = defineProps({
    order: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const currency = computed(() => page.props.settings?.currency || 'USD');

const showStatusDropdown = ref(false);

const updateStatus = (status) => {
    if (confirm(`Are you sure you want to change the order status to ${status}?`)) {
        router.put(route('orders.update', props.order.id), {
            status: status
        }, {
            preserveScroll: true,
            onSuccess: () => {
                showStatusDropdown.value = false;
            },
        });
    }
};

// Close dropdown when clicking outside
const closeDropdown = (e) => {
    const dropdown = document.querySelector('.status-dropdown');
    if (dropdown && !dropdown.contains(e.target)) {
        showStatusDropdown.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', closeDropdown);
});

onUnmounted(() => {
    document.removeEventListener('click', closeDropdown);
});
</script> 