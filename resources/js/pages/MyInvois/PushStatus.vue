<template>
    <Head title="MyInvois Push Status" />

    <AppLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                MyInvois Push Status
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Flash Messages -->
                <div v-if="$page.props.flash?.success" class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ $page.props.flash.success }}</span>
                </div>
                <div v-if="$page.props.flash?.error" class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ $page.props.flash.error }}</span>
                </div>
                <div v-if="$page.props.flash?.info" class="mb-6 bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ $page.props.flash.info }}</span>
                </div>
                
                <!-- Queue Statistics -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold">Consolidation Queue</h3>
                            <button
                                v-if="queueStats.count > 0"
                                @click="pushConsolidated"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                :disabled="pushing"
                            >
                                <span v-if="pushing">Pushing...</span>
                                <span v-else>Push Consolidated Invoices ({{ queueStats.count }})</span>
                            </button>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                <div class="text-sm text-gray-600 dark:text-gray-400">Orders in Queue</div>
                                <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ queueStats.count }}</div>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                <div class="text-sm text-gray-600 dark:text-gray-400">Total Push Attempts</div>
                                <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ pushResults.length }}</div>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                <div class="text-sm text-gray-600 dark:text-gray-400">Successful Pushes</div>
                                <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                                    {{ pushResults.filter(r => r.success).length }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Queued Orders -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6" v-if="queuedOrders.length > 0">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold">Orders in Queue</h3>
                            <button
                                v-if="queueStats.count > 0"
                                @click="pushConsolidated"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                :disabled="pushing"
                            >
                                <span v-if="pushing">Pushing...</span>
                                <span v-else>Push All ({{ queueStats.count }})</span>
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Order ID</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Customer</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                                    <tr v-for="order in queuedOrders" :key="order.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            <Link :href="route('orders.show', order.id)" class="text-indigo-600 hover:text-indigo-900">
                                                #{{ order.id }}
                                            </Link>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ order.customer_name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ currency }}{{ order.total }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ order.created_at }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <span v-if="order.has_myinvois_invoice" class="px-2 py-1 bg-green-100 text-green-800 rounded">
                                                Pushed
                                            </span>
                                            <span v-else class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded">
                                                Pending
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Push Results History -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">Push Results History</h3>
                        <div v-if="pushResults.length === 0" class="text-gray-500 dark:text-gray-400">
                            No push results yet.
                        </div>
                        <div v-else class="space-y-4">
                            <div
                                v-for="(result, index) in pushResults"
                                :key="index"
                                class="border border-gray-200 dark:border-gray-700 rounded-lg p-4"
                                :class="result.success ? 'bg-green-50 dark:bg-green-900/20' : 'bg-red-50 dark:bg-red-900/20'"
                            >
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <div class="font-semibold text-gray-900 dark:text-gray-100">
                                            {{ result.timestamp }}
                                        </div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">
                                            {{ result.order_count }} order(s)
                                        </div>
                                    </div>
                                    <div>
                                        <span
                                            v-if="result.success"
                                            class="px-3 py-1 bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 rounded text-sm font-semibold"
                                        >
                                            Success
                                        </span>
                                        <span
                                            v-else
                                            class="px-3 py-1 bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 rounded text-sm font-semibold"
                                        >
                                            Failed
                                        </span>
                                    </div>
                                </div>
                                
                                <div v-if="result.success" class="mt-2 space-y-1 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Accepted:</span>
                                        <span class="font-semibold text-green-600 dark:text-green-400">{{ result.accepted_count }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Rejected:</span>
                                        <span class="font-semibold text-red-600 dark:text-red-400">{{ result.rejected_count }}</span>
                                    </div>
                                    <div v-if="result.submission_uid" class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Submission UID:</span>
                                        <span class="font-mono text-xs">{{ result.submission_uid }}</span>
                                    </div>
                                </div>

                                <div v-else class="mt-2 text-sm">
                                    <div class="text-red-600 dark:text-red-400">
                                        <strong>Status Code:</strong> {{ result.status_code }}
                                    </div>
                                    <div v-if="result.error" class="text-red-600 dark:text-red-400 mt-1">
                                        <strong>Error:</strong> {{ result.error }}
                                    </div>
                                </div>

                                <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                                    <details class="cursor-pointer">
                                        <summary class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                                            View Order IDs ({{ result.order_ids?.length || 0 }})
                                        </summary>
                                        <div class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                            <div class="flex flex-wrap gap-2">
                                                <Link
                                                    v-for="orderId in result.order_ids"
                                                    :key="orderId"
                                                    :href="route('orders.show', orderId)"
                                                    class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400"
                                                >
                                                    #{{ orderId }}
                                                </Link>
                                            </div>
                                        </div>
                                    </details>
                                    <details class="cursor-pointer mt-2">
                                        <summary class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                                            View Full Response
                                        </summary>
                                        <pre class="mt-2 text-xs bg-gray-100 dark:bg-gray-900 p-3 rounded overflow-auto max-h-64">{{ JSON.stringify(result.response, null, 2) }}</pre>
                                    </details>
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
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    pushResults: {
        type: Array,
        default: () => [],
    },
    queueStats: {
        type: Object,
        default: () => ({ count: 0, order_ids: [] }),
    },
    queuedOrders: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const currency = computed(() => page.props.settings?.currency || 'MYR');

const pushing = ref(false);

const pushConsolidated = () => {
    if (confirm(`Are you sure you want to push ${props.queueStats.count} order(s) to MyInvois?`)) {
        pushing.value = true;
        router.post(
            route('myinvois.push'),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    pushing.value = false;
                    router.reload({ only: ['pushResults', 'queueStats', 'queuedOrders'] });
                },
                onError: () => {
                    pushing.value = false;
                },
            }
        );
    }
};
</script>

