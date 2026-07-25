<template>
    <Head title="Sales Register" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Filter bar -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6 p-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Start Date</label>
                            <input type="date" v-model="filters.start_date" :class="inputClass">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">End Date</label>
                            <input type="date" v-model="filters.end_date" :class="inputClass">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Brand</label>
                            <select v-model="filters.brand" :class="inputClass">
                                <option value="">All</option>
                                <option v-for="b in filterOptions.brands" :key="b" :value="b">{{ b }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Category</label>
                            <select v-model="filters.category_id" :class="inputClass">
                                <option value="">All</option>
                                <option v-for="c in filterOptions.categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Sales Person</label>
                            <select v-model="filters.user_id" :class="inputClass">
                                <option value="">All</option>
                                <option v-for="u in filterOptions.salespersons" :key="u.id" :value="u.id">{{ u.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contact</label>
                            <select v-model="filters.customer_id" :class="inputClass">
                                <option value="">All</option>
                                <option v-for="c in filterOptions.customers" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Payment Method</label>
                            <select v-model="filters.payment_method" :class="inputClass">
                                <option value="">All</option>
                                <option v-for="m in filterOptions.paymentMethods" :key="m" :value="m">{{ m }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Delivery Method</label>
                            <select v-model="filters.delivery_method" :class="inputClass">
                                <option value="">All</option>
                                <option v-for="m in filterOptions.deliveryMethods" :key="m" :value="m">{{ m }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex gap-2 mt-4">
                        <button @click="applyFilters" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                            Apply Filters
                        </button>
                        <a :href="route('reports.sales-register.export', queryParams)" target="_blank"
                           class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 flex items-center gap-2">
                            <FileSpreadsheet class="w-4 h-4" /> Export Excel
                        </a>
                        <a :href="route('reports.sales-register.invoices', queryParams)" target="_blank"
                           class="bg-slate-600 text-white px-4 py-2 rounded-md hover:bg-slate-700 flex items-center gap-2">
                            <Download class="w-4 h-4" /> Download Invoices
                        </a>
                    </div>
                </div>

                <!-- Header block mirroring the reference report -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6 p-4 text-sm text-gray-700 dark:text-gray-300">
                    <h2 class="text-base font-bold mb-2 text-gray-900 dark:text-gray-100">SALES REGISTER BY PRODUCT TYPE (GROUP BY CATEGORY)</h2>
                    <div><strong>Duration</strong>: from {{ filters.start_date }} to {{ filters.end_date }}</div>
                    <div><strong>Outlet</strong>: All</div>
                    <div><strong>Brand</strong>: {{ filters.brand || 'ALL' }}</div>
                    <div><strong>Category</strong>: {{ selectedCategoryName }}</div>
                    <div><strong>Contact</strong>: {{ selectedCustomerName }}</div>
                    <div><strong>Sales Person</strong>: {{ selectedSalespersonName }}</div>
                    <div><strong>Date Printed</strong>: {{ printedAt }}</div>
                </div>

                <!-- Grouped tables -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <template v-for="group in groups" :key="group.category">
                                <thead class="bg-gray-100 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-sm font-bold text-gray-900 dark:text-gray-100">{{ group.category }}</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Quantity</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Sales</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-for="product in group.products" :key="product.name">
                                        <td class="px-6 py-3 text-gray-900 dark:text-gray-100">{{ product.name }}</td>
                                        <td class="px-6 py-3 text-right text-gray-900 dark:text-gray-100">{{ product.quantity }}</td>
                                        <td class="px-6 py-3 text-right text-gray-900 dark:text-gray-100">{{ formatNumber(product.sales) }}</td>
                                    </tr>
                                    <tr class="bg-gray-50 dark:bg-gray-900 font-semibold">
                                        <td class="px-6 py-3 text-right text-gray-900 dark:text-gray-100">Total</td>
                                        <td class="px-6 py-3 text-right text-gray-900 dark:text-gray-100">{{ group.quantity_total }}</td>
                                        <td class="px-6 py-3 text-right text-gray-900 dark:text-gray-100">{{ formatNumber(group.sales_total) }}</td>
                                    </tr>
                                </tbody>
                            </template>
                            <tfoot class="bg-gray-200 dark:bg-gray-700 font-bold">
                                <tr>
                                    <td class="px-6 py-3 text-right text-gray-900 dark:text-gray-100">Grand Total</td>
                                    <td class="px-6 py-3 text-right text-gray-900 dark:text-gray-100">{{ grandTotal.quantity }}</td>
                                    <td class="px-6 py-3 text-right text-gray-900 dark:text-gray-100">{{ formatNumber(grandTotal.sales) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                        <div v-if="groups.length === 0" class="p-6 text-center text-gray-500 dark:text-gray-400">
                            No sales found for the selected filters.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { FileSpreadsheet, Download } from 'lucide-vue-next';

const props = defineProps({
    groups: Array,
    grandTotal: Object,
    filterOptions: Object,
    filters: Object,
});

const inputClass = 'mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-3 py-2';

const filters = ref({
    start_date: '',
    end_date: '',
    brand: '',
    category_id: '',
    user_id: '',
    customer_id: '',
    payment_method: '',
    delivery_method: '',
    ...props.filters,
});

const breadcrumbs = [
    { title: 'Sales Register', href: route('reports.sales-register') },
];

const queryParams = computed(() => ({
    start_date: filters.value.start_date,
    end_date: filters.value.end_date,
    brand: filters.value.brand || undefined,
    category_id: filters.value.category_id || undefined,
    user_id: filters.value.user_id || undefined,
    customer_id: filters.value.customer_id || undefined,
    payment_method: filters.value.payment_method || undefined,
    delivery_method: filters.value.delivery_method || undefined,
}));

const selectedCategoryName = computed(() => {
    const c = props.filterOptions.categories.find((x) => String(x.id) === String(filters.value.category_id));
    return c ? c.name : 'ALL';
});
const selectedCustomerName = computed(() => {
    const c = props.filterOptions.customers.find((x) => String(x.id) === String(filters.value.customer_id));
    return c ? c.name : 'ALL';
});
const selectedSalespersonName = computed(() => {
    const u = props.filterOptions.salespersons.find((x) => String(x.id) === String(filters.value.user_id));
    return u ? u.name : 'ALL';
});
const printedAt = computed(() => new Date().toLocaleString());

function formatNumber(value) {
    return parseFloat(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function applyFilters() {
    router.get(route('reports.sales-register'), queryParams.value, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
</script>
