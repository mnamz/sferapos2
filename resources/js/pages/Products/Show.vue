<template>
    <AppLayout
        :breadcrumbs="[
            { name: 'Products', href: route('products.index') },
            { name: product.name, href: route('products.show', product.id) },
        ]"
    >
        <div class="container mx-auto px-4 py-6 sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800">
                <div class="p-6">
                    <div class="flex flex-col gap-8 md:flex-row">
                        <!-- Product Image -->
                        <div class="md:w-1/3">
                            <div class="aspect-square overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-700">
                                <img v-if="product.image_url" :src="product.image_url" :alt="product.name" class="h-full w-full object-cover" />
                                <div v-else class="flex h-full w-full items-center justify-center">
                                    <svg class="h-16 w-16 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                                        />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Product Details -->
                        <div class="md:w-2/3">
                            <div class="flex items-start justify-between">
                                <div>
                                    <h1 class="mb-2 text-2xl font-bold text-gray-900 dark:text-white">{{ product.name }}</h1>
                                    <p class="mb-4 text-gray-600 dark:text-gray-300">{{ product.description }}</p>
                                </div>
                                <span
                                    :class="[
                                        'rounded-full px-3 py-1 text-sm font-semibold',
                                        product.status === 'active'
                                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                            : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                    ]"
                                >
                                    {{ product.status }}
                                </span>
                            </div>

                            <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">
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
                                    class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-white transition hover:bg-indigo-700"
                                >
                                    Edit Product
                                </Link>
                                <Link
                                    :href="route('products.index')"
                                    class="inline-flex items-center rounded-md bg-gray-100 px-4 py-2 text-gray-700 transition hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                                >
                                    Back to Products
                                </Link>
                            </div>

                            <!-- Stock Management Section -->
                            <div v-if="!roles.includes('staff')" class="mt-8 border-t border-gray-200 pt-8 dark:border-gray-700">
                                <h2 class="mb-6 text-lg font-semibold text-gray-900 dark:text-white">Stock Management</h2>

                                <!-- Serial Management Panel (for serial-tracked products) -->
                                <template v-if="product.serial_tracked">
                                    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                                        {{ product.serials?.length ?? 0 }} serial{{ (product.serials?.length ?? 0) === 1 ? '' : 's' }} available
                                    </p>

                                    <p
                                        v-if="(product.pending_serial_count ?? 0) > 0"
                                        class="mb-4 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-200"
                                    >
                                        {{ product.pending_serial_count }} unit{{ product.pending_serial_count === 1 ? '' : 's' }} awaiting serial entry — key in {{ product.pending_serial_count === 1 ? 'its serial' : 'their serials' }} below to make {{ product.pending_serial_count === 1 ? 'it' : 'them' }} sellable.
                                    </p>

                                    <p v-if="!isAdmin" class="mb-4 text-sm text-gray-500 italic dark:text-gray-400">
                                        Serial numbers are managed by administrators.
                                    </p>

                                    <!-- Add Serials Form (admin only) -->
                                    <form v-if="isAdmin" @submit.prevent="addSerials" class="space-y-3">
                                        <div>
                                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                Add Serial Numbers (one per line — paste or scan)
                                            </label>
                                            <textarea
                                                v-model="serialInput"
                                                rows="4"
                                                placeholder="Paste or scan one serial per line"
                                                class="mt-1 block w-full rounded-md border-gray-300 px-4 py-2.5 font-mono shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700"
                                                @keydown.enter.exact.prevent="captureScan"
                                            ></textarea>
                                        </div>
                                        <div class="flex justify-end">
                                            <button
                                                type="submit"
                                                class="inline-flex items-center rounded-md bg-indigo-600 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-indigo-700"
                                                :disabled="serialForm.processing"
                                            >
                                                Add Serials
                                            </button>
                                        </div>
                                    </form>

                                    <!-- Serial List -->
                                    <div v-if="product.serials?.length" class="mt-6">
                                        <h3 class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Available Serials</h3>
                                        <ul
                                            class="divide-y divide-gray-200 rounded-md border border-gray-200 dark:divide-gray-700 dark:border-gray-700"
                                        >
                                            <li
                                                v-for="s in product.serials"
                                                :key="s.id"
                                                class="flex items-center justify-between px-4 py-2 font-mono text-sm text-gray-900 dark:text-white"
                                            >
                                                <span>{{ s.serial_number }}</span>
                                                <button
                                                    v-if="isAdmin"
                                                    type="button"
                                                    class="ml-4 text-red-500 transition hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                                                    title="Remove serial"
                                                    @click="removeSerial(s)"
                                                >
                                                    ×
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
                                    <p v-else class="mt-4 text-sm text-gray-500 dark:text-gray-400">No serials on hand.</p>
                                </template>

                                <!-- Standard Stock Adjustment Form (for non-serial-tracked products) -->
                                <template v-else>
                                    <!-- Success Message -->
                                    <div
                                        v-if="successMessage"
                                        class="mt-4 rounded-md bg-green-100 p-4 text-green-700 dark:bg-green-900 dark:text-green-200"
                                    >
                                        {{ successMessage }}
                                    </div>
                                    <form @submit.prevent="submit" class="space-y-6">
                                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                            <div>
                                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                    Adjustment Type
                                                </label>
                                                <select
                                                    v-model="form.type"
                                                    class="mt-1 block w-full rounded-md border-gray-300 px-4 py-2.5 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700"
                                                >
                                                    <option value="restock">Restock</option>
                                                    <option value="withdraw">Withdraw</option>
                                                </select>
                                            </div>

                                            <div>
                                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"> Quantity </label>
                                                <input
                                                    type="number"
                                                    v-model="form.quantity"
                                                    min="1"
                                                    class="mt-1 block w-full rounded-md border-gray-300 px-4 py-2.5 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700"
                                                />
                                            </div>
                                        </div>

                                        <div>
                                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"> Notes (Optional) </label>
                                            <textarea
                                                v-model="form.notes"
                                                rows="3"
                                                class="mt-1 block w-full rounded-md border-gray-300 px-4 py-2.5 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700"
                                                placeholder="Add any notes about this stock adjustment..."
                                            ></textarea>
                                        </div>

                                        <div class="flex justify-end pt-2">
                                            <button
                                                type="submit"
                                                class="inline-flex items-center rounded-md bg-indigo-600 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-indigo-700"
                                                :disabled="form.processing"
                                            >
                                                {{ form.type === 'restock' ? 'Restock' : 'Withdraw' }} Stock
                                            </button>
                                        </div>
                                    </form>
                                </template>
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
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const page = usePage();
const roles = page.props.auth?.roles || [];
const isAdmin = roles.includes('admin');

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
});

// Standard stock adjustment form (non-serial-tracked products)
const form = useForm({
    type: 'restock',
    quantity: 1,
    notes: '',
});

const successMessage = ref('');

const submit = () => {
    form.post(route('products.adjust-stock', props.product.id), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            successMessage.value = 'Stock updated successfully';
            setTimeout(() => (successMessage.value = ''), 3000);
        },
    });
};

// Serial management (serial-tracked products)
const serialInput = ref('');
const serialForm = useForm({ serials: [] });

// Scan guns end their transmission with Enter — prevent a newline being added
// by the keydown handler and instead ensure the existing line is complete so
// the next scan starts on a fresh line.
function captureScan() {
    if (!serialInput.value.endsWith('\n')) {
        serialInput.value += '\n';
    }
}

function addSerials() {
    const serials = serialInput.value
        .split('\n')
        .map((s) => s.trim())
        .filter(Boolean);
    if (!serials.length) return;
    serialForm.serials = serials;
    serialForm.post(route('products.serials.store', props.product.id), {
        preserveScroll: true,
        onSuccess: () => {
            serialInput.value = '';
            serialForm.reset();
        },
    });
}

function removeSerial(serial) {
    router.delete(route('products.serials.destroy', [props.product.id, serial.id]), {
        preserveScroll: true,
    });
}
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
