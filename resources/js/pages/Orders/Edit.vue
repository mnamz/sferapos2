<template>
    <Head :title="'Edit Order #' + order.id" />

    <AppLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Edit Order #{{ order.id }}
                </h2>
                <div class="flex gap-2">
                    <Link
                        :href="route('orders.show', order.id)"
                        class="px-4 py-2 bg-gray-500 dark:bg-gray-600 text-white rounded-lg hover:bg-gray-600 dark:hover:bg-gray-500"
                    >
                        Cancel
                    </Link>
                    <button
                        @click="saveOrder"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
                        :disabled="processing"
                    >
                        {{ processing ? 'Saving...' : 'Save Changes' }}
                    </button>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Success Message -->
                <div v-if="$page.props.success" class="mb-4">
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
                        {{ $page.props.success }}
                    </div>
                </div>

                <!-- Error Message -->
                <div v-if="$page.props.error" class="mb-4">
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
                        {{ $page.props.error }}
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 pb-24 text-gray-900 dark:text-gray-100">
                        <!-- Customer and Order Info -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <!-- Customer Information -->
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-lg font-semibold">Customer Information</h3>
                                    <button
                                        @click="showCustomerModal = true"
                                        class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700"
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
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                <h3 class="text-lg font-semibold mb-4">Order Information</h3>
                                <div class="space-y-4">
                                    <div class="mb-4">
                                        <p><span class="font-medium">Salesperson:</span> {{ order.cashier.name }}</p>
                                    </div>
                                    <div class="mb-4">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Payment Method</label>
                                        <select
                                            v-model="form.payment_method"
                                            class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 shadow-sm py-2 px-3"
                                        >
                                            <option value="cash">Cash</option>
                                            <option value="card">Card</option>
                                            <option value="bank_transfer">Bank Transfer</option>
                                        </select>
                                    </div>
                                    <div class="mb-4">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Delivery Method</label>
                                        <select
                                            v-model="form.delivery_method"
                                            class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 shadow-sm py-2 px-3"
                                        >
                                            <option value="pickup">Pickup</option>
                                            <option value="delivery">Delivery</option>
                                            <option value="walk-in">Walk-in</option>
                                        </select>
                                    </div>
                                    <div class="mb-4">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Remarks</label>
                                        <textarea
                                            v-model="form.remarks"
                                            rows="2"
                                            class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 shadow-sm py-2 px-3"
                                        ></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Order Items -->
                        <div class="mb-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold">Order Items</h3>
                                <button
                                    @click="showProductModal = true"
                                    class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700"
                                >
                                    Add Item
                                </button>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Product</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Price</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Quantity</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Remark</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                                        <tr v-for="(item, index) in form.items" :key="index">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                                {{ item.product_name }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                                {{ currency }}{{ item.price }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                                <input
                                                    type="number"
                                                    v-model="item.quantity"
                                                    min="1"
                                                    class="block w-20 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 shadow-sm py-1 px-2"
                                                    @change="updateItemTotal(index)"
                                                >
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                                {{ currency }}{{ item.total }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                                <input
                                                    type="text"
                                                    v-model="item.remark"
                                                    class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 shadow-sm py-1 px-2"
                                                    placeholder="Remark (optional)"
                                                >
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                                <button
                                                    @click="removeItem(index)"
                                                    class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                                                >
                                                    Remove
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Order Summary -->
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                            <h3 class="text-lg font-semibold mb-4">Order Summary</h3>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span>Subtotal</span>
                                    <span>{{ currency }}{{ calculateSubtotal }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Tax ({{ taxPercentage }}%)</span>
                                    <span>{{ currency }}{{ calculateTax }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Delivery Cost</span>
                                    <input
                                        type="number"
                                        v-model="form.delivery_cost"
                                        min="0"
                                        step="0.01"
                                        class="mt-1 block w-24 text-right rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 shadow-sm"
                                    >
                                </div>
                                <div class="flex justify-between font-semibold border-t dark:border-gray-600 pt-2">
                                    <span>Total</span>
                                    <span>{{ currency }}{{ calculateTotal }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Information -->
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg mt-6">
                            <h3 class="text-lg font-semibold mb-4">Payment Information</h3>
                            <div class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block font-medium mb-1">Total Amount</label>
                                        <div class="text-2xl font-bold text-gray-900 dark:text-white">
                                            {{ currency }}{{ calculateTotal }}
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Paid Amount</label>
                                        <input
                                            type="number"
                                            v-model="form.paid_amount"
                                            min="0"
                                            step="0.01"
                                            class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 shadow-sm text-2xl font-bold py-2 px-3"
                                            @input="updatePaymentAmounts"
                                        >
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4">
                                    <div v-if="form.due_amount > 0" class="bg-red-50 dark:bg-red-900/30 p-3 rounded-lg">
                                        <label class="block text-sm font-medium text-red-700 dark:text-red-200 mb-1">Due Amount</label>
                                        <div class="text-xl font-bold text-red-700 dark:text-red-200">
                                            {{ currency }}{{ form.due_amount }}
                                        </div>
                                    </div>
                                    <div v-if="form.change_amount > 0" class="bg-green-50 dark:bg-green-900/30 p-3 rounded-lg">
                                        <label class="block text-sm font-medium text-green-700 dark:text-green-200 mb-1">Change</label>
                                        <div class="text-xl font-bold text-green-700 dark:text-green-200">
                                            {{ currency }}{{ form.change_amount }}
                                        </div>
                                    </div>
                                </div>

                                <div class="pt-4">
                                    <label class="block font-medium mb-1">Payment Status</label>
                                    <div class="text-lg">
                                        <span :class="{
                                            'px-3 py-1 rounded-full': true,
                                            'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': form.paid_amount >= calculateTotal,
                                            'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': form.paid_amount > 0 && form.paid_amount < calculateTotal,
                                            'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': form.paid_amount <= 0
                                        }">
                                            {{ form.paid_amount >= calculateTotal ? 'Paid' : 
                                               form.paid_amount > 0 ? 'Partial' : 'Pending' }}
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
        <div class="fixed bottom-0 left-0 right-0 py-4 px-6 bg-white dark:bg-gray-800 border-t dark:border-gray-700 shadow-lg">
            <div class="max-w-7xl mx-auto flex justify-end gap-4">
                <Link
                    :href="route('orders.show', order.id)"
                    class="px-4 py-2 bg-gray-500 dark:bg-gray-600 text-white rounded-lg hover:bg-gray-600 dark:hover:bg-gray-500"
                >
                    Cancel
                </Link>
                <button
                    @click="saveOrder"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
                    :disabled="processing"
                >
                    {{ processing ? 'Saving...' : 'Save Changes' }}
                </button>
            </div>
        </div>

        <!-- Customer Selection Modal -->
        <Modal :show="showCustomerModal" @close="showCustomerModal = false">
            <div class="p-6">
                <h2 class="text-lg font-semibold mb-4">Select Customer</h2>
                <div class="mb-4">
                    <input
                        type="text"
                        v-model="customerSearch"
                        placeholder="Search customers..."
                        class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 shadow-sm py-2 px-3"
                        @input="searchCustomers"
                    >
                </div>
                <div class="max-h-96 overflow-y-auto">
                    <div
                        v-for="customer in filteredCustomers"
                        :key="customer.id"
                        class="p-3 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer rounded-lg"
                        @click="selectCustomer(customer)"
                    >
                        <div class="font-medium">{{ customer.name }}</div>
                        <div class="text-sm text-gray-500">{{ customer.email }}</div>
                    </div>
                    <div
                        class="p-3 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer rounded-lg"
                        @click="selectCustomer(null)"
                    >
                        <div class="font-medium">Walk-in Customer</div>
                    </div>
                </div>
            </div>
        </Modal>

        <!-- Product Selection Modal -->
        <Modal :show="showProductModal" @close="showProductModal = false">
            <div class="p-6">
                <h2 class="text-lg font-semibold mb-4">Add Product</h2>
                <div class="mb-4">
                    <input
                        type="text"
                        v-model="productSearch"
                        placeholder="Search products..."
                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 shadow-sm"
                        @input="searchProducts"
                    >
                </div>
                <div class="max-h-96 overflow-y-auto">
                    <div
                        v-for="product in filteredProducts"
                        :key="product.id"
                        class="p-3 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer rounded-lg"
                        @click="addProduct(product)"
                    >
                        <div class="font-medium">{{ product.name }}</div>
                        <div class="text-sm text-gray-500">
                            Price: {{ currency }}{{ product.price }} | Stock: {{ product.stock }}
                        </div>
                    </div>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import debounce from 'lodash/debounce';

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
    }
});

const page = usePage();
const currency = computed(() => page.props.settings?.currency || 'USD');
const taxPercentage = computed(() => page.props.settings?.tax_percentage || 0);

const processing = ref(false);
const showCustomerModal = ref(false);
const showProductModal = ref(false);
const customerSearch = ref('');
const productSearch = ref('');
const selectedCustomer = ref(props.order.customer);

// Form data
const form = ref({
    customer_id: props.order.customer?.id || null,
    items: props.order.items.map(item => ({
        id: item.id,
        product_id: item.product_id,
        product_name: item.product_name,
        quantity: item.quantity,
        price: parseFloat(item.price),
        total: parseFloat(item.total),
        remark: item.remark || ''
    })),
    payment_method: props.order.payment_method,
    delivery_method: props.order.delivery_method,
    delivery_cost: parseFloat(props.order.delivery_cost),
    paid_amount: parseFloat(props.order.paid_amount),
    due_amount: parseFloat(props.order.due_amount),
    change_amount: parseFloat(props.order.change_amount),
    remarks: props.order.remarks
});

// Computed properties for calculations
const calculateSubtotal = computed(() => {
    return form.value.items.reduce((sum, item) => sum + parseFloat(item.total), 0).toFixed(2);
});

const calculateTax = computed(() => {
    return (parseFloat(calculateSubtotal.value) * (taxPercentage.value / 100)).toFixed(2);
});

const calculateTotal = computed(() => {
    return (
        parseFloat(calculateSubtotal.value) +
        parseFloat(calculateTax.value) +
        parseFloat(form.value.delivery_cost)
    ).toFixed(2);
});

// Filtered lists
const filteredCustomers = computed(() => {
    if (!customerSearch.value) return props.customers;
    const search = customerSearch.value.toLowerCase();
    return props.customers.filter(customer => 
        customer.name.toLowerCase().includes(search) ||
        customer.email.toLowerCase().includes(search)
    );
});

const filteredProducts = computed(() => {
    if (!productSearch.value) return props.products;
    const search = productSearch.value.toLowerCase();
    return props.products.filter(product => 
        product.name.toLowerCase().includes(search)
    );
});

// Methods
const updateItemTotal = (index) => {
    const item = form.value.items[index];
    item.total = (item.quantity * item.price).toFixed(2);
    updatePaymentAmounts();
};

const updatePaymentAmounts = () => {
    const total = parseFloat(calculateTotal.value);
    const paid = parseFloat(form.value.paid_amount);
    
    if (paid >= total) {
        form.value.due_amount = 0;
        form.value.change_amount = (paid - total).toFixed(2);
    } else {
        form.value.due_amount = (total - paid).toFixed(2);
        form.value.change_amount = 0;
    }
};

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
    form.value.items.push({
        product_id: product.id,
        product_name: product.name,
        quantity: 1,
        price: parseFloat(product.price),
        total: parseFloat(product.price),
        remark: ''
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
        }
    });
};
</script> 