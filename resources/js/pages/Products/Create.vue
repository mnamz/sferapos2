<template>
    <Head title="Create Product" />

    <AppLayout :breadcrumbs="[
        { name: 'Products', href: route('products.index') },
        { name: 'Create', href: route('products.create') }
    ]">
        <div class="container mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <div class="p-4 sm:p-6 lg:p-8 text-gray-900 dark:text-gray-100">
                        <form @submit.prevent="submit">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
                                <div class="space-y-4 sm:space-y-6">
                                    <!-- Name -->
                                    <div>
                                        <Label for="name" class="text-gray-900 dark:text-gray-100">Name</Label>
                                        <Input
                                            id="name"
                                            v-model="form.name"
                                            type="text"
                                            class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                            required
                                        />
                                        <InputError :message="form.errors.name" class="mt-2" />
                                    </div>

                                    <!-- Price -->
                                    <div>
                                        <Label for="price" class="text-gray-900 dark:text-gray-100">Price</Label>
                                        <Input
                                            id="price"
                                            v-model="form.price"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                            required
                                        />
                                        <InputError :message="form.errors.price" class="mt-2" />
                                    </div>

                                    <!-- Stock -->
                                    <div>
                                        <Label for="stock" class="text-gray-900 dark:text-gray-100">Stock</Label>
                                        <Input
                                            id="stock"
                                            v-model="form.stock"
                                            type="number"
                                            min="0"
                                            class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                            required
                                        />
                                        <InputError :message="form.errors.stock" class="mt-2" />
                                    </div>

                                    <!-- Barcode -->
                                    <div>
                                        <Label for="barcode" class="text-gray-900 dark:text-gray-100">Barcode</Label>
                                        <Input
                                            id="barcode"
                                            v-model="form.barcode"
                                            type="text"
                                            class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                            placeholder="Enter product barcode"
                                        />
                                        <InputError :message="form.errors.barcode" class="mt-2" />
                                    </div>

                                    <!-- Category -->
                                    <div>
                                        <Label for="category_id" class="text-gray-900 dark:text-gray-100">Category</Label>
                                        <Select
                                            id="category_id"
                                            v-model="form.category_id"
                                            class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                            required
                                        >
                                            <option value="">Select a category</option>
                                            <option v-for="category in categories" :key="category.id" :value="category.id">
                                                {{ category.name }}
                                            </option>
                                        </Select>
                                        <InputError :message="form.errors.category_id" class="mt-2" />
                                    </div>
                                </div>

                                <div class="space-y-4 sm:space-y-6">
                                    <!-- Description -->
                                    <div>
                                        <Label for="description" class="text-gray-900 dark:text-gray-100">Description</Label>
                                        <Textarea
                                            id="description"
                                            v-model="form.description"
                                            class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                            rows="3"
                                        />
                                        <InputError :message="form.errors.description" class="mt-2" />
                                    </div>

                                    <!-- Supplier -->
                                    <div>
                                        <Label for="supplier_id" class="text-gray-900 dark:text-gray-100">Supplier (Optional)</Label>
                                        <Select
                                            id="supplier_id"
                                            v-model="form.supplier_id"
                                            class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                        >
                                            <option value="">Select a supplier</option>
                                            <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                                                {{ supplier.name }}
                                            </option>
                                        </Select>
                                        <InputError :message="form.errors.supplier_id" class="mt-2" />
                                    </div>

                                    <!-- Status -->
                                    <div>
                                        <Label for="status" class="text-gray-900 dark:text-gray-100">Status</Label>
                                        <Select
                                            id="status"
                                            v-model="form.status"
                                            class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                            required
                                        >
                                            <option value="active">Active</option>
                                            <option value="inactive">Inactive</option>
                                        </Select>
                                        <InputError :message="form.errors.status" class="mt-2" />
                                    </div>

                                    <!-- Image Upload -->
                                    <div>
                                        <Label for="image" class="text-gray-900 dark:text-gray-100">Product Image</Label>
                                        <Input
                                            id="image"
                                            type="file"
                                            @input="form.image = $event.target.files[0]"
                                            accept="image/*"
                                            class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                        />
                                        <InputError :message="form.errors.image" class="mt-2" />
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-col sm:flex-row items-center justify-end gap-4 mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                                <Link
                                    :href="route('products.index')"
                                    class="w-full sm:w-auto px-4 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600 text-center"
                                >
                                    Cancel
                                </Link>
                                <Button :disabled="form.processing" class="w-full sm:w-auto bg-indigo-600 hover:bg-indigo-700">
                                    Save Product
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import InputError from '@/components/InputError.vue';

const props = defineProps({
    categories: {
        type: Array,
        required: true,
    },
    suppliers: {
        type: Array,
        required: true,
    },
});

const form = useForm({
    name: '',
    description: '',
    price: '',
    stock: '',
    barcode: '',
    category_id: '',
    supplier_id: '',
    status: 'active',
    image: null,
});

const submit = () => {
    form.post(route('products.store'), {
        preserveScroll: true,
        preserveState: true,
    });
};
</script> 