<template>
    <Head :title="'E-Invoice #' + order.id" />

    <AppLayout>
        <div class="container mx-auto py-6 px-4 sm:px-6 lg:px-8 max-w-4xl">
            <!-- E-Invoice Document -->
            <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg overflow-hidden print:shadow-none relative">
                <!-- Red Banner for Illustration -->
                <div v-if="!order.myinvois_invoice" class="bg-red-600 text-white text-center py-2 text-sm font-bold transform rotate-12 absolute top-4 right-4 z-10 px-8">
                    FOR ILLUSTRATION PURPOSES ONLY
                </div>

                <div class="p-8">
                    <!-- Header: Supplier Name and Address -->
                    <div class="mb-6">
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                            {{ shopSettings.shop_name }}
                        </h1>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            {{ shopSettings.shop_address }}
                        </p>
                    </div>

                    <!-- Card 1 and Card 2 Side by Side -->
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <!-- Card 1: Supplier Details -->
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 border border-gray-200 dark:border-gray-600">
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-3">Supplier Details</h3>
                            <div class="space-y-2 text-sm">
                                <p><span class="font-medium">Name:</span> {{ shopSettings.shop_name }}</p>
                                <p><span class="font-medium">TIN:</span> {{ shopSettings.tax_number || 'N/A' }}</p>
                                <p v-if="shopSettings.identification_number"><span class="font-medium">Reg. No:</span> {{ shopSettings.identification_number }}</p>
                                <p v-if="shopSettings.identification_scheme"><span class="font-medium">ID Type:</span> {{ shopSettings.identification_scheme }}</p>
                                <p><span class="font-medium">Address:</span> {{ shopSettings.shop_address }}</p>
                                <p><span class="font-medium">Phone:</span> {{ shopSettings.shop_phone }}</p>
                                <p v-if="shopSettings.industry_classification_code"><span class="font-medium">MSIC:</span> {{ shopSettings.industry_classification_code }}</p>
                            </div>
                        </div>

                        <!-- Card 2: E-Invoice Details -->
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 border border-gray-200 dark:border-gray-600">
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-3">E-Invoice Details</h3>
                            <div class="space-y-2 text-sm">
                                <p><span class="font-medium">e-Invoice Code:</span> {{ order.myinvois_invoice?.invoice_code_number || 'N/A' }}</p>
                                <p v-if="order.myinvois_invoice"><span class="font-medium">Unique ID:</span> {{ order.myinvois_invoice.uuid.substring(0, 25) }}...</p>
                                <p><span class="font-medium">Invoice Date:</span> {{ formatDateTime(order.created_at) }}</p>
                                <p v-if="order.myinvois_invoice"><span class="font-medium">Validated:</span> {{ formatDateTime(order.myinvois_invoice.created_at) }}</p>
                                <p v-if="order.myinvois_invoice" class="text-green-600 dark:text-green-400 font-semibold">
                                    ✓ Status: Validated
                                </p>
                                <p v-else class="text-gray-500 dark:text-gray-400">
                                    Status: Not Submitted
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Horizontal Divider -->
                    <hr class="my-4 border-gray-300 dark:border-gray-600">

                    <!-- Card 3: Buyer Information -->
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 border border-gray-200 dark:border-gray-600 mb-6">
                        <h3 class="font-semibold text-gray-900 dark:text-white mb-3">Buyer Information</h3>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <p><span class="font-medium">Name:</span> {{ order.customer?.name || 'Walk-in Customer' }}</p>
                                <p v-if="order.customer?.tin"><span class="font-medium">TIN:</span> {{ order.customer.tin }}</p>
                                <p v-if="order.customer?.brn || order.customer?.nric">
                                    <span class="font-medium">Reg. No:</span> 
                                    {{ order.customer.brn || order.customer.nric || 'N/A' }}
                                </p>
                            </div>
                            <div>
                                <p v-if="order.customer?.address"><span class="font-medium">Address:</span> {{ formatBuyerAddress(order.customer) }}</p>
                                <p v-else><span class="font-medium">Address:</span> N/A</p>
                                <p v-if="order.customer?.phone"><span class="font-medium">Phone:</span> {{ order.customer.phone }}</p>
                                <p v-else><span class="font-medium">Phone:</span> N/A</p>
                            </div>
                        </div>
                    </div>

                    <!-- Line Items Table -->
                    <div class="mb-6 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 border border-gray-300 dark:border-gray-600">
                            <thead class="bg-gray-100 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase border-r border-gray-300 dark:border-gray-600">Classification</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase border-r border-gray-300 dark:border-gray-600">Description</th>
                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase border-r border-gray-300 dark:border-gray-600">Quantity</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase border-r border-gray-300 dark:border-gray-600">Unit Price</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase border-r border-gray-300 dark:border-gray-600">Amount</th>
                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase border-r border-gray-300 dark:border-gray-600">Disc</th>
                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase border-r border-gray-300 dark:border-gray-600">Tax Rate</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase border-r border-gray-300 dark:border-gray-600">Tax Amount</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Total (incl. tax)</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="(item, index) in order.items" :key="item.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100 border-r border-gray-300 dark:border-gray-600">004</td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100 border-r border-gray-300 dark:border-gray-600">{{ item.product_name }}</td>
                                    <td class="px-4 py-2 text-sm text-center text-gray-900 dark:text-gray-100 border-r border-gray-300 dark:border-gray-600">{{ item.quantity }}</td>
                                    <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100 border-r border-gray-300 dark:border-gray-600">{{ formatCurrency(item.price) }}</td>
                                    <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100 border-r border-gray-300 dark:border-gray-600">{{ formatCurrency(item.total) }}</td>
                                    <td class="px-4 py-2 text-sm text-center text-gray-900 dark:text-gray-100 border-r border-gray-300 dark:border-gray-600">-</td>
                                    <td class="px-4 py-2 text-sm text-center text-gray-900 dark:text-gray-100 border-r border-gray-300 dark:border-gray-600">-</td>
                                    <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100 border-r border-gray-300 dark:border-gray-600">-</td>
                                    <td class="px-4 py-2 text-sm text-right font-medium text-gray-900 dark:text-gray-100">{{ formatCurrency(item.total) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Summary Totals -->
                    <div class="mb-6 flex justify-end">
                        <div class="w-64 space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="font-medium text-gray-700 dark:text-gray-300">Subtotal:</span>
                                <span class="text-gray-900 dark:text-gray-100">{{ formatCurrency(order.subtotal) }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="font-medium text-gray-700 dark:text-gray-300">Total excluding tax:</span>
                                <span class="text-gray-900 dark:text-gray-100">{{ formatCurrency(order.subtotal) }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="font-medium text-gray-700 dark:text-gray-300">Tax amount:</span>
                                <span class="text-gray-900 dark:text-gray-100">{{ formatCurrency(order.tax) }}</span>
                            </div>
                            <div class="flex justify-between text-lg font-bold pt-2 border-t-2 border-gray-300 dark:border-gray-600">
                                <span class="text-gray-900 dark:text-white">Total including tax:</span>
                                <span class="text-gray-900 dark:text-white">{{ formatCurrency(order.total) }}</span>
                            </div>
                            <div class="flex justify-between text-lg font-bold">
                                <span class="text-gray-900 dark:text-white">Total payable amount:</span>
                                <span class="text-gray-900 dark:text-white">{{ formatCurrency(order.total) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Footer: QR Code, Date of Validation, End Text -->
                    <div class="mt-6 pt-4 border-t border-gray-300 dark:border-gray-600">
                        <div class="flex justify-between items-end">
                            <div class="flex-1">
                                <p v-if="order.myinvois_invoice" class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                    <span class="font-medium">Date of Validation:</span> {{ formatDateTime(order.myinvois_invoice.created_at) }}
                                </p>
                                <p class="text-sm text-gray-500 dark:text-gray-400 italic">End of e-Invoice</p>
                            </div>
                            <div v-if="order.qr_code_url" class="text-center ml-4">
                                <img 
                                    :src="`https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=${encodeURIComponent(order.qr_code_url)}`" 
                                    alt="QR Code"
                                    class="mx-auto border border-gray-300 dark:border-gray-600 mb-2"
                                />
                                <p class="text-xs text-gray-600 dark:text-gray-400">Scan to verify</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="mt-6 flex justify-end gap-4 print:hidden">
                <Link
                    :href="route('orders.show', order.id)"
                    class="px-4 py-2 bg-gray-500 dark:bg-gray-600 text-white rounded-lg hover:bg-gray-600 dark:hover:bg-gray-500"
                >
                    Back to Order
                </Link>
                <a
                    :href="route('orders.eInvoicePdf', order.id)"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 inline-block text-center"
                    target="_blank"
                >
                    Download E-Invoice PDF
                </a>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    order: {
        type: Object,
        required: true,
    },
    shopSettings: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const currency = computed(() => page.props.settings?.currency || 'RM');

const formatCurrency = (amount) => {
    return `${currency.value} ${parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')}`;
};

const formatDateTime = (dateString) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleString('en-MY', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false
    }).replace(',', '');
};

const formatBuyerAddress = (customer) => {
    if (!customer) return 'N/A';
    const parts = [
        customer.address,
        customer.city,
        customer.postal_code,
        customer.state_code,
        customer.country
    ].filter(Boolean);
    return parts.length > 0 ? parts.join(', ') : 'N/A';
};
</script>

<style scoped>
@media print {
    .print\:hidden {
        display: none;
    }
    .print\:shadow-none {
        box-shadow: none;
    }
}
</style>

