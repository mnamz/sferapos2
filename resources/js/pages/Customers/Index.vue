<template>
    <AppLayout :breadcrumbs="[
        { name: 'Customers', href: route('customers.index') }
    ]">
        <div class="container mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4 mb-6">
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Customers</h1>
                <div class="flex gap-2 print:hidden">
                    <Link
                        :href="route('customers.create')"
                        class="w-full sm:w-auto px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition text-center"
                    >
                        Add New Customer
                    </Link>
                    <button
                        @click="exportCSV"
                        class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition text-center"
                    >
                        Export CSV
                    </button>
                </div>
            </div>

            <div v-if="$page.props.flash?.success" class="mb-4">
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ $page.props.flash.success }}</span>
                </div>
            </div>

            <div v-if="$page.props.flash?.error" class="mb-4">
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ $page.props.flash.error }}</span>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                <div class="p-4">
                    <div class="flex items-center mb-4">
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search customers..."
                            class="w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                            @input="debouncedSearch"
                        >
                    </div>

                    <!-- Desktop Table View -->
                    <div class="hidden md:block print-customers-table">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Phone</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                                <tr v-for="customer in customers.data" :key="customer.id">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ customer.name }}</div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500 dark:text-gray-300">{{ customer.email }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500 dark:text-gray-300">{{ customer.phone }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span :class="[
                                            'px-2 inline-flex text-xs leading-5 font-semibold rounded-full',
                                            customer.status ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                        ]">
                                            {{ customer.status ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <Link
                                            :href="route('customers.edit', customer.id)"
                                            class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 mr-4"
                                        >
                                            Edit
                                        </Link>
                                        <button
                                            @click="destroy(customer.id)"
                                            v-if="!roles.includes('staff')" 
                                            class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                        >
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Card View -->
                    <div class="md:hidden space-y-4">
                        <div v-for="customer in customers.data" :key="customer.id" class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow border dark:border-gray-700">
                            <div class="flex flex-col space-y-2">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h3 class="text-sm font-medium text-gray-900 dark:text-white">{{ customer.name }}</h3>
                                        <p class="text-sm text-gray-500 dark:text-gray-300">{{ customer.email }}</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-300">{{ customer.phone }}</p>
                                    </div>
                                    <span :class="[
                                        'px-2 py-1 text-xs font-semibold rounded-full',
                                        customer.status ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                    ]">
                                        {{ customer.status ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                                <div class="flex justify-end space-x-3 pt-2 border-t dark:border-gray-700">
                                    <Link
                                        :href="route('customers.edit', customer.id)"
                                        class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
                                    >
                                        Edit
                                    </Link>
                                    <button
                                        @click="destroy(customer.id)"
                                        class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <Pagination :links="customers.links" class="mt-6" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Pagination from '@/Components/Pagination.vue';
import debounce from 'lodash/debounce';
import AppLayout from '@/Layouts/AppLayout.vue';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

const page = usePage();
const roles = page.props.auth?.roles || [];
const props = defineProps({
    customers: Object,
    filters: Object
});

const search = ref(props.filters?.search || '');

const debouncedSearch = debounce(() => {
    router.get(
        route('customers.index'),
        { search: search.value },
        { preserveState: true, preserveScroll: true }
    );
}, 300);

const destroy = (id) => {
    if (confirm('Are you sure you want to delete this customer?')) {
        router.delete(route('customers.destroy', id), {
            onSuccess: () => {
                toast.success('Customer deleted successfully');
            },
        });
    }
};

function exportCSV() {
    const header = ['Name', 'Email', 'Phone', 'Status'];
    const rows = props.customers.data.map(c => [
        c.name,
        c.email,
        c.phone,
        c.status ? 'Active' : 'Inactive'
    ]);
    const csvContent = [header, ...rows]
        .map(e => e.map(v => `"${String(v).replace(/"/g, '""')}"`).join(','))
        .join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.setAttribute('download', 'customers.csv');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function printCustomers() {
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
  .print-customers-table, .print-customers-table * {
    visibility: visible !important;
  }
  .print-customers-table {
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