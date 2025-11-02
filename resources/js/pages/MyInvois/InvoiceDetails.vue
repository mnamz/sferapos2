<template>
    <Head :title="`Invoice Details - ${invoice.invoice_code_number || 'N/A'}`" />

    <AppLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Consolidated Invoice Details
                </h2>
                <Link
                    :href="route('orders.show', invoice.order_id)"
                    class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600"
                >
                    View Order
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Invoice Information -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">Invoice Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Invoice Code Number</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100 font-mono">
                                    {{ invoice.invoice_code_number || '-' }}
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Submission UID</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100 font-mono">
                                    {{ invoice.submission_uid || '-' }}
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">UUID</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100 font-mono break-all">
                                    {{ invoice.uuid || '-' }}
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Pushed At</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                    {{ invoice.created_at }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Order Information -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">Order Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h4 class="font-medium mb-2">Order Details</h4>
                                <div class="space-y-2 text-sm">
                                    <div>
                                        <span class="font-medium">Order Number:</span>
                                        <Link :href="route('orders.show', invoice.order_id)" class="text-indigo-600 hover:text-indigo-900 ml-2">
                                            #{{ invoice.order.order_number || invoice.order_id }}
                                        </Link>
                                    </div>
                                    <div>
                                        <span class="font-medium">Date:</span>
                                        <span class="ml-2">{{ invoice.order.created_at }}</span>
                                    </div>
                                    <div>
                                        <span class="font-medium">Cashier:</span>
                                        <span class="ml-2">{{ invoice.order.cashier.name }}</span>
                                    </div>
                                    <div>
                                        <span class="font-medium">Total:</span>
                                        <span class="ml-2">{{ currency }}{{ invoice.order.total }}</span>
                                    </div>
                                </div>
                            </div>
                            <div v-if="invoice.order.customer">
                                <h4 class="font-medium mb-2">Customer Details</h4>
                                <div class="space-y-2 text-sm">
                                    <div>
                                        <span class="font-medium">Name:</span>
                                        <span class="ml-2">{{ invoice.order.customer.name }}</span>
                                    </div>
                                    <div v-if="invoice.order.customer.email">
                                        <span class="font-medium">Email:</span>
                                        <span class="ml-2">{{ invoice.order.customer.email }}</span>
                                    </div>
                                    <div v-if="invoice.order.customer.phone">
                                        <span class="font-medium">Phone:</span>
                                        <span class="ml-2">{{ invoice.order.customer.phone }}</span>
                                    </div>
                                    <div v-if="invoice.order.customer.address">
                                        <span class="font-medium">Address:</span>
                                        <div class="ml-2 mt-1">{{ invoice.order.customer.address }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Order Items -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">Order Items</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Product</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Quantity</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Price</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                                    <tr v-for="(item, index) in invoice.order.items" :key="index">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ item.product_name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ item.quantity }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ currency }}{{ item.price }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ currency }}{{ item.total }}</td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <td colspan="3" class="px-6 py-4 text-right text-sm font-medium text-gray-900 dark:text-gray-100">Subtotal:</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">{{ currency }}{{ invoice.order.subtotal }}</td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" class="px-6 py-4 text-right text-sm font-medium text-gray-900 dark:text-gray-100">Tax:</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">{{ currency }}{{ invoice.order.tax }}</td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" class="px-6 py-4 text-right text-sm font-medium text-gray-900 dark:text-gray-100">Discount:</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">{{ currency }}{{ invoice.order.discount }}</td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" class="px-6 py-4 text-right text-sm font-bold text-gray-900 dark:text-gray-100">Total:</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900 dark:text-gray-100">{{ currency }}{{ invoice.order.total }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Request & Response Payloads -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">Request & Response Payloads</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h4 class="font-medium mb-2">Request Payload</h4>
                                <details class="cursor-pointer">
                                    <summary class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 mb-2">
                                        View Request Payload
                                    </summary>
                                    <pre class="mt-2 text-xs bg-gray-100 dark:bg-gray-900 p-3 rounded overflow-auto max-h-96">{{ JSON.stringify(invoice.request_payload, null, 2) }}</pre>
                                </details>
                            </div>
                            <div>
                                <h4 class="font-medium mb-2">Response Payload</h4>
                                <details class="cursor-pointer">
                                    <summary class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 mb-2">
                                        View Response Payload
                                    </summary>
                                    <pre class="mt-2 text-xs bg-gray-100 dark:bg-gray-900 p-3 rounded overflow-auto max-h-96">{{ JSON.stringify(invoice.response_payload, null, 2) }}</pre>
                                </details>
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
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    invoice: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const currency = computed(() => page.props.settings?.currency || 'MYR');
</script>

