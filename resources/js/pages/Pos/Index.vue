<template>
    <Head title="Point of Sale" />

    <AppLayout :collapse-sidebar="true">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Point of Sale
            </h2>
        </template>

        <div class="py-6">
            <div class="max-w-[95%] mx-auto sm:px-4 lg:px-6">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-4">
                        <!-- Customer Selection -->
                        <div class="mb-6">
                            <div class="relative">
                                <div v-if="!selectedCustomer" class="flex gap-4">
                                    <div class="flex-1 relative customer-search">
                                        <input
                                            type="text"
                                            v-model="customerSearchQuery"
                                            @focus="showCustomerSearch = true"
                                            placeholder="Search customer by name, email, or phone..."
                                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                        />
                                        <!-- Customer Search Results -->
                                        <div v-if="showCustomerSearch" class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700">
                                            <div v-if="loadingCustomers" class="p-4 text-center text-gray-500 dark:text-gray-400">
                                                Loading customers...
                                            </div>
                                            <div v-else-if="customers.length === 0 && customerSearchQuery.length >= 2" class="p-4 text-center text-gray-500 dark:text-gray-400">
                                                No customers found
                                            </div>
                                            <div v-else class="max-h-60 overflow-y-auto">
                                                <button
                                                    v-for="customer in customers"
                                                    :key="customer.id"
                                                    @click="selectCustomer(customer)"
                                                    class="w-full px-4 py-2 text-left hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none"
                                                >
                                                    <div class="text-gray-900 dark:text-gray-100 font-medium">{{ customer.name }}</div>
                                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                                        {{ customer.email || customer.phone || 'No contact info' }}
                                                    </div>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <button
                                        @click="showCustomerSearch = true"
                                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition dark:bg-blue-500 dark:hover:bg-blue-600"
                                    >
                                        Select Customer
                                    </button>
                                </div>
                                <div v-else class="flex items-center justify-between bg-blue-50 dark:bg-blue-900/20 px-4 py-2 rounded-lg">
                                    <div>
                                        <div class="text-blue-700 dark:text-blue-300 font-medium">
                                            {{ selectedCustomer.name }}
                                        </div>
                                        <div class="text-sm text-blue-600 dark:text-blue-400">
                                            {{ selectedCustomer.email || selectedCustomer.phone || 'No contact info' }}
                                        </div>
                                    </div>
                                    <button
                                        @click="clearCustomer"
                                        class="text-blue-700 dark:text-blue-300 hover:text-blue-800 dark:hover:text-blue-200"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Search and Scan Section -->
                        <div class="mb-6">
                            <div class="flex gap-4 items-center">
                                <div class="flex-1 relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <input
                                        type="text"
                                        v-model="searchQuery"
                                        @keyup.enter="handleSearch"
                                        @keydown="handleBarcodeInput"
                                        placeholder="Search by product name or scan barcode..."
                                        class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                        ref="searchInput"
                                        :disabled="loading"
                                    />
                                    <button 
                                        v-if="searchQuery"
                                        @click="searchQuery = ''"
                                        class="absolute inset-y-0 right-0 pr-3 flex items-center"
                                    >
                                        <svg class="h-5 w-5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <!-- Search Results Count -->
                            <div v-if="searchQuery && !loading" class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                Found {{ filteredProducts.length }} {{ filteredProducts.length === 1 ? 'product' : 'products' }}
                            </div>
                        </div>

                        <!-- Main POS Interface -->
                        <div class="grid grid-cols-12 gap-4">
                            <!-- Products Section -->
                            <div class="col-span-12 lg:col-span-8">
                                <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                                    <div class="flex justify-between items-center mb-4">
                                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Products</h3>
                                        <div class="flex gap-2">
                                            <!-- Category Filter -->
                                            <select
                                                v-model="selectedCategory"
                                                class="px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                                :disabled="loading"
                                            >
                                                <option value="">All Categories</option>
                                                <option v-for="category in categories" :key="category.id" :value="category.id">
                                                    {{ category.name }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Loading State -->
                                    <div v-if="loading" class="flex justify-center items-center py-12">
                                        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 dark:border-blue-400"></div>
                                    </div>

                                    <!-- Products Grid -->
                                    <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-4">
                                        <div v-if="filteredProducts.length === 0" class="col-span-full text-center py-12">
                                            <p class="text-gray-500 dark:text-gray-400 text-lg">No products found</p>
                                            <p v-if="searchQuery" class="text-gray-400 dark:text-gray-500 text-sm mt-2">
                                                Try searching with a different term
                                            </p>
                                        </div>
                                        <div
                                            v-for="product in filteredProducts"
                                            :key="product.id"
                                            @click="addToCart(product)"
                                            class="bg-white dark:bg-gray-800 p-3 rounded-lg shadow-sm cursor-pointer hover:shadow-md transition"
                                        >
                                            <div class="aspect-square mb-2 bg-gray-100 dark:bg-gray-700 rounded-lg overflow-hidden flex items-center justify-center">
                                                <template v-if="product.image">
                                                    <img
                                                        :src="product.image"
                                                        :alt="product.name"
                                                        class="w-full h-full object-cover"
                                                    />
                                                </template>
                                                <template v-else>
                                                    <svg 
                                                        class="w-12 h-12 text-gray-400 dark:text-gray-500" 
                                                        fill="none" 
                                                        viewBox="0 0 24 24" 
                                                        stroke="currentColor"
                                                    >
                                                        <path 
                                                            stroke-linecap="round" 
                                                            stroke-linejoin="round" 
                                                            stroke-width="2" 
                                                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                                                        />
                                                    </svg>
                                                </template>
                                            </div>
                                            <h4 class="font-medium text-sm truncate text-gray-900 dark:text-gray-100" :title="product.name">{{ product.name }}</h4>
                                            <p class="text-green-600 dark:text-green-400 font-semibold">{{ currency }}{{ product.price }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Stock: {{ product.stock }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Cart Section -->
                            <div class="col-span-12 lg:col-span-4">
                                <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 sticky top-4">
                                    <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Cart</h3>
                                    
                                    <!-- Empty Cart State -->
                                    <div v-if="cart.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
                                        Cart is empty
                                    </div>
                                    
                                    <!-- Cart Items -->
                                    <div v-else class="space-y-3 mb-4 max-h-[400px] overflow-y-auto">
                                        <div
                                            v-for="(item, index) in cart"
                                            :key="index"
                                            class="flex items-center gap-3 bg-white dark:bg-gray-800 p-3 rounded-lg"
                                        >
                                            <div class="w-16 h-16 bg-gray-100 dark:bg-gray-700 rounded-lg overflow-hidden flex items-center justify-center">
                                                <template v-if="item.image">
                                                    <img
                                                        :src="item.image"
                                                        :alt="item.name"
                                                        class="w-full h-full object-cover"
                                                    />
                                                </template>
                                                <template v-else>
                                                    <svg 
                                                        class="w-8 h-8 text-gray-400 dark:text-gray-500" 
                                                        fill="none" 
                                                        viewBox="0 0 24 24" 
                                                        stroke="currentColor"
                                                    >
                                                        <path 
                                                            stroke-linecap="round" 
                                                            stroke-linejoin="round" 
                                                            stroke-width="2" 
                                                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                                                        />
                                                    </svg>
                                                </template>
                                            </div>
                                            <div class="flex-1">
                                                <h4 class="font-medium text-gray-900 dark:text-gray-100">{{ item.name }}</h4>
                                                <p class="text-green-600 dark:text-green-400">{{ currency }}{{ item.price }}</p>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <button
                                                    @click="decrementQuantity(index)"
                                                    class="w-8 h-8 flex items-center justify-center bg-gray-100 dark:bg-gray-700 rounded-lg text-gray-900 dark:text-gray-100"
                                                >
                                                    -
                                                </button>
                                                <span class="w-8 text-center text-gray-900 dark:text-gray-100">{{ item.quantity }}</span>
                                                <button
                                                    @click="incrementQuantity(index)"
                                                    class="w-8 h-8 flex items-center justify-center bg-gray-100 dark:bg-gray-700 rounded-lg text-gray-900 dark:text-gray-100"
                                                >
                                                    +
                                                </button>
                                            </div>
                                            <button
                                                @click="removeFromCart(index)"
                                                class="text-red-500 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Cart Summary -->
                                    <div v-if="cart.length > 0" class="border-t border-gray-200 dark:border-gray-700 pt-4 space-y-3">
                                        <div class="flex justify-between text-gray-900 dark:text-gray-100">
                                            <span>Subtotal</span>
                                            <span>{{ currency }}{{ cartTotal }}</span>
                                        </div>
                                        <div class="flex justify-between text-gray-900 dark:text-gray-100">
                                            <span>Tax ({{ taxPercentage }}%)</span>
                                            <span>{{ currency }}{{ tax }}</span>
                                        </div>
                                        <div class="flex justify-between items-center text-gray-900 dark:text-gray-100">
                                            <span>Delivery Cost</span>
                                            <div class="w-24">
                                                <input
                                                    type="number"
                                                    v-model="deliveryCost"
                                                    class="w-full px-2 py-1 text-right border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700"
                                                    step="0.01"
                                                    min="0"
                                                />
                                            </div>
                                        </div>
                                        <div class="flex justify-between items-center text-gray-900 dark:text-gray-100">
                                            <span>Discount</span>
                                            <div class="w-24">
                                                <input
                                                    type="number"
                                                    v-model="discount"
                                                    class="w-full px-2 py-1 text-right border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700"
                                                    step="0.01"
                                                    min="0"
                                                />
                                            </div>
                                        </div>
                                        <div class="flex justify-between font-semibold text-lg text-gray-900 dark:text-gray-100">
                                            <span>Total</span>
                                            <span>{{ currency }}{{ total }}</span>
                                        </div>

                                        <!-- Payment and Delivery Details -->
                                        <div class="border-t border-gray-200 dark:border-gray-700 pt-4 space-y-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                    Payment Method
                                                </label>
                                                <select
                                                    v-model="paymentMethod"
                                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                                >
                                                    <option value="cash">Cash</option>
                                                    <option value="card">Card</option>
                                                    <option value="bank_transfer">Bank Transfer</option>
                                                </select>
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                    Delivery Method
                                                </label>
                                                <select
                                                    v-model="deliveryMethod"
                                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                                    @change="updateDeliveryCost"
                                                >
                                                    <option value="pickup">Pickup</option>
                                                    <option value="delivery">Delivery</option>
                                                </select>
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                    Paid Amount
                                                </label>
                                                <input
                                                    type="number"
                                                    v-model="paidAmount"
                                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                                    step="0.01"
                                                    min="0"
                                                />
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                    Due Amount
                                                </label>
                                                <div class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                                    {{ currency }}{{ actualDueAmount }}
                                                </div>
                                            </div>

                                            <div v-if="changeAmount > 0" class="bg-green-50 dark:bg-green-900/20 p-3 rounded-lg">
                                                <label class="block text-sm font-medium text-green-700 dark:text-green-300 mb-1">
                                                    Change to Return
                                                </label>
                                                <div class="text-lg font-semibold text-green-700 dark:text-green-300">
                                                    {{ currency }}{{ changeAmount }}
                                                </div>
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                    Remarks
                                                </label>
                                                <textarea
                                                    v-model="remarks"
                                                    rows="2"
                                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                                    placeholder="Add any notes or special instructions..."
                                                ></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Checkout Button -->
                                    <button
                                        @click="checkout"
                                        :disabled="!cart.length || loading"
                                        class="w-full mt-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition disabled:opacity-50 disabled:cursor-not-allowed dark:bg-green-500 dark:hover:bg-green-600"
                                    >
                                        <span v-if="!loading">Checkout</span>
                                        <span v-else>Processing...</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import axios from 'axios';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

// State
const page = usePage();
const searchQuery = ref('');
const selectedCategory = ref('');
const cart = ref([]);
const searchInput = ref(null);
const barcodeBuffer = ref('');
const barcodeTimeout = ref(null);
const loading = ref(false);
const products = ref([]);
const categories = ref([]);
const selectedCustomer = ref(null);
const customerSearchQuery = ref('');
const showCustomerSearch = ref(false);
const customers = ref([]);
const loadingCustomers = ref(false);
const paymentMethod = ref('cash');
const deliveryMethod = ref('pickup');
const deliveryCost = ref(0);
const paidAmount = ref(0);
const remarks = ref('');
const discount = ref(0);

// Add currency computed property
const currency = computed(() => {
    return page.props.settings?.currency || 'USD';
});

// Add tax percentage computed property
const taxPercentage = computed(() => {
    return page.props.settings?.tax_percentage || 0;
});

// Computed properties
const filteredProducts = computed(() => {
    return products.value.filter(product => {
        const matchesSearch = product.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
                            product.barcode?.includes(searchQuery.value);
        const matchesCategory = !selectedCategory.value || product.category_id === parseInt(selectedCategory.value);
        return matchesSearch && matchesCategory;
    });
});

const cartTotal = computed(() => {
    return cart.value.reduce((total, item) => total + (item.price * item.quantity), 0).toFixed(2);
});

const tax = computed(() => {
    return (parseFloat(cartTotal.value) * (taxPercentage.value / 100)).toFixed(2);
});

const total = computed(() => {
    return (parseFloat(cartTotal.value) + parseFloat(tax.value) + parseFloat(deliveryCost.value) - parseFloat(discount.value)).toFixed(2);
});

const actualDueAmount = computed(() => {
    const due = parseFloat(total.value) - parseFloat(paidAmount.value);
    return due > 0 ? due.toFixed(2) : '0.00';
});

const changeAmount = computed(() => {
    const change = parseFloat(paidAmount.value) - parseFloat(total.value);
    return change > 0 ? change.toFixed(2) : '0.00';
});

// Methods
const loadProducts = async () => {
    try {
        loading.value = true;
        const response = await axios.get('/pos-products');
        products.value = response.data.products;
        categories.value = response.data.categories;
    } catch (error) {
        toast.error('Failed to load products');
        console.error('Error loading products:', error);
    } finally {
        loading.value = false;
    }
};

const handleSearch = () => {
    // If there are filtered products and search query is not empty
    if (filteredProducts.value.length > 0 && searchQuery.value.trim()) {
        // Add the first matching product to cart
        addToCart(filteredProducts.value[0]);
        // Clear the search query
        searchQuery.value = '';
    }
    // Focus back on the search input
    searchInput.value?.focus();
};

const handleBarcodeInput = (event) => {
    // Clear the timeout on each keypress
    if (barcodeTimeout.value) clearTimeout(barcodeTimeout.value);

    // Add the character to the buffer
    barcodeBuffer.value += event.key;

    // Set a timeout to clear the buffer
    barcodeTimeout.value = setTimeout(() => {
        // If buffer length is appropriate for a barcode
        if (barcodeBuffer.value.length >= 8) {
            // Search for product with this barcode
            const product = products.value.find(p => p.barcode === barcodeBuffer.value);
            if (product) {
                addToCart(product);
            }
        }
        barcodeBuffer.value = '';
    }, 100);
};

const addToCart = (product) => {
    const existingItem = cart.value.find(item => item.id === product.id);
    if (existingItem) {
        existingItem.quantity++;
    } else {
        cart.value.push({
            ...product,
            quantity: 1
        });
    }
};

const removeFromCart = (index) => {
    cart.value.splice(index, 1);
};

const incrementQuantity = (index) => {
    if (cart.value[index].quantity < cart.value[index].stock) {
        cart.value[index].quantity++;
    }
};

const decrementQuantity = (index) => {
    if (cart.value[index].quantity > 1) {
        cart.value[index].quantity--;
    }
};

const loadCustomers = async (search = '') => {
    try {
        loadingCustomers.value = true;
        const response = await axios.get(`/api/customers/search?q=${search}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        });
        customers.value = response.data;
    } catch (error) {
        console.error('Error details:', error.response || error);
        toast.error(error.response?.data?.message || 'Failed to load customers');
    } finally {
        loadingCustomers.value = false;
    }
};

const selectCustomer = (customer) => {
    selectedCustomer.value = customer;
    showCustomerSearch.value = false;
    customerSearchQuery.value = '';
};

const clearCustomer = () => {
    selectedCustomer.value = null;
};

const updateDeliveryCost = () => {
    deliveryCost.value = deliveryMethod.value === 'delivery' ? 10 : 0; // Example: $10 delivery fee
};

const checkout = async () => {
    try {
        loading.value = true;
        
        const orderData = {
            items: cart.value.map(item => ({
                id: item.id,
                quantity: item.quantity
            })),
            customer_id: selectedCustomer.value?.id,
            subtotal: parseFloat(cartTotal.value),
            tax: parseFloat(tax.value),
            delivery_cost: parseFloat(deliveryCost.value),
            discount: parseFloat(discount.value),
            total: parseFloat(total.value),
            paid_amount: parseFloat(paidAmount.value),
            due_amount: Math.max(0, parseFloat(actualDueAmount.value)),
            change_amount: parseFloat(changeAmount.value),
            payment_method: paymentMethod.value,
            delivery_method: deliveryMethod.value,
            remarks: remarks.value,
        };

        const response = await axios.post('/orders', orderData);
        
        if (response.data.success) {
            toast.success('Order completed successfully!');
            // Reset all fields
            cart.value = [];
            selectedCustomer.value = null;
            paidAmount.value = 0;
            deliveryCost.value = 0;
            deliveryMethod.value = 'pickup';
            paymentMethod.value = 'cash';
            remarks.value = '';
            searchInput.value?.focus();
        }
    } catch (error) {
        toast.error(error.response?.data?.message || 'Failed to process order');
        console.error('Checkout error:', error);
    } finally {
        loading.value = false;
    }
};

// Lifecycle hooks
onMounted(() => {
    searchInput.value?.focus();
    loadProducts();
    
    // Close customer search when clicking outside
    document.addEventListener('click', (e) => {
        const customerSearch = document.querySelector('.customer-search');
        if (customerSearch && !customerSearch.contains(e.target)) {
            showCustomerSearch.value = false;
        }
    });
});

// Add watcher for customer search
watch(customerSearchQuery, (newQuery) => {
    if (newQuery.length >= 2) {
        loadCustomers(newQuery);
    } else {
        customers.value = [];
    }
});
</script>

<style scoped>
/* Add any component-specific styles here */
</style> 