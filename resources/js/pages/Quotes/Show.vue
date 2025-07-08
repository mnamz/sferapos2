<template>
    <Head :title="'Quote #' + quote.id" />
    <AppLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Quote #{{ quote.id }}
                </h2>
                <div class="flex gap-2">
                    <Link
                        :href="route('quotes.index')"
                        class="px-4 py-2 bg-gray-500 dark:bg-gray-600 text-white rounded-lg hover:bg-gray-600 dark:hover:bg-gray-500"
                    >
                        Back to Quotes
                    </Link>
                    <Link
                        :href="route('quotes.edit', quote.id)"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
                    >
                        Edit Quote
                    </Link>
                    <a
                        :href="route('quotes.pdf', quote.id)"
                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700"
                        target="_blank"
                    >
                        Print PDF
                    </a>
                </div>
            </div>
        </template>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div v-if="$page.props.success" class="mb-4">
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
                        {{ $page.props.success }}
                    </div>
                </div>
                <div v-if="$page.props.error" class="mb-4">
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
                        {{ $page.props.error }}
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 pb-24 text-gray-900 dark:text-gray-100">
                        <!-- Quote Status and Date -->
                        <div class="flex justify-between items-center mb-6">
                            <div class="flex items-center gap-4">
                                <span class="px-3 py-1 text-sm rounded-full inline-flex items-center gap-2"
                                    :class="{
                                        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': quote.status === 'accepted',
                                        'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200': quote.status === 'sent',
                                        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': quote.status === 'draft',
                                        'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': quote.status === 'rejected'
                                    }"
                                >
                                    {{ quote.status }}
                                </span>
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                {{ quote.created_at }}
                            </div>
                        </div>
                        <!-- Customer Info -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                <h3 class="text-lg font-semibold mb-2">Customer Information</h3>
                                <div v-if="quote.customer">
                                    <p><span class="font-medium">Name:</span> {{ quote.customer.name }}</p>
                                    <p><span class="font-medium">Email:</span> {{ quote.customer.email }}</p>
                                    <p><span class="font-medium">Phone:</span> {{ quote.customer.phone }}</p>
                                    <p><span class="font-medium">Address:</span> {{ quote.customer.address }}</p>
                                </div>
                                <p v-else class="text-gray-500 dark:text-gray-400">Walk-in Customer</p>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                <h3 class="text-lg font-semibold mb-2">Quote Information</h3>
                                <p><span class="font-medium">Created By:</span> {{ quote.user?.name }}</p>
                                <p><span class="font-medium">Delivery Method:</span> {{ quote.delivery_method }}</p>
                                <p v-if="quote.remarks"><span class="font-medium">Remarks:</span> {{ quote.remarks }}</p>
                            </div>
                        </div>
                        <!-- Quote Items -->
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold mb-4">Quote Items</h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Product</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Price</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Quantity</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Remark</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Custom Fields</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                                        <tr v-for="item in quote.items" :key="item.id">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ item.product_name }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ item.price }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ item.quantity }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ item.total }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ item.remark || '-' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                                <div v-if="item.custom_fields">
                                                    <div v-for="(val, key) in item.custom_fields" :key="key">
                                                        <span class="font-medium">{{ key }}:</span> {{ val }}
                                                    </div>
                                                </div>
                                                <span v-else>-</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <!-- Quote Summary -->
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                            <h3 class="text-lg font-semibold mb-4">Quote Summary</h3>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span>Subtotal</span>
                                    <span>{{ quote.subtotal }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Tax</span>
                                    <span>{{ quote.tax }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Discount</span>
                                    <span>{{ quote.discount }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Delivery Cost</span>
                                    <span>{{ quote.delivery_cost }}</span>
                                </div>
                                <div class="flex justify-between font-semibold border-t dark:border-gray-600 pt-2">
                                    <span>Total</span>
                                    <span>{{ quote.total }}</span>
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
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
const props = defineProps({
    quote: {
        type: Object,
        required: true,
    },
});
const page = usePage();
const currency = computed(() => page.props.settings?.currency || 'USD');
</script> 