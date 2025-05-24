<template>
    <AppLayout :breadcrumbs="[
        { name: 'Products', href: route('products.index') },
        { name: product.name, href: route('products.show', product.id) }
    ]">
        <div class="container mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                <div class="p-6">
                    <div class="flex flex-col md:flex-row gap-8">
                        <!-- Product Image -->
                        <div class="md:w-1/3">
                            <div class="aspect-square rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-700">
                                <img 
                                    v-if="product.image_url" 
                                    :src="product.image_url" 
                                    :alt="product.name"
                                    class="w-full h-full object-cover"
                                />
                                <div v-else class="w-full h-full flex items-center justify-center">
                                    <svg class="h-16 w-16 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Product Details -->
                        <div class="md:w-2/3">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">{{ product.name }}</h1>
                                    <p class="text-gray-600 dark:text-gray-300 mb-4">{{ product.description }}</p>
                                </div>
                                <span :class="[
                                    'px-3 py-1 text-sm font-semibold rounded-full',
                                    product.status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                ]">
                                    {{ product.status }}
                                </span>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                                <div>
                                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Category</h3>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ product.category?.name || 'Uncategorized' }}</p>
                                </div>
                                <div>
                                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Price</h3>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-white">RM {{ product.price }}</p>
                                </div>
                                <div>
                                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Stock</h3>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ product.stock }} units</p>
                                </div>
                                <div v-if="!roles.includes('staff')">
                                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Cost Price</h3>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-white">RM {{ product.cost_price }}</p>
                                </div>
                            </div>

                            <div class="mt-8 flex gap-4">
                                <Link
                                    :href="route('products.edit', product.id)"
                                    v-if="!roles.includes('staff')"
                                    class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition"
                                >
                                    Edit Product
                                </Link>
                                <Link
                                    :href="route('products.index')"
                                    class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 transition dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                                >
                                    Back to Products
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const page = usePage();
const roles = page.props.auth?.roles || [];

defineProps({
    product: {
        type: Object,
        required: true
    }
});
</script> 