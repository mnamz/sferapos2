<template>
  <AppLayout :breadcrumbs="[{ name: 'Quotations', href: route('quotes.index') }, { name: 'Create', href: route('quotes.create') }]">
    <div class="py-6">
      <div class="max-w-7xl mx-auto space-y-6">
        <!-- Customer & Date Section -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 flex flex-col md:flex-row gap-6">
          <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Customer</label>
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
          <div class="w-full md:w-64">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Date</label>
            <input type="date" v-model="quoteDate" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" />
          </div>
        </div>
        <!-- Quote Items Table -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Quote Items</h3>
            <button @click="addItem" class="bg-green-600 text-white px-3 py-1 rounded-lg hover:bg-green-700 flex items-center gap-1">
              <span class="text-xl leading-none">+</span> Add
            </button>
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">#</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Product</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Remark</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Stock</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Price</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Qty</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Total</th>
                  <th class="px-4 py-2"></th>
                </tr>
              </thead>
              <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-for="(item, idx) in items" :key="idx">
                  <td class="px-4 py-2">{{ idx + 1 }}</td>
                  <td class="px-4 py-2">
                    <input
                      v-model="item.productSearch"
                      @input="searchProducts(idx)"
                      @focus="item.showDropdown = true"
                      @blur="() => setTimeout(() => item.showDropdown = false, 200)"
                      class="w-full px-2 py-1 border rounded-lg dark:bg-gray-700 dark:border-gray-600"
                      placeholder="Search product..."
                      autocomplete="off"
                    />
                    <div v-if="item.showDropdown && filteredProducts(idx).length" class="absolute z-10 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded shadow mt-1 w-64 max-h-48 overflow-y-auto">
                      <div
                        v-for="product in filteredProducts(idx)"
                        :key="product.id"
                        @mousedown.prevent="selectProduct(idx, product)"
                        class="px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer"
                      >
                        {{ product.name }} <span class="text-xs text-gray-400">({{ product.barcode }})</span>
                      </div>
                    </div>
                  </td>
                  <td class="px-4 py-2">
                    <input v-model="item.remark" class="w-full px-2 py-1 border rounded-lg dark:bg-gray-700 dark:border-gray-600" />
                  </td>
                  <td class="px-4 py-2">
                    <input :value="item.stock" readonly class="w-20 px-2 py-1 border rounded-lg bg-gray-100 dark:bg-gray-700 dark:border-gray-600 text-center" />
                  </td>
                  <td class="px-4 py-2">
                    <input type="number" v-model.number="item.price" @input="updateTotal(idx)" class="w-24 px-2 py-1 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-center" />
                  </td>
                  <td class="px-4 py-2">
                    <input type="number" v-model.number="item.quantity" @input="updateTotal(idx)" class="w-20 px-2 py-1 border rounded-lg dark:bg-gray-700 dark:border-gray-600 text-center" />
                  </td>
                  <td class="px-4 py-2">
                    <input :value="item.total" readonly class="w-24 px-2 py-1 border rounded-lg bg-gray-100 dark:bg-gray-700 dark:border-gray-600 text-center" />
                  </td>
                  <td class="px-4 py-2">
                    <button @click="removeItem(idx)" class="text-red-500 hover:text-red-700">&times;</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <!-- Quote Details Card -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 space-y-4">
            <div class="flex gap-2 items-center">
              <label class="w-28 text-gray-700 dark:text-gray-200">Subtotal</label>
              <input :value="subtotal" readonly class="border rounded-lg px-3 py-1 flex-1 dark:bg-gray-700 dark:border-gray-600" />
            </div>
            <div class="flex gap-2 items-center">
              <label class="w-28 text-gray-700 dark:text-gray-200">Tax (%)</label>
              <div class="flex gap-2 items-center flex-1">
                <input type="number" v-model.number="taxPercentage" min="0" max="100" step="0.01" class="border rounded-lg px-3 py-1 w-24 dark:bg-gray-700 dark:border-gray-600" />
                <input :value="tax" readonly class="border rounded-lg px-3 py-1 flex-1 dark:bg-gray-700 dark:border-gray-600" />
              </div>
            </div>
            <div class="flex gap-2 items-center">
              <label class="w-28 text-gray-700 dark:text-gray-200">Discount</label>
              <input type="number" v-model.number="discount" class="border rounded-lg px-3 py-1 flex-1 dark:bg-gray-700 dark:border-gray-600" />
            </div>
            <div class="flex gap-2 items-center">
              <label class="w-28 text-gray-700 dark:text-gray-200">Delivery Method</label>
              <div class="flex gap-2 flex-wrap">
                <button
                  v-for="method in deliveryMethods"
                  :key="method.value"
                  type="button"
                  @click="deliveryMethod = method.value"
                  :class="[
                    'flex items-center gap-2 px-4 py-2 rounded-lg border transition min-w-[120px] justify-center',
                    deliveryMethod === method.value
                      ? 'bg-blue-600 text-white border-blue-600 shadow'
                      : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 border-gray-300 dark:border-gray-600 hover:bg-blue-50 dark:hover:bg-blue-800'
                  ]"
                >
                  <span>{{ method.label }}</span>
                </button>
              </div>
            </div>
            <div v-if="deliveryMethod === 'delivery'" class="flex gap-2 items-center mt-2">
              <label class="w-28 text-gray-700 dark:text-gray-200">Delivery Cost</label>
              <input type="number" v-model.number="deliveryCost" class="border rounded-lg px-3 py-1 flex-1 dark:bg-gray-700 dark:border-gray-600" />
            </div>
            <div class="flex gap-2 items-center">
              <label class="w-28 text-gray-700 dark:text-gray-200">Total</label>
              <input :value="total" readonly class="border rounded-lg px-3 py-1 flex-1 dark:bg-gray-700 dark:border-gray-600 font-bold" />
            </div>
          </div>
          <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 space-y-4">
            <div class="flex gap-2 items-center">
              <label class="w-28 text-gray-700 dark:text-gray-200">Quote Remark</label>
              <textarea v-model="remark" class="border rounded-lg px-3 py-1 flex-1 dark:bg-gray-700 dark:border-gray-600" placeholder="Details"></textarea>
            </div>
          </div>
        </div>
        <!-- Save Button -->
        <div class="flex justify-center mt-6">
          <button type="button" @click="saveQuote" :disabled="saving" class="bg-cyan-600 hover:bg-cyan-700 text-white px-8 py-3 rounded-lg text-lg font-semibold shadow flex items-center gap-2">
            <span v-if="!saving">Save Quote</span>
            <span v-else>Saving...</span>
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

const items = ref([
    { product: null, productSearch: '', showDropdown: false, remark: '', stock: '', price: '', quantity: 1, total: 0 }
]);
const products = ref([]);
const quoteDate = ref(new Date().toISOString().slice(0, 10));
const discount = ref(0);
const deliveryCost = ref(0);
const remark = ref('');
const deliveryMethod = ref('walk-in');
const taxPercentage = ref(0);
const deliveryMethods = [
  { value: 'walk-in', label: 'Walk-in' },
  { value: 'delivery', label: 'Delivery' },
  { value: 'shopee', label: 'Shopee' },
  { value: 'tiktok', label: 'Tiktok' },
  { value: 'lazada', label: 'Lazada' }
];

const selectedCustomer = ref(null);
const customerSearchQuery = ref('');
const showCustomerSearch = ref(false);
const customers = ref([]);
const loadingCustomers = ref(false);
const saving = ref(false);

const addItem = () => {
  items.value.push({ product: null, productSearch: '', showDropdown: false, remark: '', stock: '', price: '', quantity: 1, total: 0 });
};
const removeItem = (idx) => {
  if (items.value.length > 1) items.value.splice(idx, 1);
};
const searchProducts = (idx) => {
  items.value[idx].showDropdown = true;
};
const filteredProducts = (idx) => {
  const query = items.value[idx].productSearch.toLowerCase();
  return products.value.filter(p =>
    p.name.toLowerCase().includes(query) ||
    (p.barcode && p.barcode.includes(query))
  );
};
const selectProduct = (idx, product) => {
  items.value[idx].product = product;
  items.value[idx].productSearch = product.name;
  items.value[idx].stock = product.stock;
  items.value[idx].price = parseFloat(product.price);
  items.value[idx].quantity = 1;
  items.value[idx].total = product.price;
  items.value[idx].showDropdown = false;
};
const updateTotal = (idx) => {
  const item = items.value[idx];
  if (item.product) {
    item.total = (parseFloat(item.price) * item.quantity).toFixed(2);
  }
};
const subtotal = computed(() => {
  return items.value.reduce((sum, item) => sum + (parseFloat(item.total) || 0), 0).toFixed(2);
});
const tax = computed(() => {
  return (subtotal.value * (taxPercentage.value / 100)).toFixed(2);
});
const total = computed(() => {
  return (parseFloat(subtotal.value) + parseFloat(tax.value) + parseFloat(deliveryCost.value) - parseFloat(discount.value)).toFixed(2);
});

const selectCustomer = (customer) => {
  selectedCustomer.value = customer;
  showCustomerSearch.value = false;
  customerSearchQuery.value = '';
};
const clearCustomer = () => {
  selectedCustomer.value = null;
};
const loadCustomers = async (search = '') => {
  try {
    loadingCustomers.value = true;
    const response = await axios.get(`/api/customers/search?q=${search}`);
    customers.value = response.data;
  } catch (error) {
    customers.value = [];
  } finally {
    loadingCustomers.value = false;
  }
};
watch(customerSearchQuery, (newQuery) => {
  if (newQuery.length >= 2) {
    loadCustomers(newQuery);
  } else {
    customers.value = [];
  }
});

watch(deliveryMethod, (newVal) => {
  if (newVal === 'pickup') {
    deliveryCost.value = 0;
  }
});

onMounted(async () => {
  const res = await axios.get('/pos-products');
  products.value = res.data.products;
});

const saveQuote = async () => {
  saving.value = true;
  try {
    const validItems = items.value.filter(item => item.product?.id || item.productSearch);
    if (validItems.length === 0) {
      toast.error('Please select at least one product.');
      saving.value = false;
      return;
    }
    const payload = {
      items: validItems.map(item => ({
        product_id: item.product?.id || null,
        product_name: item.productSearch || (item.product?.name ?? ''),
        quantity: item.quantity,
        remark: item.remark || null,
        price: item.price,
        total: item.total,
      })),
      customer_id: selectedCustomer.value?.id || null,
      subtotal: parseFloat(subtotal.value),
      tax: parseFloat(tax.value),
      discount: parseFloat(discount.value),
      delivery_cost: parseFloat(deliveryCost.value),
      total: parseFloat(total.value),
      delivery_method: deliveryMethod.value,
      remarks: remark.value,
    };
    router.post('/quotes', payload, {
      onSuccess: () => {
        toast.success('Quote created successfully!');
      },
      onError: (errors) => {
        if (errors) {
          toast.error(Object.values(errors).flat().join('\n'));
        } else {
          toast.error('Failed to create quote');
        }
      },
      onFinish: () => {
        saving.value = false;
      }
    });
  } catch (error) {
    toast.error('Failed to create quote');
    saving.value = false;
  }
};
</script> 