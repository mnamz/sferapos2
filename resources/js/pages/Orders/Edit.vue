<template>
    <Head :title="'Edit Order #' + order.id" />

    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200">Edit Order #{{ order.id }}</h2>
                <div class="flex gap-2">
                    <Link
                        :href="route('orders.show', order.id)"
                        class="rounded-lg bg-gray-500 px-4 py-2 text-white hover:bg-gray-600 dark:bg-gray-600 dark:hover:bg-gray-500"
                    >
                        Cancel
                    </Link>
                    <button @click="saveOrder" class="rounded-lg bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700" :disabled="processing">
                        {{ processing ? 'Saving...' : 'Save Changes' }}
                    </button>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- Success Message -->
                <div v-if="$page.props.success" class="mb-4">
                    <div class="relative rounded border border-green-400 bg-green-100 px-4 py-3 text-green-700">
                        {{ $page.props.success }}
                    </div>
                </div>

                <!-- Error Message -->
                <div v-if="$page.props.error" class="mb-4">
                    <div class="relative rounded border border-red-400 bg-red-100 px-4 py-3 text-red-700">
                        {{ $page.props.error }}
                    </div>
                </div>

                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                    <div class="p-6 pb-24 text-gray-900 dark:text-gray-100">
                        <!-- Customer and Order Info -->
                        <div class="mb-6 grid grid-cols-1 gap-6 md:grid-cols-2">
                            <!-- Customer Information -->
                            <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-700">
                                <div class="mb-4 flex items-center justify-between">
                                    <h3 class="text-lg font-semibold">Customer Information</h3>
                                    <button
                                        @click="showCustomerModal = true"
                                        class="rounded-lg bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700"
                                    >
                                        Change Customer
                                    </button>
                                </div>
                                <div v-if="selectedCustomer">
                                    <p><span class="font-medium">Name:</span> {{ selectedCustomer.name }}</p>
                                    <p><span class="font-medium">Email:</span> {{ selectedCustomer.email }}</p>
                                    <p><span class="font-medium">Phone:</span> {{ selectedCustomer.phone }}</p>
                                    <p><span class="font-medium">Address:</span> {{ selectedCustomer.address }}</p>
                                </div>
                                <p v-else class="text-gray-500 dark:text-gray-400">Walk-in Customer</p>
                            </div>

                            <!-- Order Information -->
                            <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-700">
                                <h3 class="mb-4 text-lg font-semibold">Order Information</h3>
                                <div class="space-y-4">
                                    <div class="mb-4">
                                        <p><span class="font-medium">Salesperson:</span> {{ order.cashier.name }}</p>
                                    </div>
                                    <div class="mb-4">
                                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Payment Method</label>
                                        <select
                                            v-model="form.payment_method"
                                            class="block w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-indigo-600 dark:focus:ring-indigo-600"
                                        >
                                            <option value="cash">Cash</option>
                                            <option value="card">Card</option>
                                            <option value="e-wallet">E-Wallet</option>
                                            <option value="online_transfer">Online Transfer</option>
                                        </select>
                                    </div>
                                    <div class="mb-4">
                                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Delivery Method</label>
                                        <select
                                            v-model="form.delivery_method"
                                            class="block w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-indigo-600 dark:focus:ring-indigo-600"
                                        >
                                            <option value="pickup">Pickup</option>
                                            <option value="delivery">Delivery</option>
                                            <option value="walk-in">Walk-in</option>
                                            <option value="shopee">Shopee</option>
                                            <option value="tiktok">Tiktok</option>
                                            <option value="lazada">Lazada</option>
                                        </select>
                                    </div>
                                    <div class="mb-4">
                                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Remarks</label>
                                        <textarea
                                            v-model="form.remarks"
                                            rows="2"
                                            class="block w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-indigo-600 dark:focus:ring-indigo-600"
                                        ></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Order Items -->
                        <div class="mb-6">
                            <div class="mb-4 flex items-center justify-between">
                                <h3 class="text-lg font-semibold">Order Items</h3>
                                <button @click="showProductModal = true" class="rounded-lg bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700">
                                    Add Item
                                </button>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-300"
                                            >
                                                Product
                                            </th>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-300"
                                            >
                                                Price
                                            </th>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-300"
                                            >
                                                Quantity
                                            </th>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-300"
                                            >
                                                Total
                                            </th>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-300"
                                            >
                                                Remark
                                            </th>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-300"
                                            >
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                                        <template v-for="(item, index) in form.items" :key="index">
                                            <tr>
                                                <td class="px-6 py-4 text-sm whitespace-nowrap text-gray-900 dark:text-gray-100">
                                                    {{ item.product_name }}
                                                </td>
                                                <td class="px-6 py-4 text-sm whitespace-nowrap text-gray-900 dark:text-gray-100">
                                                    <input
                                                        type="number"
                                                        :value="item.price"
                                                        @input="
                                                            (e) => {
                                                                item.price = e.target.value;
                                                                updateItemTotal(index);
                                                            }
                                                        "
                                                        min="0"
                                                        step="0.01"
                                                        class="block w-24 rounded-md border-gray-300 px-2 py-1 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-indigo-600 dark:focus:ring-indigo-600"
                                                    />
                                                </td>
                                                <td class="px-6 py-4 text-sm whitespace-nowrap text-gray-900 dark:text-gray-100">
                                                    <input
                                                        type="number"
                                                        v-model="item.quantity"
                                                        min="1"
                                                        :readonly="item.serial_tracked"
                                                        :class="[
                                                            'block w-20 rounded-md border-gray-300 px-2 py-1 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-indigo-600 dark:focus:ring-indigo-600',
                                                            item.serial_tracked ? 'cursor-not-allowed bg-gray-100 dark:bg-gray-700' : '',
                                                        ]"
                                                        @change="!item.serial_tracked && updateItemTotal(index)"
                                                    />
                                                </td>
                                                <td class="px-6 py-4 text-sm whitespace-nowrap text-gray-900 dark:text-gray-100">
                                                    {{ currency }}{{ item.total }}
                                                </td>
                                                <td class="px-6 py-4 text-sm whitespace-nowrap text-gray-900 dark:text-gray-100">
                                                    <input
                                                        type="text"
                                                        v-model="item.remark"
                                                        class="block w-full rounded-md border-gray-300 px-2 py-1 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-indigo-600 dark:focus:ring-indigo-600"
                                                        placeholder="Remark (optional)"
                                                    />
                                                </td>
                                                <td class="px-6 py-4 text-sm whitespace-nowrap text-gray-900 dark:text-gray-100">
                                                    <button
                                                        @click="removeItem(index)"
                                                        class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                                                    >
                                                        Remove
                                                    </button>
                                                </td>
                                            </tr>
                                            <tr v-if="item.serial_tracked">
                                                <td colspan="6" class="px-6 pt-1 pb-4">
                                                    <div class="space-y-1">
                                                        <input
                                                            v-model="item.serialScan"
                                                            placeholder="Scan or type serial number, press Enter"
                                                            class="w-full rounded border border-gray-300 px-2 py-1 font-mono text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                                            @keydown.enter.prevent="addSerialToItem(item)"
                                                        />
                                                        <div class="flex flex-wrap gap-1">
                                                            <span
                                                                v-for="sn in item.serials"
                                                                :key="sn"
                                                                class="flex items-center gap-1 rounded bg-gray-100 px-2 py-0.5 font-mono text-xs dark:bg-gray-700"
                                                            >
                                                                {{ sn }}
                                                                <button
                                                                    type="button"
                                                                    class="ml-1 text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400"
                                                                    @click="removeSerialFromItem(item, sn)"
                                                                >
                                                                    &times;
                                                                </button>
                                                            </span>
                                                            <span v-if="item.serials.length === 0" class="text-xs text-gray-400 dark:text-gray-500">
                                                                No serials added yet
                                                            </span>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Order Summary -->
                        <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-700">
                            <h3 class="mb-4 text-lg font-semibold">Order Summary</h3>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span>Subtotal</span>
                                    <span>{{ currency }}{{ calculateSubtotal }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Tax ({{ form.tax_percentage }}%)</span>
                                    <div class="flex items-center gap-2">
                                        <input
                                            type="number"
                                            v-model.number="form.tax_percentage"
                                            min="0"
                                            max="100"
                                            step="0.01"
                                            class="w-20 rounded-md border-gray-300 text-right shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-indigo-600 dark:focus:ring-indigo-600"
                                        />
                                        <span>{{ currency }}{{ calculateTax }}</span>
                                    </div>
                                </div>
                                <div class="flex justify-between">
                                    <span>Discount</span>
                                    <input
                                        type="number"
                                        v-model.number="form.discount"
                                        min="0"
                                        step="0.01"
                                        class="mt-1 block w-24 rounded-md border-gray-300 text-right shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-indigo-600 dark:focus:ring-indigo-600"
                                        @input="updatePaymentAmounts"
                                    />
                                </div>
                                <div class="flex justify-between">
                                    <span>Delivery Cost</span>
                                    <input
                                        type="number"
                                        v-model="form.delivery_cost"
                                        min="0"
                                        step="0.01"
                                        class="mt-1 block w-24 rounded-md border-gray-300 text-right shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-indigo-600 dark:focus:ring-indigo-600"
                                    />
                                </div>
                                <div class="flex justify-between border-t pt-2 font-semibold dark:border-gray-600">
                                    <span>Total</span>
                                    <span>{{ currency }}{{ calculateTotal }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Information -->
                        <div class="mt-6 rounded-lg bg-gray-50 p-4 dark:bg-gray-700">
                            <h3 class="mb-4 text-lg font-semibold">Payment Information</h3>
                            <div class="space-y-4">
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="mb-1 block font-medium">Total Amount</label>
                                        <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ currency }}{{ calculateTotal }}</div>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Paid Amount</label>
                                        <input
                                            type="number"
                                            v-model.number="form.paid_amount"
                                            @input="updatePaymentAmounts"
                                            min="0"
                                            step="0.01"
                                            class="block w-full rounded-md border-gray-300 px-3 py-2 text-2xl font-bold shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-indigo-600 dark:focus:ring-indigo-600"
                                        />
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-4 pt-4 md:grid-cols-2">
                                    <div v-if="form.due_amount > 0" class="rounded-lg bg-red-50 p-3 dark:bg-red-900/30">
                                        <label class="mb-1 block text-sm font-medium text-red-700 dark:text-red-200">Due Amount</label>
                                        <div class="text-xl font-bold text-red-700 dark:text-red-200">{{ currency }}{{ form.due_amount }}</div>
                                    </div>
                                    <div v-if="form.change_amount > 0" class="rounded-lg bg-green-50 p-3 dark:bg-green-900/30">
                                        <label class="mb-1 block text-sm font-medium text-green-700 dark:text-green-200">Change</label>
                                        <div class="text-xl font-bold text-green-700 dark:text-green-200">{{ currency }}{{ form.change_amount }}</div>
                                    </div>
                                </div>

                                <div class="pt-4">
                                    <label class="mb-1 block font-medium">Payment Status</label>
                                    <div class="text-lg">
                                        <span
                                            :class="{
                                                'rounded-full px-3 py-1': true,
                                                'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200':
                                                    form.paid_amount >= calculateTotal,
                                                'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200':
                                                    form.paid_amount > 0 && form.paid_amount < calculateTotal,
                                                'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': form.paid_amount <= 0,
                                            }"
                                        >
                                            {{ form.paid_amount >= calculateTotal ? 'Paid' : form.paid_amount > 0 ? 'Partial' : 'Pending' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fixed Bottom Bar -->
        <div class="fixed right-0 bottom-0 left-0 border-t bg-white px-6 py-4 shadow-lg dark:border-gray-700 dark:bg-gray-800">
            <div class="mx-auto flex max-w-7xl justify-end gap-4">
                <Link
                    :href="route('orders.show', order.id)"
                    class="rounded-lg bg-gray-500 px-4 py-2 text-white hover:bg-gray-600 dark:bg-gray-600 dark:hover:bg-gray-500"
                >
                    Cancel
                </Link>
                <button @click="saveOrder" class="rounded-lg bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700" :disabled="processing">
                    {{ processing ? 'Saving...' : 'Save Changes' }}
                </button>
            </div>
        </div>

        <!-- Customer Selection Modal -->
        <Modal :show="showCustomerModal" @close="showCustomerModal = false">
            <div class="p-6">
                <h2 class="mb-4 text-lg font-semibold">Select Customer</h2>
                <div class="mb-4">
                    <input
                        type="text"
                        v-model="customerSearch"
                        placeholder="Search customers..."
                        class="block w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-indigo-600 dark:focus:ring-indigo-600"
                        @input="searchCustomers"
                    />
                </div>
                <div class="max-h-96 overflow-y-auto">
                    <div
                        v-for="customer in filteredCustomers"
                        :key="customer.id"
                        class="cursor-pointer rounded-lg p-3 hover:bg-gray-100 dark:hover:bg-gray-700"
                        @click="selectCustomer(customer)"
                    >
                        <div class="font-medium">{{ customer.name }}</div>
                        <div class="text-sm text-gray-500">{{ customer.email }}</div>
                    </div>
                    <div class="cursor-pointer rounded-lg p-3 hover:bg-gray-100 dark:hover:bg-gray-700" @click="selectCustomer(null)">
                        <div class="font-medium">Walk-in Customer</div>
                    </div>
                </div>
            </div>
        </Modal>

        <!-- Product Selection Modal -->
        <Modal :show="showProductModal" @close="showProductModal = false">
            <div class="p-6">
                <h2 class="mb-4 text-lg font-semibold">Add Product</h2>
                <div class="mb-4">
                    <input
                        type="text"
                        v-model="productSearch"
                        placeholder="Search products..."
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-indigo-600 dark:focus:ring-indigo-600"
                        @input="searchProducts"
                    />
                </div>
                <div class="max-h-96 overflow-y-auto">
                    <div
                        v-for="product in filteredProducts"
                        :key="product.id"
                        class="cursor-pointer rounded-lg p-3 hover:bg-gray-100 dark:hover:bg-gray-700"
                        @click="addProduct(product)"
                    >
                        <div class="font-medium">{{ product.name }}</div>
                        <div class="text-sm text-gray-500">Price: {{ currency }}{{ product.price }} | Stock: {{ product.stock }}</div>
                    </div>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>

<script setup>
import Modal from '@/Components/Modal.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import debounce from 'lodash/debounce';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

const props = defineProps({
    order: {
        type: Object,
        required: true,
    },
    customers: {
        type: Array,
        default: () => [],
    },
    products: {
        type: Array,
        default: () => [],
    },
    tax_percentage: {
        type: Number,
        default: 0,
    },
});

const page = usePage();
const currency = computed(() => page.props.settings?.currency || 'USD');

const processing = ref(false);
const showCustomerModal = ref(false);
const showProductModal = ref(false);
const customerSearch = ref('');
const productSearch = ref('');
const selectedCustomer = ref(props.order.customer);

// Form data
const form = ref({
    customer_id: props.order.customer?.id || null,
    items: props.order.items.map((item) => ({
        id: item.id,
        product_id: item.product_id,
        product_name: item.product_name,
        serial_tracked: !!item.serial_tracked,
        serials: item.serials ? [...item.serials] : [],
        serialScan: '',
        quantity: item.quantity,
        price: parseFloat(item.price.toString().replace(/,/g, '')).toFixed(2),
        total: parseFloat(item.total.toString().replace(/,/g, '')).toFixed(2),
        remark: item.remark || '',
    })),
    payment_method: props.order.payment_method,
    delivery_method: props.order.delivery_method,
    delivery_cost: parseFloat(props.order.delivery_cost.toString().replace(/,/g, '')),
    paid_amount: parseFloat(props.order.paid_amount.toString().replace(/,/g, '')),
    due_amount: parseFloat(props.order.due_amount.toString().replace(/,/g, '')),
    change_amount: parseFloat(props.order.change_amount.toString().replace(/,/g, '')),
    remarks: props.order.remarks,
    tax_percentage: props.order.tax_percentage || props.tax_percentage,
    discount: parseFloat(props.order.discount?.toString().replace(/,/g, '') || '0'),
});

console.log(form.value.items);

// Computed properties for calculations
const calculateSubtotal = computed(() => {
    return form.value.items.reduce((sum, item) => sum + parseFloat(item.total), 0).toFixed(2);
});

const calculateTax = computed(() => {
    return (parseFloat(calculateSubtotal.value) * (form.value.tax_percentage / 100)).toFixed(2);
});

const calculateTotal = computed(() => {
    return (
        parseFloat(calculateSubtotal.value) +
        parseFloat(calculateTax.value) +
        parseFloat(form.value.delivery_cost) -
        parseFloat(form.value.discount)
    ).toFixed(2);
});

// Filtered lists
const filteredCustomers = computed(() => {
    if (!customerSearch.value) return props.customers;
    const search = customerSearch.value.toLowerCase();
    return props.customers.filter(
        (customer) =>
            (customer.name && customer.name.toLowerCase().includes(search)) || (customer.email && customer.email.toLowerCase().includes(search)),
    );
});

const filteredProducts = computed(() => {
    // Filter out products with no stock
    const availableProducts = props.products.filter((product) => product.stock > 0);

    if (!productSearch.value) return availableProducts;
    const search = productSearch.value.toLowerCase();
    return availableProducts.filter((product) => product.name.toLowerCase().includes(search));
});

// Methods
const updateItemTotal = (index) => {
    const item = form.value.items[index];
    const price = parseFloat(item.price.toString().replace(/,/g, ''));
    const quantity = parseInt(item.quantity);
    item.total = (quantity * price).toFixed(2);
    updatePaymentAmounts();
};

const updatePaymentAmounts = () => {
    const total = parseFloat(calculateTotal.value);
    const paid = parseFloat(form.value.paid_amount);

    // Ensure paid amount doesn't exceed total
    if (paid > total) {
        form.value.paid_amount = total;
    }

    if (paid >= total) {
        form.value.due_amount = 0;
        form.value.change_amount = (paid - total).toFixed(2);
    } else {
        form.value.due_amount = (total - paid).toFixed(2);
        form.value.change_amount = 0;
    }
};

// Add watchers for price and quantity changes
watch(
    () => form.value.items,
    (items) => {
        items.forEach((item, index) => {
            watch(
                () => item.price,
                () => updateItemTotal(index),
            );
            watch(
                () => item.quantity,
                () => updateItemTotal(index),
            );
        });
    },
    { deep: true, immediate: true },
);

// Add a watcher for paid amount changes
watch(
    () => form.value.paid_amount,
    (newValue) => {
        updatePaymentAmounts();
    },
);

// Initialize totals on mount
onMounted(() => {
    form.value.items.forEach((_, index) => {
        updateItemTotal(index);
    });
});

const removeItem = (index) => {
    form.value.items.splice(index, 1);
    updatePaymentAmounts();
};

const selectCustomer = (customer) => {
    selectedCustomer.value = customer;
    form.value.customer_id = customer?.id || null;
    showCustomerModal.value = false;
};

const addProduct = (product) => {
    const serialTracked = !!product.serial_tracked;
    form.value.items.push({
        product_id: product.id,
        product_name: product.name,
        serial_tracked: serialTracked,
        serials: [],
        serialScan: '',
        quantity: serialTracked ? 0 : 1,
        price: parseFloat(product.price),
        total: serialTracked ? 0 : parseFloat(product.price),
        remark: '',
    });
    showProductModal.value = false;
    updatePaymentAmounts();
};

const searchCustomers = debounce(() => {
    // If needed, you can add API call here to search customers
}, 300);

const searchProducts = debounce(() => {
    // If needed, you can add API call here to search products
}, 300);

const addSerialToItem = async (item) => {
    const sn = (item.serialScan || '').trim();
    if (!sn) return;
    if (item.serials.includes(sn)) {
        item.serialScan = '';
        return;
    }
    try {
        const { data } = await axios.get(route('products.serials.index', item.product_id));
        const available = data.serials.map((s) => s.serial_number);
        if (!available.includes(sn)) {
            toast.error(`Serial "${sn}" is not available for this product.`);
            item.serialScan = '';
            return;
        }
    } catch {
        toast.error('Could not verify serial number. Please try again.');
        item.serialScan = '';
        return;
    }
    item.serials.push(sn);
    item.serialScan = '';
    item.quantity = item.serials.length;
    const idx = form.value.items.indexOf(item);
    updateItemTotal(idx);
};

const removeSerialFromItem = (item, sn) => {
    item.serials = item.serials.filter((s) => s !== sn);
    item.quantity = item.serials.length;
    const idx = form.value.items.indexOf(item);
    updateItemTotal(idx);
};

const saveOrder = () => {
    if (processing.value) return;

    processing.value = true;
    router.put(route('orders.update', props.order.id), form.value, {
        preserveScroll: true,
        onSuccess: () => {
            processing.value = false;
            // Optionally redirect to show page
            router.visit(route('orders.show', props.order.id));
        },
        onError: () => {
            processing.value = false;
        },
    });
};
</script>
