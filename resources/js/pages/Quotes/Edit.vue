<template>
    <Head :title="'Edit Quote #' + quote.id" />

    <AppLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Edit Quote #{{ quote.id }}
                </h2>
                <div class="flex gap-2">
                    <Link
                        :href="route('quotes.show', quote.id)"
                        class="px-4 py-2 bg-gray-500 dark:bg-gray-600 text-white rounded-lg hover:bg-gray-600 dark:hover:bg-gray-500"
                    >
                        Cancel
                    </Link>
                    <button
                        @click="saveQuote"
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
                        <!-- Customer and Quote Info -->
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

                            <!-- Quote Information -->
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                <h3 class="text-lg font-semibold mb-4">Quote Information</h3>
                                <div class="space-y-4">
                                    <div class="mb-4">
                                        <p><span class="font-medium">Issued By:</span> {{ quote.user.name }}</p>
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
                                            <option value="shopee">Shopee</option>
                                            <option value="tiktok">Tiktok</option>
                                            <option value="lazada">Lazada</option>
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
                                    <div class="mb-4">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                                        <select
                                            v-model="form.status"
                                            class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 shadow-sm py-2 px-3"
                                        >
                                            <option value="draft">Draft</option>
                                            <option value="sent">Sent</option>
                                            <option value="accepted">Accepted</option>
                                            <option value="rejected">Rejected</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quote Items -->
                        <div class="mb-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold">Quote Items</h3>
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
                                                <input
                                                    type="number"
                                                    :value="item.price"
                                                    @input="e => { item.price = e.target.value; updateItemTotal(index); }"
                                                    min="0"
                                                    step="0.01"
                                                    class="block w-24 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 shadow-sm py-1 px-2"
                                                >
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

                        <!-- Quote Summary -->
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                            <h3 class="text-lg font-semibold mb-4">Quote Summary</h3>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span>Subtotal</span>
                                    <span>{{ currency }}{{ calculateSubtotal }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Tax ({{ form.tax_percentage }}%)</span>
                                    <div class="flex gap-2 items-center">
                                        <input
                                            type="number"
                                            v-model.number="form.tax_percentage"
                                            min="0"
                                            max="100"
                                            step="0.01"
                                            class="w-20 text-right rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 shadow-sm"
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
                                        class="mt-1 block w-24 text-right rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 shadow-sm"
                                        @input="updatePaymentAmounts"
                                    >
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
                    </div>
                </div>
            </div>
        </div>

        <!-- Fixed Bottom Bar -->
        <div class="fixed bottom-0 left-0 right-0 py-4 px-6 bg-white dark:bg-gray-800 border-t dark:border-gray-700 shadow-lg">
            <div class="max-w-7xl mx-auto flex justify-end gap-4">
                <Link
                    :href="route('quotes.show', quote.id)"
                    class="px-4 py-2 bg-gray-500 dark:bg-gray-600 text-white rounded-lg hover:bg-gray-600 dark:hover:bg-gray-500"
                >
                    Cancel
                </Link>
                <button
                    @click="saveQuote"
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
import { ref, computed, watch, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import debounce from 'lodash/debounce';

const props = defineProps({
    quote: {
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
        default: 0
    }
});

const page = usePage();
const currency = computed(() => page.props.settings?.currency || 'USD');

const processing = ref(false);
const showCustomerModal = ref(false);
const showProductModal = ref(false);
const customerSearch = ref('');
const productSearch = ref('');
const selectedCustomer = ref(props.quote.customer);

// Form data
const form = ref({
    customer_id: props.quote.customer?.id || null,
    items: props.quote.items.map(item => ({
        id: item.id,
        product_id: item.product_id,
        product_name: item.product_name,
        quantity: item.quantity,
        price: parseFloat(item.price.toString().replace(/,/g, '')).toFixed(2),
        total: parseFloat(item.total.toString().replace(/,/g, '')).toFixed(2),
        remark: item.remark || '',
        status: props.quote.status || 'draft'
    })),
    payment_method: props.quote.payment_method,
    delivery_method: props.quote.delivery_method,
    delivery_cost: parseFloat(props.quote.delivery_cost.toString().replace(/,/g, '')),
    remarks: props.quote.remarks,
    tax_percentage: props.quote.tax_percentage || props.tax_percentage,
    discount: parseFloat(props.quote.discount?.toString().replace(/,/g, '') || '0'),
    status: props.quote.status || 'draft'
});

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
    return props.customers.filter(customer => 
        (customer.name && customer.name.toLowerCase().includes(search)) ||
        (customer.email && customer.email.toLowerCase().includes(search))
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
    const price = parseFloat(item.price.toString().replace(/,/g, ''));
    const quantity = parseInt(item.quantity);
    item.total = (quantity * price).toFixed(2);
};

const updatePaymentAmounts = () => {
    // Quotes do not have payment tracking
};

// Add watchers for price and quantity changes
watch(() => form.value.items, (items) => {
    items.forEach((item, index) => {
        watch(() => item.price, () => updateItemTotal(index));
        watch(() => item.quantity, () => updateItemTotal(index));
    });
}, { deep: true, immediate: true });

// Initialize totals on mount
onMounted(() => {
    form.value.items.forEach((_, index) => {
        updateItemTotal(index);
    });
});

const removeItem = (index) => {
    form.value.items.splice(index, 1);
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
        remark: '',
        status: 'draft'
    });
    showProductModal.value = false;
};

const searchCustomers = debounce(() => {
    // If needed, you can add API call here to search customers
}, 300);

const searchProducts = debounce(() => {
    // If needed, you can add API call here to search products
}, 300);

const saveQuote = () => {
    if (processing.value) return;
    processing.value = true;
    router.put(route('quotes.update', props.quote.id), form.value, {
        preserveScroll: true,
        onSuccess: () => {
            processing.value = false;
            router.visit(route('quotes.show', props.quote.id));
        },
        onError: () => {
            processing.value = false;
        }
    });
};
</script> 