<template>
    <Head title="Activity Log" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Filters -->
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
                                Event Type
                            </label>
                            <select 
                                v-model="filters.event"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-3 py-2"
                            >
                                <option value="">All Events</option>
                                <option value="created">Created</option>
                                <option value="updated">Updated</option>
                                <option value="deleted">Deleted</option>
                            </select>
                        </div>
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Model Type
                            </label>
                            <select 
                                v-model="filters.auditable_type"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-3 py-2"
                            >
                                <option value="">All Models</option>
                                <option value="Order">Order</option>
                                <option value="Product">Product</option>
                                <option value="Customer">Customer</option>
                                <option value="User">User</option>
                            </select>
                        </div>
                        <div class="flex gap-2">
                            <button 
                                @click="applyFilters"
                                class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                            >
                                Apply Filters
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Activity Log Table -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Activity Log</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">User</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Event</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Model</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Changes</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">IP Address</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-for="audit in audits.data" :key="audit.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            {{ audit.created_at }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            {{ audit.user?.name || 'System' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span :class="{
                                                'px-2 py-1 text-xs rounded-full': true,
                                                'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': audit.event === 'created',
                                                'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200': audit.event === 'updated',
                                                'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': audit.event === 'deleted'
                                            }">
                                                {{ audit.event }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            {{ audit.auditable_type }} #{{ audit.auditable_id }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">
                                            <div v-if="audit.event === 'created'">
                                                <div v-for="(value, key) in audit.new_values" :key="key" class="mb-1">
                                                    <span class="font-medium">{{ key }}:</span> {{ value }}
                                                </div>
                                            </div>
                                            <div v-else-if="audit.event === 'updated'">
                                                <div v-for="(value, key) in audit.new_values" :key="key" class="mb-1">
                                                    <span class="font-medium">{{ key }}:</span>
                                                    <span class="line-through text-red-600 dark:text-red-400">{{ audit.old_values[key] }}</span>
                                                    <span class="mx-1">→</span>
                                                    <span class="text-green-600 dark:text-green-400">{{ value }}</span>
                                                </div>
                                            </div>
                                            <div v-else-if="audit.event === 'deleted'">
                                                <div v-for="(value, key) in audit.old_values" :key="key" class="mb-1">
                                                    <span class="font-medium">{{ key }}:</span> {{ value }}
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            {{ audit.ip_address }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <Pagination :links="audits.links" class="mt-6" />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    audits: Object,
    filters: Object,
});

const filters = ref({
    start_date: '',
    end_date: '',
    event: '',
    auditable_type: '',
    ...props.filters
});

const breadcrumbs = [
    {
        title: 'Activity Log',
        href: route('activity-log.index'),
    },
];

function applyFilters() {
    router.get(route('activity-log.index'), {
        start_date: filters.value.start_date,
        end_date: filters.value.end_date,
        event: filters.value.event,
        auditable_type: filters.value.auditable_type,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
}
</script> 