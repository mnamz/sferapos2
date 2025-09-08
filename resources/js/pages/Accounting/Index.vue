<template>
    <Head title="Accounting" />
    <AppLayout :breadcrumbs="[{ name: 'Accounting', href: route('accounting.index') }]">
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-6">
                            <div class="grid grid-cols-1 md:grid-cols-6 gap-3 w-full">
                                <div>
                                    <Label class="mb-1 block">Search</Label>
                                    <Input v-model="filters.search" type="text" placeholder="Search description..." />
                                </div>
                                <div>
                                    <Label class="mb-1 block">Category</Label>
                                    <select v-model="filters.category_id" class="mt-1 block w-full rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] dark:bg-input/30">
                                        <option value="">All Categories</option>
                                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }} ({{ c.type }})</option>
                                    </select>
                                </div>
                                <div>
                                    <Label class="mb-1 block">Type</Label>
                                    <select v-model="filters.type" class="mt-1 block w-full rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] dark:bg-input/30">
                                        <option value="">All Types</option>
                                        <option value="credit">Credit (Income)</option>
                                        <option value="debit">Debit (Expense)</option>
                                    </select>
                                </div>
                                <div>
                                    <Label class="mb-1 block">AR/AP</Label>
                                    <select v-model="filters.ar_ap_type" class="mt-1 block w-full rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] dark:bg-input/30">
                                        <option value="">All</option>
                                        <option value="AR">Accounts Receivable</option>
                                        <option value="AP">Accounts Payable</option>
                                    </select>
                                </div>
                                <div>
                                    <Label class="mb-1 block">Start date</Label>
                                    <Input v-model="filters.start_date" type="date" />
                                </div>
                                <div>
                                    <Label class="mb-1 block">End date</Label>
                                    <Input v-model="filters.end_date" type="date" />
                                </div>
                                <div>
                                    <Label class="mb-1 block">Payroll Only</Label>
                                    <select v-model="filters.is_payroll" class="mt-1 block w-full rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] dark:bg-input/30">
                                        <option :value="''">All</option>
                                        <option :value="true">Yes</option>
                                        <option :value="false">No</option>
                                    </select>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <Button @click="applyFilters" class="bg-indigo-600 hover:bg-indigo-700">Filter</Button>
                                <Button @click="openNewEntry" class="bg-green-600 hover:bg-green-700">New Entry</Button>
                            </div>
                        </div>

                        <div v-if="successMessage" class="bg-green-50 dark:bg-green-900/30 p-4 rounded-lg mb-6">
                            <div class="text-sm text-green-700 dark:text-green-300">{{ successMessage }}</div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <div class="p-4 rounded-lg bg-green-50 dark:bg-green-900/30">
                                <div class="text-sm text-green-700 dark:text-green-300">Income</div>
                                <div class="text-2xl font-bold text-green-800 dark:text-green-200">{{ currency }}{{ summary.income }}</div>
                            </div>
                            <div class="p-4 rounded-lg bg-red-50 dark:bg-red-900/30">
                                <div class="text-sm text-red-700 dark:text-red-300">Expenses</div>
                                <div class="text-2xl font-bold text-red-800 dark:text-red-200">{{ currency }}{{ summary.expense }}</div>
                            </div>
                            <div class="p-4 rounded-lg bg-blue-50 dark:bg-blue-900/30">
                                <div class="text-sm text-blue-700 dark:text-blue-300">Profit</div>
                                <div class="text-2xl font-bold text-blue-800 dark:text-blue-200">{{ currency }}{{ summary.profit }}</div>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Category</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Type</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">AR/AP</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Amount</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Description</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Party</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Due</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                                    <tr v-for="entry in entries.data" :key="entry.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">{{ entry.date }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">{{ entry.category?.name || '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <span :class="{
                                                'px-2 py-1 rounded-full text-xs': true,
                                                'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': entry.type === 'credit',
                                                'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': entry.type === 'debit'
                                            }">{{ entry.type }}</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">{{ entry.ar_ap_type || '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">{{ currency }}{{ entry.amount }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">{{ entry.description }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">{{ entry.party_name || '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">{{ entry.due_date || '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <button @click="editEntry(entry)" class="text-indigo-600 hover:text-indigo-800 mr-3">Edit</button>
                                            <button @click="deleteEntry(entry)" class="text-red-600 hover:text-red-800">Delete</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4 flex justify-between items-center">
                            <div class="text-sm text-gray-500">Showing {{ entries.data.length }} of {{ entries.total }} entries</div>
                            <div class="space-x-2">
                                <button :disabled="!entries.prev_page_url" @click="goPage(entries.prev_page_url)" class="px-3 py-1 border rounded disabled:opacity-50">Prev</button>
                                <button :disabled="!entries.next_page_url" @click="goPage(entries.next_page_url)" class="px-3 py-1 border rounded disabled:opacity-50">Next</button>
                            </div>
                        </div>

                        <div class="mt-8 border-t dark:border-gray-700 pt-6">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-lg font-semibold">Sync from Orders</h3>
                            </div>
                            <div>
                                <button @click="syncOrders" class="px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-800">Sync Now</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
    
    <!-- Entry Dialog (package) -->
    <Dialog :open="showModal" @update:open="val => (showModal = val)">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ editing ? 'Edit Entry' : 'New Entry' }}</DialogTitle>
                <DialogDescription>Fill in the details below.</DialogDescription>
            </DialogHeader>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div class="md:col-span-2">
                    <Label class="mb-1 block">Entry Type</Label>
                    <select v-model="entryType" class="mt-1 block w-full rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] dark:bg-input/30">
                        <option value="income">Income</option>
                        <option value="expense">Expense</option>
                        <option value="customer_invoice">Customer Invoice</option>
                        <option value="customer_payment">Customer Payment</option>
                        <option value="vendor_bill">Vendor Bill</option>
                        <option value="vendor_payment">Vendor Payment</option>
                        <option value="payroll">Payroll</option>
                    </select>
                </div>
                <div>
                    <Label class="mb-1 block">Date</Label>
                    <Input v-model="form.date" type="date" />
                </div>
                <div v-if="showDueDate">
                    <Label class="mb-1 block">Due date</Label>
                    <Input v-model="form.due_date" type="date" />
                </div>
                <div v-if="showCategory">
                    <Label class="mb-1 block">Category</Label>
                    <select v-model="form.category_id" class="mt-1 block w-full rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] dark:bg-input/30">
                        <option value="">No Category</option>
                        <option v-for="c in availableCategories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div>
                    <Label class="mb-1 block">Amount</Label>
                    <Input v-model.number="form.amount" type="number" step="0.01" min="0" />
                </div>
                <div class="md:col-span-2">
                    <Label class="mb-1 block">Description</Label>
                    <Input v-model="form.description" type="text" placeholder="Description" />
                </div>
                <div v-if="showParty">
                    <Label class="mb-1 block">{{ partyLabel }}</Label>
                    <template v-if="entryType === 'vendor_bill' || entryType === 'vendor_payment'">
                        <select v-model.number="form.party_id" class="mt-1 block w-full rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] dark:bg-input/30">
                            <option :value="null">Select supplier</option>
                            <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </template>
                    <template v-else>
                        <Input v-model="form.party_name" type="text" :placeholder="partyPlaceholder" />
                    </template>
                </div>
                <div v-if="showReference">
                    <Label class="mb-1 block">Reference</Label>
                    <Input v-model="form.reference" type="text" :placeholder="referencePlaceholder" />
                </div>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <DialogClose as-child>
                    <Button @click="closeModal" class="bg-gray-500 hover:bg-gray-600">Cancel</Button>
                </DialogClose>
                <Button @click="saveEntry" class="bg-indigo-600 hover:bg-indigo-700">Save</Button>
            </div>
        </DialogContent>
    </Dialog>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import { Head, router, usePage, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Dialog } from '@/Components/ui/dialog';
import { DialogContent } from '@/Components/ui/dialog';
import { DialogHeader } from '@/Components/ui/dialog';
import { DialogTitle } from '@/Components/ui/dialog';
import { DialogDescription } from '@/Components/ui/dialog';
import { DialogClose } from '@/Components/ui/dialog';

const props = defineProps({
    entries: Object,
    categories: Array,
    suppliers: Array,
    filters: Object,
    summary: Object,
});

const page = usePage();
const currency = computed(() => page.props.settings?.currency || 'USD');
const successMessage = computed(() => (page.props?.success) || (page.props?.flash?.success) || '');

const filters = ref({
    search: props.filters.search || '',
    category_id: props.filters.category_id || '',
    type: props.filters.type || '',
    start_date: props.filters.start_date || '',
    end_date: props.filters.end_date || '',
});

const entries = ref(props.entries);
const categories = ref(props.categories || []);
const suppliers = ref(props.suppliers || []);
const summary = ref(props.summary || { income: '0.00', expense: '0.00', profit: '0.00' });

const showModal = ref(false);
const editing = ref(false);
const form = ref({ id: null, date: '', due_date: '', category_id: '', type: 'debit', ar_ap_type: '', amount: 0, description: '', party_name: '', reference: '', is_payroll: false });

// Simplified UX helpers
const entryType = ref('expense');
const showDueDate = computed(() => ['customer_invoice', 'vendor_bill'].includes(entryType.value));
const showCategory = computed(() => ['income', 'expense', 'payroll'].includes(entryType.value));
const showParty = computed(() => ['customer_invoice', 'customer_payment', 'vendor_bill', 'vendor_payment'].includes(entryType.value));
const showReference = computed(() => ['customer_invoice', 'vendor_bill'].includes(entryType.value));

const partyLabel = computed(() => ['customer_invoice', 'customer_payment'].includes(entryType.value) ? 'Customer' : 'Supplier');
const partyPlaceholder = computed(() => ['customer_invoice', 'customer_payment'].includes(entryType.value) ? 'Customer name' : 'Supplier name');
const referencePlaceholder = computed(() => ['customer_invoice'].includes(entryType.value) ? 'Invoice No.' : 'Bill No.');

const availableCategories = computed(() => {
    if (entryType.value === 'payroll') {
        return categories.value.filter(c => c.subtype === 'payroll');
    }
    if (entryType.value === 'expense') {
        return categories.value.filter(c => c.type === 'expense' && c.subtype !== 'payroll');
    }
    if (entryType.value === 'income') {
        return categories.value.filter(c => c.type === 'income');
    }
    return categories.value;
});

const sync = ref({});

const goPage = (url) => {
    if (!url) return;
    router.visit(url, { preserveScroll: true, preserveState: true });
};

const applyFilters = () => {
    router.get(route('accounting.index'), filters.value, { preserveState: true, preserveScroll: true });
};

const openNewEntry = () => {
    form.value = { id: null, date: new Date().toISOString().slice(0,10), due_date: '', category_id: '', type: 'debit', ar_ap_type: '', amount: 0, description: '', party_name: '', reference: '', is_payroll: false };
    editing.value = false;
    entryType.value = 'expense';
    showModal.value = true;
};

const editEntry = (entry) => {
    form.value = { id: entry.id, date: entry.date, due_date: entry.due_date || '', category_id: entry.category?.id || '', type: entry.type, ar_ap_type: entry.ar_ap_type || '', amount: parseFloat(entry.amount.replace(/,/g,'')), description: entry.description, party_name: entry.party_name || '', reference: entry.reference || '', is_payroll: !!entry.is_payroll };
    editing.value = true;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
};

const saveEntry = () => {
    // Map simplified entryType to accounting fields
    const payload = { ...form.value };
    // If supplier selected, map party_name from suppliers
    if ((entryType.value === 'vendor_bill' || entryType.value === 'vendor_payment') && payload.party_id) {
        const sup = suppliers.value.find(s => s.id === payload.party_id);
        payload.party_name = sup ? sup.name : payload.party_name;
    }
    if (entryType.value === 'income') {
        payload.type = 'credit';
        payload.ar_ap_type = '';
        payload.is_payroll = false;
    } else if (entryType.value === 'expense') {
        payload.type = 'debit';
        payload.ar_ap_type = '';
        payload.is_payroll = false;
    } else if (entryType.value === 'payroll') {
        payload.type = 'debit';
        payload.ar_ap_type = '';
        payload.is_payroll = true;
    } else if (entryType.value === 'customer_invoice') {
        payload.type = 'debit'; // AR debit
        payload.ar_ap_type = 'AR';
    } else if (entryType.value === 'customer_payment') {
        payload.type = 'credit'; // AR credit
        payload.ar_ap_type = 'AR';
    } else if (entryType.value === 'vendor_bill') {
        payload.type = 'credit'; // AP credit
        payload.ar_ap_type = 'AP';
    } else if (entryType.value === 'vendor_payment') {
        payload.type = 'debit'; // AP debit
        payload.ar_ap_type = 'AP';
    }
    if (editing.value && payload.id) {
        router.put(route('accounting.entries.update', payload.id), payload, { preserveScroll: true, onSuccess: () => { showModal.value = false; } });
    } else {
        router.post(route('accounting.entries.store'), payload, { preserveScroll: true, onSuccess: () => { showModal.value = false; } });
    }
};

const deleteEntry = (entry) => {
    if (!confirm('Delete this entry?')) return;
    router.delete(route('accounting.entries.destroy', entry.id), { preserveScroll: true });
};

const syncOrders = () => {
    router.post(route('accounting.sync'), {}, {
        onSuccess: () => {
            // Force a hard refresh to reflect updated values immediately
            window.location.href = route('accounting.index');
        },
    });
};
</script>


