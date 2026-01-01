<template>
    <Head :title="'Order #' + order.id" />

    <AppLayout :breadcrumbs="[
        { name: 'Orders', href: route('orders.index') },
        { name: 'Order #' + order.id, href: route('orders.show', order.id) }
    ]">
        <div class="container mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                    Order #{{ order.id }}
                </h1>
            </div>

            <div class="max-w-7xl mx-auto">
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
                        <!-- Order Status and Date -->
                        <div class="flex justify-between items-center mb-6">
                            <div class="flex items-center gap-4">
                                <div class="relative status-dropdown">
                                    <button
                                        @click="showStatusDropdown = !showStatusDropdown"
                                        type="button"
                                        class="status-dropdown-button"
                                        :class="{
                                            'px-3 py-1 text-sm rounded-full inline-flex items-center gap-2': true,
                                            'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': order.status === 'completed',
                                            'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200': order.status === 'processing',
                                            'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': order.status === 'pending',
                                            'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': order.status === 'cancelled'
                                        }"
                                    >
                                        {{ order.status }}
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                    <!-- Status Dropdown -->
                                    <div
                                        v-show="showStatusDropdown"
                                        class="status-dropdown-menu absolute z-10 mt-2 w-48 rounded-md shadow-lg bg-white dark:bg-gray-700 ring-1 ring-black ring-opacity-5"
                                    >
                                        <div class="py-1" role="menu">
                                            <button
                                                v-for="status in ['pending', 'processing', 'completed', 'cancelled']"
                                                :key="status"
                                                @click="updateStatus(status)"
                                                class="block w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-600"
                                                :class="{
                                                    'text-yellow-800 dark:text-yellow-200': status === 'pending',
                                                    'text-blue-800 dark:text-blue-200': status === 'processing',
                                                    'text-green-800 dark:text-green-200': status === 'completed',
                                                    'text-red-800 dark:text-red-200': status === 'cancelled'
                                                }"
                                            >
                                                {{ status.charAt(0).toUpperCase() + status.slice(1) }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                {{ order.created_at }}
                            </div>
                        </div>

                        <!-- Customer and Cashier Info -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-lg font-semibold">Customer Information</h3>
                                    <button
                                        v-if="!editingCustomer && order.customer"
                                        @click="startEditingCustomer"
                                        class="px-3 py-1 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700"
                                    >
                                        Edit
                                    </button>
                                    <div v-else-if="editingCustomer" class="flex gap-2">
                                        <button
                                            @click="saveCustomerInfo"
                                            class="px-3 py-1 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700"
                                            :disabled="savingCustomer"
                                        >
                                            {{ savingCustomer ? 'Saving...' : 'Save' }}
                                        </button>
                                        <button
                                            @click="cancelEditingCustomer"
                                            class="px-3 py-1 bg-gray-500 text-white text-sm rounded-lg hover:bg-gray-600"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                </div>
                                <div v-if="!editingCustomer">
                                    <div v-if="order.customer">
                                        <p><span class="font-medium">Name:</span> {{ order.customer.name }}</p>
                                        <p><span class="font-medium">Email:</span> {{ order.customer.email || '-' }}</p>
                                        <p><span class="font-medium">Phone:</span> {{ order.customer.phone || '-' }}</p>
                                        <p><span class="font-medium">Address:</span> {{ order.customer.address || '-' }}</p>
                                    </div>
                                    <p v-else class="text-gray-500 dark:text-gray-400">Walk-in Customer</p>
                                </div>
                                <div v-else class="space-y-3">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name</label>
                                        <input
                                            type="text"
                                            v-model="customerForm.name"
                                            class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 shadow-sm py-2 px-3"
                                            placeholder="Enter customer name"
                                        >
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                                        <input
                                            type="email"
                                            v-model="customerForm.email"
                                            class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 shadow-sm py-2 px-3"
                                            placeholder="Enter email"
                                        >
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone</label>
                                        <input
                                            type="text"
                                            v-model="customerForm.phone"
                                            class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 shadow-sm py-2 px-3"
                                            placeholder="Enter phone"
                                        >
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Address</label>
                                        <textarea
                                            v-model="customerForm.address"
                                            rows="2"
                                            class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 shadow-sm py-2 px-3"
                                            placeholder="Enter address"
                                        ></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                <h3 class="text-lg font-semibold mb-2">Order Information</h3>
                                <p><span class="font-medium">Cashier:</span> {{ order.cashier.name }}</p>
                                <p><span class="font-medium">Payment Method:</span> {{ order.payment_method }}</p>
                                <p><span class="font-medium">Delivery Method:</span> {{ order.delivery_method }}</p>
                                <p v-if="order.remarks"><span class="font-medium">Remarks:</span> {{ order.remarks }}</p>
                            </div>
                        </div>

                        <!-- Delivery Information -->
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg mb-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold">Delivery Information</h3>
                                <button
                                    v-if="!editingDelivery"
                                    @click="startEditingDelivery"
                                    class="px-3 py-1 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700"
                                >
                                    Edit
                                </button>
                                <div v-else class="flex gap-2">
                                    <button
                                        @click="saveDeliveryInfo"
                                        class="px-3 py-1 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700"
                                        :disabled="savingDelivery"
                                    >
                                        {{ savingDelivery ? 'Saving...' : 'Save' }}
                                    </button>
                                    <button
                                        @click="cancelEditingDelivery"
                                        class="px-3 py-1 bg-gray-500 text-white text-sm rounded-lg hover:bg-gray-600"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </div>
                            <div v-if="!editingDelivery" class="space-y-2">
                                <p><span class="font-medium">Name:</span> {{ deliveryForm.delivery_name || '-' }}</p>
                                <p><span class="font-medium">Company Name:</span> {{ deliveryForm.delivery_company_name || '-' }}</p>
                                <p><span class="font-medium">Address:</span> {{ deliveryForm.delivery_address || '-' }}</p>
                                <p><span class="font-medium">Phone:</span> {{ deliveryForm.delivery_phone || '-' }}</p>
                            </div>
                            <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Delivery Name</label>
                                    <input
                                        type="text"
                                        v-model="deliveryForm.delivery_name"
                                        class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 shadow-sm py-2 px-3"
                                        placeholder="Enter delivery name"
                                    >
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Company Name</label>
                                    <input
                                        type="text"
                                        v-model="deliveryForm.delivery_company_name"
                                        class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 shadow-sm py-2 px-3"
                                        placeholder="Enter company name (optional)"
                                    >
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Delivery Address</label>
                                    <textarea
                                        v-model="deliveryForm.delivery_address"
                                        rows="3"
                                        class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 shadow-sm py-2 px-3"
                                        placeholder="Enter delivery address"
                                    ></textarea>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Delivery Phone</label>
                                    <input
                                        type="text"
                                        v-model="deliveryForm.delivery_phone"
                                        class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 shadow-sm py-2 px-3"
                                        placeholder="Enter delivery phone"
                                    >
                                </div>
                            </div>
                        </div>

                        <!-- Order Items -->
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold mb-4">Order Items</h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Product</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Price</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Quantity</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Profit</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Remark</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                                        <tr v-for="item in order.items" :key="item.id">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ item.product_name }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ currency }}{{ item.price }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ item.quantity }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ currency }}{{ item.total }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 dark:text-green-400">{{ currency }}{{ item.profit }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ item.remark || '-' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Service Expenses -->
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold mb-4">Service Expenses</h3>
                            <div class="overflow-x-auto" v-if="order.expenses && order.expenses.length">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Amount</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Remark</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                                        <tr v-for="expense in order.expenses" :key="expense.id">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ expense.name }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ currency }}{{ expense.amount }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ expense.remark || '-' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div v-else class="text-gray-500 dark:text-gray-400">No expenses added.</div>
                        </div>

                        <!-- Order Summary -->
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                            <h3 class="text-lg font-semibold mb-4">Order Summary</h3>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span>Subtotal</span>
                                    <span>{{ currency }}{{ order.subtotal }}</span>
                                </div>
                                <div class="flex justify-between" v-if="order.expenses?.length">
                                    <span>Expenses</span>
                                    <span>{{ currency }}{{ order.expenses.reduce((s, e) => s + parseFloat((e.amount||'0').toString().replace(/,/g, '')), 0).toFixed(2) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Tax</span>
                                    <span>{{ currency }}{{ order.tax }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Discount</span>
                                    <span>{{ currency }}{{ order.discount }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Delivery Cost</span>
                                    <span>{{ currency }}{{ order.delivery_cost }}</span>
                                </div>
                                <div class="flex justify-between font-semibold border-t dark:border-gray-600 pt-2">
                                    <span>Total</span>
                                    <span>{{ currency }}{{ order.total }}</span>
                                </div>
                                <div class="flex justify-between text-green-600 dark:text-green-400 font-semibold border-t dark:border-gray-600 pt-2">
                                    <span>Total Profit</span>
                                    <span>{{ currency }}{{ order.profit }}</span>
                                </div>
                                <div class="flex justify-between text-green-600 dark:text-green-400">
                                    <span>Paid Amount</span>
                                    <span>{{ currency }}{{ order.paid_amount }}</span>
                                </div>
                                <div v-if="order.due_amount > 0" class="flex justify-between text-red-600 dark:text-red-400">
                                    <span>Due Amount</span>
                                    <span>{{ currency }}{{ order.due_amount }}</span>
                                </div>
                                <div v-if="order.change_amount > 0" class="flex justify-between text-blue-600 dark:text-blue-400">
                                    <span>Change</span>
                                    <span>{{ currency }}{{ order.change_amount }}</span>
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
                    :href="route('orders.index')"
                    class="px-4 py-2 bg-gray-500 dark:bg-gray-600 text-white rounded-lg hover:bg-gray-600 dark:hover:bg-gray-500"
                >
                    Back to Orders
                </Link>
                <Link
                    v-if="!order.myinvois_invoice"
                    :href="route('orders.edit', order.id)"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
                >
                    Edit Order
                </Link>
                <span
                    v-if="order.myinvois_invoice"
                    class="px-4 py-2 bg-gray-400 text-white rounded-lg cursor-not-allowed"
                    title="Cannot edit order with pushed MyInvois invoice. Cancel the invoice first."
                >
                    Edit Order (Locked)
                </span>
                <a
                    :href="route('orders.invoice', order.id)"
                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700"
                    target="_blank"
                >
                    Print Invoice
                </a>
                <Link
                    v-if="order.myinvois_invoice"
                    :href="route('orders.eInvoice', order.id)"
                    class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700"
                    target="_blank"
                >
                    View E-Invoice
                </Link>
                <button
                    v-if="order.customer?.email"
                    @click="sendInvoice"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                    :disabled="sendingInvoice"
                >
                    <span v-if="sendingInvoice">Sending...</span>
                    <span v-else>Send Invoice (Email)</span>
                </button>
                <button
                    v-if="!order.my_invois_invoice && !order.in_consolidation_queue"
                    @click="addToConsolidation"
                    class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700"
                    :disabled="addingToConsolidation"
                >
                    <span v-if="addingToConsolidation">Adding...</span>
                    <span v-else>Add to Consolidation</span>
                </button>
                <span
                    v-else-if="order.in_consolidation_queue"
                    class="px-4 py-2 bg-yellow-500 text-white rounded-lg"
                >
                    In Queue
                </span>
                <span
                    v-else-if="order.my_invois_invoice"
                    class="px-4 py-2 bg-green-500 text-white rounded-lg"
                >
                    Pushed to MyInvois
                </span>
                <button
                    v-if="order.delivery_method === 'walk-in' && !order.myinvois_queue_status && !order.myinvois_invoice"
                    @click="addToQueue"
                    class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700"
                >
                    Add to Consolidation Queue
                </button>
                <button
                    v-if="order.myinvois_queue_status === 'pending'"
                    @click="pushToMyInvois"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                >
                    Push to MyInvois
                </button>
                <button
                    v-if="order.myinvois_queue_status === 'pending'"
                    @click="clearFromQueue"
                    class="px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700"
                >
                    Clear from Queue
                </button>
                <span
                    v-if="order.myinvois_queue_status === 'pushed'"
                    class="px-4 py-2 bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 rounded-lg flex items-center"
                >
                    ✓ Pushed to MyInvois
                </span>
                <button
                    v-if="order.myinvois_invoice"
                    @click="showCancelModal = true"
                    class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700"
                >
                    Cancel MyInvois Invoice
                </button>
            </div>
        </div>

        <!-- Cancel MyInvois Invoice Modal -->
        <Modal :show="showCancelModal" @close="showCancelModal = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    Cancel MyInvois Invoice
                </h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    Please provide a reason for cancelling this invoice. After cancellation, you will be able to edit the order and resubmit.
                </p>
                <div class="mb-4">
                    <label for="cancel_reason" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Cancellation Reason <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        id="cancel_reason"
                        v-model="cancelReason"
                        rows="4"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-100"
                        placeholder="Enter reason for cancellation..."
                        required
                    ></textarea>
                </div>
                <div class="flex justify-end gap-3">
                    <button
                        @click="showCancelModal = false"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600"
                    >
                        Cancel
                    </button>
                    <button
                        @click="cancelMyInvoisInvoice"
                        :disabled="!cancelReason || cancelReason.trim() === ''"
                        class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700 disabled:bg-gray-400 disabled:cursor-not-allowed"
                    >
                        Confirm Cancellation
                    </button>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, computed } from 'vue';

const props = defineProps({
    order: {
        type: Object,
        required: true,
    },
    myinvoisQueueDelayHours: {
        type: Number,
        default: 72,
    },
});

const page = usePage();
const currency = computed(() => page.props.settings?.currency || 'USD');

const showStatusDropdown = ref(false);
const sendingInvoice = ref(false);
const addingToConsolidation = ref(false);
const editingDelivery = ref(false);
const savingDelivery = ref(false);
const originalDeliveryData = ref(null);
const deliveryForm = ref({
    delivery_name: props.order.delivery_name || '',
    delivery_company_name: props.order.delivery_company_name || '',
    delivery_address: props.order.delivery_address || '',
    delivery_phone: props.order.delivery_phone || '',
});
const editingCustomer = ref(false);
const savingCustomer = ref(false);
const originalCustomerData = ref(null);
const customerForm = ref({
    customer_id: props.order.customer?.id || null,
    name: props.order.customer?.name || '',
    email: props.order.customer?.email || '',
    phone: props.order.customer?.phone || '',
    address: props.order.customer?.address || '',
});
const showCancelModal = ref(false);
const cancelReason = ref('');

const updateStatus = (status) => {
    if (confirm(`Are you sure you want to change the order status to ${status}?`)) {
        router.put(
            route('orders.updateStatus', props.order.id),
            { status },
            {
                preserveScroll: true,
                onSuccess: () => {
                    showStatusDropdown.value = false;
                    router.reload({ only: ['order'] });
                },
            }
        );
    }
};

const sendInvoice = () => {
    if (confirm('Are you sure you want to send the invoice to ' + props.order.customer.email + '?')) {
        sendingInvoice.value = true;
        router.post(
            route('orders.send-invoice', props.order.id),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    sendingInvoice.value = false;
                    alert('Invoice sent successfully!');
                },
                onError: () => {
                    sendingInvoice.value = false;
                    alert('Failed to send invoice. Please try again.');
                },
            }
        );
    }
};

const startEditingDelivery = () => {
    originalDeliveryData.value = { ...deliveryForm.value };
    editingDelivery.value = true;
};

const cancelEditingDelivery = () => {
    if (originalDeliveryData.value) {
        deliveryForm.value = { ...originalDeliveryData.value };
    }
    editingDelivery.value = false;
    originalDeliveryData.value = null;
};

const saveDeliveryInfo = () => {
    savingDelivery.value = true;
    router.put(
        route('orders.updateDelivery', props.order.id),
        deliveryForm.value,
        {
            preserveScroll: true,
            onSuccess: () => {
                savingDelivery.value = false;
                editingDelivery.value = false;
                originalDeliveryData.value = null;
                router.reload({ only: ['order'] });
            },
            onError: () => {
                savingDelivery.value = false;
                alert('Failed to save delivery information. Please try again.');
            },
        }
    );
};

const startEditingCustomer = () => {
    originalCustomerData.value = { ...customerForm.value };
    editingCustomer.value = true;
};

const cancelEditingCustomer = () => {
    if (originalCustomerData.value) {
        customerForm.value = { ...originalCustomerData.value };
    }
    editingCustomer.value = false;
    originalCustomerData.value = null;
};

const saveCustomerInfo = () => {
    savingCustomer.value = true;
    router.put(
        route('orders.updateCustomer', props.order.id),
        customerForm.value,
        {
            preserveScroll: true,
            onSuccess: () => {
                savingCustomer.value = false;
                editingCustomer.value = false;
                originalCustomerData.value = null;
                router.reload({ only: ['order'] });
            },
            onError: () => {
                savingCustomer.value = false;
                alert('Failed to save customer information. Please try again.');
            },
        }
    );
};

const addToConsolidation = () => {
    if (confirm('Add this order to MyInvois consolidation queue?')) {
        addingToConsolidation.value = true;
        router.post(
            route('orders.addToConsolidation', props.order.id),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    addingToConsolidation.value = false;
                    router.reload({ only: ['order'] });
                },
                onError: () => {
                    addingToConsolidation.value = false;
                    alert('Failed to add order to consolidation queue. Please try again.');
                },
            }
        );
    }
};

const pushToMyInvois = () => {
    if (confirm(`Are you sure you want to push this invoice to MyInvois now? This will bypass the ${props.myinvoisQueueDelayHours}-hour delay.`)) {
        router.post(
            route('orders.pushMyInvois', props.order.id),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    router.reload({ only: ['order'] });
                },
            }
        );
    }
};

const clearFromQueue = () => {
    if (confirm('Are you sure you want to remove this invoice from the MyInvois queue? It will not be pushed.')) {
        router.post(
            route('orders.clearQueue', props.order.id),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    router.reload({ only: ['order'] });
                },
            }
        );
    }
};

const addToQueue = () => {
    if (confirm(`Add this invoice to the consolidation queue? It will be automatically pushed to MyInvois after ${props.myinvoisQueueDelayHours} hours.`)) {
        router.post(
            route('orders.addToQueue', props.order.id),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    router.reload({ only: ['order'] });
                },
            }
        );
    }
};

const cancelMyInvoisInvoice = () => {
    if (!cancelReason.value || cancelReason.value.trim() === '') {
        return;
    }

    router.put(
        route('orders.cancelMyInvois', props.order.id),
        { reason: cancelReason.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                showCancelModal.value = false;
                cancelReason.value = '';
                router.reload({ only: ['order'] });
            },
        }
    );
};
// Close dropdown when clicking outside
const closeDropdown = (e) => {
    const dropdown = document.querySelector('.status-dropdown');
    if (dropdown && !dropdown.contains(e.target)) {
        showStatusDropdown.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', closeDropdown);
});

onUnmounted(() => {
    document.removeEventListener('click', closeDropdown);
});
</script> 