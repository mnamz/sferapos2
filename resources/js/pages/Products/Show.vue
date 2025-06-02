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

                            <!-- Stock Management Section -->
                            <div v-if="!roles.includes('staff')" class="mt-8 border-t border-gray-200 dark:border-gray-700 pt-8">
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Stock Management</h2>
                                <!-- Success Message -->
                                <div v-if="successMessage" class="mt-4 p-4 bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-200 rounded-md">
                                    {{ successMessage }}
                                </div>
                                <form @submit.prevent="submit" class="space-y-6">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                Adjustment Type
                                            </label>
                                            <select 
                                                v-model="form.type"
                                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-4 py-2.5"
                                            >
                                                <option value="restock">Restock</option>
                                                <option value="withdraw">Withdraw</option>
                                            </select>
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                Quantity
                                            </label>
                                            <input 
                                                type="number" 
                                                v-model="form.quantity"
                                                min="1"
                                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-4 py-2.5"
                                            >
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Notes (Optional)
                                        </label>
                                        <textarea 
                                            v-model="form.notes"
                                            rows="3"
                                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-4 py-2.5"
                                            placeholder="Add any notes about this stock adjustment..."
                                        ></textarea>
                                    </div>

                                    <div class="flex justify-end pt-2">
                                        <button 
                                            type="submit"
                                            class="inline-flex items-center px-6 py-2.5 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition text-sm font-medium"
                                            :disabled="form.processing"
                                        >
                                            {{ form.type === 'restock' ? 'Restock' : 'Withdraw' }} Stock
                                        </button>
                                    </div>
                                </form>

                                
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Link, usePage, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const page = usePage();
const roles = page.props.auth?.roles || [];

const props = defineProps({
    product: {
        type: Object,
        required: true
    }
});

const form = useForm({
    type: 'restock',
    quantity: 1,
    notes: ''
});

const successMessage = ref('');

const submit = () => {
    form.post(route('products.adjust-stock', props.product.id), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            successMessage.value = 'Stock updated successfully';
            setTimeout(() => successMessage.value = '', 3000);
        }
    });
};
</script>

<style scoped>
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.3s;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}
</style> 