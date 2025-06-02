<template>
    <AppLayout :breadcrumbs="[
        { name: 'Products', href: route('products.index') },
        { name: 'Inventory Cost', href: route('products.inventory-cost') }
    ]">
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Total Inventory Cost Card -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                    <div class="p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                    Total Inventory Cost
                                </h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Total value of all products in stock
                                </p>
                            </div>
                            <div class="flex items-center space-x-4">
                                <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">
                                    RM {{ formattedTotalCost }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Products Table -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <!-- Search Bar -->
                        <div class="mb-6 flex items-center space-x-4">
                            <div class="flex-1">
                                <input
                                    type="text"
                                    v-model="form.search"
                                    placeholder="Search products..."
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-4 py-2"
                                    @input="search"
                                />
                            </div>
                            <a
                                :href="route('products.inventory-cost.export', {
                                    search: form.search,
                                    sort: form.sort,
                                    direction: form.direction
                                })"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                            >
                                Export CSV
                            </a>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            <div class="flex items-center space-x-1">
                                                <span>Product Name</span>
                                                <button 
                                                    @click="sort('name')"
                                                    class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300"
                                                    :class="{ 'text-indigo-600 dark:text-indigo-400': form.sort === 'name' }"
                                                >
                                                    <ArrowUpDown class="w-4 h-4" />
                                                </button>
                                            </div>
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            <div class="flex items-center space-x-1">
                                                <span>Cost Price</span>
                                                <button 
                                                    @click="sort('cost_price')"
                                                    class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300"
                                                    :class="{ 'text-indigo-600 dark:text-indigo-400': form.sort === 'cost_price' }"
                                                >
                                                    <ArrowUpDown class="w-4 h-4" />
                                                </button>
                                            </div>
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            <div class="flex items-center space-x-1">
                                                <span>Stock</span>
                                                <button 
                                                    @click="sort('stock')"
                                                    class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300"
                                                    :class="{ 'text-indigo-600 dark:text-indigo-400': form.sort === 'stock' }"
                                                >
                                                    <ArrowUpDown class="w-4 h-4" />
                                                </button>
                                            </div>
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            <div class="flex items-center space-x-1">
                                                <span>Total Cost</span>
                                                <button 
                                                    @click="sort('total_cost')"
                                                    class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300"
                                                    :class="{ 'text-indigo-600 dark:text-indigo-400': form.sort === 'total_cost' }"
                                                >
                                                    <ArrowUpDown class="w-4 h-4" />
                                                </button>
                                            </div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-for="product in products.data" :key="product.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            {{ product.name }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            RM {{ Number(product.cost_price).toFixed(2) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            {{ product.stock }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            RM {{ (Number(product.cost_price) * product.stock).toFixed(2) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="mt-4">
                            <Pagination :links="products.links" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { ArrowUpDown } from 'lucide-vue-next';

const props = defineProps({
    products: {
        type: Object,
        required: true
    },
    totalInventoryCost: {
        type: [Number, String],
        required: true
    },
    filters: {
        type: Object,
        required: true
    }
});

const form = ref({
    search: props.filters.search,
    sort: props.filters.sort || 'name',
    direction: props.filters.direction || 'asc'
});

const formattedTotalCost = computed(() => {
    return Number(props.totalInventoryCost).toFixed(2);
});

const search = () => {
    router.get(
        route('products.inventory-cost'),
        { 
            search: form.value.search,
            sort: form.value.sort,
            direction: form.value.direction
        },
        { 
            preserveState: true, 
            preserveScroll: true,
            replace: true,
            only: ['products']
        }
    );
};

// Debounce search
watch(() => form.value.search, (value) => {
    search();
}, { debounce: 300 });

const reset = () => {
    form.value.search = '';
    form.value.sort = 'name';
    form.value.direction = 'asc';
    router.get(route('products.inventory-cost'));
};

const sort = (column) => {
    if (form.value.sort === column) {
        form.value.direction = form.value.direction === 'asc' ? 'desc' : 'asc';
    } else {
        form.value.sort = column;
        form.value.direction = 'asc';
    }
    search();
};
</script> 