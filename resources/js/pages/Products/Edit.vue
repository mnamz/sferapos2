<template>
    <Head title="Edit Product" />

    <AppLayout
        :breadcrumbs="[
            { name: 'Products', href: route('products.index') },
            { name: 'Edit', href: route('products.edit', product.id) },
        ]"
    >
        <div class="container mx-auto px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div class="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-gray-800">
                    <div class="p-4 text-gray-900 sm:p-6 lg:p-8 dark:text-gray-100">
                        <form @submit.prevent="submit">
                            <div class="grid grid-cols-1 gap-4 sm:gap-6 lg:grid-cols-2">
                                <div class="space-y-4 sm:space-y-6">
                                    <!-- Item Type -->
                                    <div>
                                        <Label class="text-gray-900 dark:text-gray-100">Item Type</Label>
                                        <div class="mt-1 grid grid-cols-3 gap-2">
                                            <button
                                                v-for="t in itemTypes"
                                                :key="t.value"
                                                type="button"
                                                @click="setType(t.value)"
                                                :class="[
                                                    'rounded-lg border px-3 py-2 text-left text-sm transition',
                                                    form.type === t.value
                                                        ? 'border-indigo-600 bg-indigo-50 ring-2 ring-indigo-600 dark:bg-indigo-900/30'
                                                        : 'border-gray-300 hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-700',
                                                ]"
                                            >
                                                <div class="font-medium">{{ t.label }}</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ t.hint }}</div>
                                            </button>
                                        </div>
                                        <InputError :message="form.errors.type" class="mt-2" />
                                    </div>

                                    <!-- Name -->
                                    <div>
                                        <Label for="name" class="text-gray-900 dark:text-gray-100">Name</Label>
                                        <Input
                                            id="name"
                                            v-model="form.name"
                                            type="text"
                                            class="mt-1 block w-full dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
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
                                            class="mt-1 block w-full dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                            required
                                        />
                                        <InputError :message="form.errors.price" class="mt-2" />
                                    </div>

                                    <!-- Cost Price -->
                                    <div>
                                        <Label for="cost_price" class="text-gray-900 dark:text-gray-100">Cost Price</Label>
                                        <Input
                                            id="cost_price"
                                            v-model="form.cost_price"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="mt-1 block w-full dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                            required
                                        />
                                        <InputError :message="form.errors.cost_price" class="mt-2" />
                                    </div>

                                    <!-- Serial Number Tracking -->
                                    <div v-if="form.type !== 'service'">
                                        <Label for="serial_tracked" class="flex cursor-pointer items-center gap-2 text-gray-900 dark:text-gray-100">
                                            <Checkbox id="serial_tracked" v-model="form.serial_tracked" />
                                            <span>Track serial / IMEI numbers</span>
                                        </Label>
                                        <InputError :message="form.errors.serial_tracked" class="mt-2" />
                                    </div>

                                    <!-- Stock -->
                                    <div v-if="form.type !== 'service'">
                                        <Label for="stock" class="text-gray-900 dark:text-gray-100">Stock</Label>
                                        <Input
                                            id="stock"
                                            v-model="form.stock"
                                            type="number"
                                            min="0"
                                            :readonly="form.serial_tracked"
                                            :class="[
                                                'mt-1 block w-full dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100',
                                                form.serial_tracked ? 'bg-muted text-muted-foreground cursor-not-allowed' : '',
                                            ]"
                                            required
                                        />
                                        <p v-if="form.serial_tracked" class="text-muted-foreground mt-1 text-xs">
                                            Stock is derived from serial numbers. Saving resets the count to 0 — add serials on the product page to build stock back up.
                                        </p>
                                        <InputError :message="form.errors.stock" class="mt-2" />
                                    </div>

                                    <!-- Barcode -->
                                    <div>
                                        <Label for="barcode" class="text-gray-900 dark:text-gray-100">Barcode</Label>
                                        <Input
                                            id="barcode"
                                            v-model="form.barcode"
                                            type="text"
                                            class="mt-1 block w-full dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
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
                                            class="mt-1 block w-full dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
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
                                            class="mt-1 block w-full dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                            rows="3"
                                        />
                                        <InputError :message="form.errors.description" class="mt-2" />
                                    </div>

                                    <!-- SKU / Brand -->
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <Label for="sku" class="text-gray-900 dark:text-gray-100">SKU / Part No.</Label>
                                            <Input id="sku" v-model="form.sku" type="text" class="mt-1 block w-full dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100" />
                                            <InputError :message="form.errors.sku" class="mt-2" />
                                        </div>
                                        <div>
                                            <Label for="brand" class="text-gray-900 dark:text-gray-100">Brand</Label>
                                            <Input id="brand" v-model="form.brand" type="text" placeholder="e.g. Apple, Samsung" class="mt-1 block w-full dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100" />
                                            <InputError :message="form.errors.brand" class="mt-2" />
                                        </div>
                                    </div>

                                    <!-- Compatible models -->
                                    <div v-if="form.type !== 'product' || form.compatible_models">
                                        <Label for="compatible_models" class="text-gray-900 dark:text-gray-100">Compatible Models</Label>
                                        <Input
                                            id="compatible_models"
                                            v-model="form.compatible_models"
                                            type="text"
                                            placeholder="e.g. iPhone 13, iPhone 13 Pro"
                                            class="mt-1 block w-full dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                        />
                                        <p class="text-muted-foreground mt-1 text-xs">Searchable at the counter, so staff can find parts by phone model.</p>
                                        <InputError :message="form.errors.compatible_models" class="mt-2" />
                                    </div>

                                    <!-- Warranty -->
                                    <div>
                                        <Label for="warranty_days" class="text-gray-900 dark:text-gray-100">Warranty (days)</Label>
                                        <Input
                                            id="warranty_days"
                                            v-model="form.warranty_days"
                                            type="number"
                                            min="0"
                                            placeholder="Leave blank for no warranty"
                                            class="mt-1 block w-full dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                        />
                                        <p class="text-muted-foreground mt-1 text-xs">Printed on the receipt with its expiry date.</p>
                                        <InputError :message="form.errors.warranty_days" class="mt-2" />
                                    </div>

                                    <!-- Supplier -->
                                    <div>
                                        <Label for="supplier_id" class="text-gray-900 dark:text-gray-100">Supplier (Optional)</Label>
                                        <Select
                                            id="supplier_id"
                                            v-model="form.supplier_id"
                                            class="mt-1 block w-full dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
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
                                            class="mt-1 block w-full dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                            required
                                        >
                                            <option value="active">Active</option>
                                            <option value="inactive">Inactive</option>
                                        </Select>
                                        <InputError :message="form.errors.status" class="mt-2" />
                                    </div>

                                    <!-- Current Image Preview -->
                                    <div v-if="product.image" class="mt-2">
                                        <Label class="text-gray-900 dark:text-gray-100">Current Image</Label>
                                        <img :src="product.image" alt="Current product image" class="mt-1 h-32 w-32 rounded-lg object-cover" />
                                    </div>

                                    <!-- Image Upload -->
                                    <div>
                                        <Label for="image" class="text-gray-900 dark:text-gray-100">Update Image</Label>
                                        <Input
                                            id="image"
                                            type="file"
                                            @input="form.image = $event.target.files[0]"
                                            accept="image/*"
                                            class="mt-1 block w-full dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                        />
                                        <InputError :message="form.errors.image" class="mt-2" />
                                    </div>
                                </div>
                            </div>

                            <div
                                class="mt-6 flex flex-col items-center justify-end gap-4 border-t border-gray-200 pt-6 sm:flex-row dark:border-gray-700"
                            >
                                <Link
                                    :href="route('products.index')"
                                    class="w-full rounded-lg bg-gray-100 px-4 py-2 text-center text-gray-600 hover:bg-gray-200 sm:w-auto dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                                >
                                    Cancel
                                </Link>
                                <Button :disabled="form.processing" class="w-full bg-indigo-600 hover:bg-indigo-700 sm:w-auto">
                                    Update Product
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
import InputError from '@/Components/InputError.vue';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select } from '@/Components/ui/select';
import { Textarea } from '@/Components/ui/textarea';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
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
    type: props.product.type || 'product',
    name: props.product.name,
    sku: props.product.sku || '',
    brand: props.product.brand || '',
    compatible_models: props.product.compatible_models || '',
    warranty_days: props.product.warranty_days ?? '',
    description: props.product.description,
    price: props.product.price,
    cost_price: props.product.cost_price,
    stock: props.product.stock,
    barcode: props.product.barcode,
    category_id: props.product.category_id,
    supplier_id: props.product.supplier_id,
    status: props.product.status === 'inactive' ? 'inactive' : 'active',
    image: null,
    serial_tracked: props.product.serial_tracked ?? false,
    _method: 'PUT',
});

const itemTypes = [
    { value: 'product', label: 'Product', hint: 'Phones, accessories' },
    { value: 'part', label: 'Spare Part', hint: 'Screens, batteries' },
    { value: 'service', label: 'Service', hint: 'Labour, no stock' },
];

const setType = (type) => {
    form.type = type;
    if (type === 'service') {
        form.serial_tracked = false;
        form.stock = 0;
        form.cost_price = form.cost_price === '' ? 0 : form.cost_price;
    }
};

const submit = () => {
    form.post(route('products.update', props.product.id), {
        preserveScroll: true,
        preserveState: true,
    });
};
</script>
