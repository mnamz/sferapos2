<template>
    <AppLayout :breadcrumbs="[
        { title: 'Products', href: route('products.index') },
        { title: 'Low Stock Products', href: route('products.low-stock') }
    ]">
        <div class="container mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4 mb-6">
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Low Stock Products</h1>
                <div class="flex items-center gap-4">
                    <div class="relative">
                        <input
                            type="text"
                            v-model="search"
                            placeholder="Search products..."
                            class="w-full sm:w-64 px-4 py-2 border border-gray-300 dark:border-gray-700 rounded-md focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-800 dark:text-white"
                            @input="(e) => search = (e.target as HTMLInputElement).value"
                        />
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Product
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Category
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Stock
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Price
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="product in products?.data" :key="product.id">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                {{ product.name }}
                                            </div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ product.barcode }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 dark:text-white">{{ product.category?.name }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="{
                                        'px-2 py-1 text-xs rounded-full': true,
                                        'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': product.stock <= 5,
                                        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': product.stock > 5 && product.stock <= 10
                                    }">
                                        {{ product.stock }} units
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ currency }}{{ product.price }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <Link :href="route('products.edit', product.id)" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300 mr-3">
                                        Edit
                                    </Link>
                                    <Link :href="route('products.show', product.id)" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-300">
                                        View
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                    <Pagination :links="products?.links" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { type BreadcrumbItem } from '@/types';

interface Product {
    id: number;
    name: string;
    barcode?: string;
    image?: string;
    stock: number;
    price: number;
    category?: {
        name: string;
    };
}

interface Props {
    products?: {
        data: Product[];
        links: any;
    };
    filters?: {
        search?: string;
    };
}

const props = withDefaults(defineProps<Props>(), {
    products: undefined,
    filters: () => ({})
});

const page = usePage();
const currency = computed(() => page.props.settings?.currency || 'USD');

const search = ref(props.filters?.search || '');

const debouncedSearch = useDebounceFn((value: string) => {
    console.log('Search value:', value);
    router.get(
        route('products.low-stock'),
        { search: value },
        { 
            preserveState: true, 
            preserveScroll: true,
            onSuccess: (page) => {
                console.log('Search response:', page);
            },
            onError: (errors) => {
                console.error('Search error:', errors);
            }
        }
    );
}, 300);

watch(search, (value) => {
    console.log('Search changed:', value);
    debouncedSearch(value);
});
</script> 