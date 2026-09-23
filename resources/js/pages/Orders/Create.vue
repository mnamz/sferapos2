<template>
    <Head title="New Sale" />
    <AppLayout
        :collapse-sidebar="true"
        :breadcrumbs="[
            { name: 'Orders', href: route('orders.index') },
            { name: 'New Sale', href: route('orders.create') },
        ]"
    >
        <div class="px-3 py-4 lg:px-6">
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-12">
                <!-- ============ Catalogue ============ -->
                <section class="space-y-3 lg:col-span-7">
                    <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-gray-800">
                        <div class="relative">
                            <ScanLine class="pointer-events-none absolute top-1/2 left-3 h-5 w-5 -translate-y-1/2 text-gray-400" />
                            <input
                                ref="searchInput"
                                v-model="search"
                                @keydown.enter.prevent="onSearchEnter"
                                type="text"
                                placeholder="Scan barcode or search product, part, service, phone model…"
                                class="w-full rounded-lg border border-gray-300 py-3 pr-3 pl-11 text-base focus:border-transparent focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                autocomplete="off"
                            />
                        </div>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <button
                                v-for="t in typeFilters"
                                :key="t.value"
                                type="button"
                                @click="typeFilter = t.value"
                                :class="[
                                    'rounded-full px-3 py-1 text-sm transition',
                                    typeFilter === t.value
                                        ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900'
                                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200',
                                ]"
                            >
                                {{ t.label }} <span class="opacity-60">{{ typeCounts[t.value] }}</span>
                            </button>
                            <button
                                type="button"
                                @click="addCustomLine()"
                                class="ml-auto flex items-center gap-1 rounded-full border border-dashed border-gray-400 px-3 py-1 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-500 dark:text-gray-200 dark:hover:bg-gray-700"
                            >
                                <Plus class="h-4 w-4" /> Custom charge
                            </button>
                        </div>
                    </div>

                    <!-- Quick services -->
                    <div v-if="!search && quickServices.length" class="rounded-xl bg-white p-4 shadow-sm dark:bg-gray-800">
                        <div class="mb-2 text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Quick services</div>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            <button
                                v-for="p in quickServices"
                                :key="p.id"
                                type="button"
                                @click="addProduct(p)"
                                class="rounded-lg border border-purple-200 bg-purple-50 px-3 py-2 text-left hover:bg-purple-100 dark:border-purple-900 dark:bg-purple-900/20 dark:hover:bg-purple-900/40"
                            >
                                <div class="line-clamp-2 text-sm font-medium text-purple-900 dark:text-purple-100">{{ p.name }}</div>
                                <div class="text-xs text-purple-700 dark:text-purple-300">RM {{ money(p.price) }}</div>
                            </button>
                        </div>
                    </div>

                    <div class="rounded-xl bg-white shadow-sm dark:bg-gray-800">
                        <div v-if="loadingProducts" class="p-8 text-center text-gray-500">Loading catalogue…</div>
                        <div v-else-if="!filteredProducts.length" class="p-8 text-center text-gray-500 dark:text-gray-400">
                            No matches.
                            <button type="button" class="text-blue-600 hover:underline" @click="addCustomLine(search)">Add “{{ search || 'custom item' }}” as a custom charge</button>
                        </div>
                        <ul v-else class="max-h-[60vh] divide-y divide-gray-100 overflow-y-auto dark:divide-gray-700">
                            <li v-for="p in filteredProducts" :key="p.id">
                                <button type="button" @click="addProduct(p)" class="flex w-full items-center gap-3 px-4 py-2.5 text-left hover:bg-blue-50 dark:hover:bg-gray-700">
                                    <div :class="['flex h-9 w-9 shrink-0 items-center justify-center rounded-lg', typeStyle(p.type).bg]">
                                        <component :is="typeStyle(p.type).icon" :class="['h-5 w-5', typeStyle(p.type).fg]" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="truncate font-medium text-gray-900 dark:text-gray-100">{{ p.name }}</div>
                                        <div class="truncate text-xs text-gray-500 dark:text-gray-400">
                                            <span>{{ typeLabel(p.type) }}</span>
                                            <span v-if="p.sku || p.barcode"> · {{ p.sku || p.barcode }}</span>
                                            <span v-if="p.compatible_models"> · fits {{ p.compatible_models }}</span>
                                            <span v-if="p.warranty_days"> · {{ p.warranty_days }}d warranty</span>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-semibold text-gray-900 dark:text-gray-100">RM {{ money(p.price) }}</div>
                                        <div v-if="p.type !== 'service'" :class="['text-xs', p.stock <= 3 ? 'text-red-600' : 'text-gray-500 dark:text-gray-400']">
                                            {{ p.stock }} in stock<span v-if="p.serial_tracked"> · IMEI</span>
                                        </div>
                                    </div>
                                </button>
                            </li>
                        </ul>
                    </div>
                </section>

                <!-- ============ Ticket ============ -->
                <section class="lg:col-span-5">
                    <div class="space-y-3 lg:sticky lg:top-4">
                        <div
                            v-if="repairJob"
                            class="flex items-center justify-between rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 dark:border-amber-700 dark:bg-amber-900/20"
                        >
                            <div>
                                <div class="text-xs font-semibold text-amber-700 uppercase dark:text-amber-300">Repair checkout</div>
                                <div class="font-semibold text-amber-900 dark:text-amber-100">{{ repairJob.job_number }} · {{ repairJob.device }}</div>
                                <div v-if="repairJob.imei" class="font-mono text-xs text-amber-800 dark:text-amber-200">{{ repairJob.imei }}</div>
                            </div>
                            <Link :href="route('repairs.show', repairJob.id)" class="text-sm text-amber-800 underline dark:text-amber-200">Open job</Link>
                        </div>

                        <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-gray-800">
                            <CustomerPicker v-model="customer" :locked="!!repairJob?.customer" placeholder="Customer: search name / phone (optional for walk-in)" />
                        </div>

                        <div class="rounded-xl bg-white shadow-sm dark:bg-gray-800">
                            <div class="flex items-center justify-between border-b border-gray-100 px-4 py-2 dark:border-gray-700">
                                <div class="font-semibold text-gray-900 dark:text-gray-100">Items ({{ lines.length }})</div>
                                <button v-if="lines.length" type="button" class="text-xs text-red-600 hover:underline" @click="clearLines">Clear</button>
                            </div>
                            <div v-if="!lines.length" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                Scan or tap an item to start the sale.
                            </div>
                            <ul v-else class="max-h-[46vh] divide-y divide-gray-100 overflow-y-auto dark:divide-gray-700">
                                <li v-for="(line, idx) in lines" :key="line.key" class="px-4 py-3">
                                    <div class="flex items-start gap-2">
                                        <div class="min-w-0 flex-1">
                                            <input
                                                v-if="!line.product"
                                                v-model="line.name"
                                                placeholder="Describe the charge (e.g. Screen replacement labour)"
                                                class="w-full rounded border border-gray-300 px-2 py-1 text-sm font-medium dark:border-gray-600 dark:bg-gray-700"
                                            />
                                            <div v-else class="font-medium text-gray-900 dark:text-gray-100">{{ line.name }}</div>
                                            <div class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                                <span :class="['rounded px-1.5 py-0.5 font-medium', typeStyle(line.type).badge]">{{ line.product ? typeLabel(line.type) : 'Custom' }}</span>
                                                <span v-if="line.product && line.type !== 'service'">{{ line.product.stock }} in stock</span>
                                                <label class="flex items-center gap-1">
                                                    <ShieldCheck class="h-3.5 w-3.5" />
                                                    <input
                                                        v-model.number="line.warranty_days"
                                                        type="number"
                                                        min="0"
                                                        placeholder="0"
                                                        class="w-12 rounded border border-gray-200 px-1 py-0 text-xs dark:border-gray-600 dark:bg-gray-700"
                                                    />
                                                    days
                                                </label>
                                                <button type="button" class="hover:text-gray-800 dark:hover:text-gray-200" @click="line.showRemark = !line.showRemark">
                                                    {{ line.remark ? 'Note: ' + line.remark : '+ note' }}
                                                </button>
                                            </div>
                                            <input
                                                v-if="line.showRemark"
                                                v-model="line.remark"
                                                placeholder="Line note (shown on receipt)"
                                                class="mt-1 w-full rounded border border-gray-300 px-2 py-1 text-xs dark:border-gray-600 dark:bg-gray-700"
                                            />
                                        </div>
                                        <button type="button" @click="lines.splice(idx, 1)" class="rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/30">
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </div>

                                    <div class="mt-2 flex items-center gap-2">
                                        <div class="flex items-center rounded-lg border border-gray-300 dark:border-gray-600">
                                            <button type="button" :disabled="line.serial_tracked" @click="line.quantity > 1 && line.quantity--" class="px-2 py-1 disabled:opacity-30">
                                                <Minus class="h-4 w-4" />
                                            </button>
                                            <input
                                                v-model.number="line.quantity"
                                                type="number"
                                                min="1"
                                                :readonly="line.serial_tracked"
                                                class="w-12 border-0 bg-transparent p-0 text-center text-sm focus:ring-0"
                                            />
                                            <button type="button" :disabled="line.serial_tracked" @click="line.quantity++" class="px-2 py-1 disabled:opacity-30">
                                                <Plus class="h-4 w-4" />
                                            </button>
                                        </div>
                                        <span class="text-gray-400">×</span>
                                        <div class="relative">
                                            <span class="absolute top-1/2 left-2 -translate-y-1/2 text-xs text-gray-400">RM</span>
                                            <input
                                                v-model.number="line.price"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                class="w-28 rounded-lg border border-gray-300 py-1 pr-2 pl-8 text-right text-sm dark:border-gray-600 dark:bg-gray-700"
                                            />
                                        </div>
                                        <div class="ml-auto text-right font-semibold text-gray-900 dark:text-gray-100">RM {{ money(lineTotal(line)) }}</div>
                                    </div>
                                    <div v-if="line.product && Number(line.price) !== Number(line.product.price)" class="mt-1 text-xs text-amber-600">
                                        List price RM {{ money(line.product.price) }}
                                    </div>

                                    <!-- IMEI / serial picker -->
                                    <div v-if="line.serial_tracked" class="mt-2 space-y-1">
                                        <div class="relative">
                                            <input
                                                v-model="line.serialScan"
                                                placeholder="Scan / type IMEI or serial"
                                                class="w-full rounded border border-gray-300 px-2 py-1 font-mono text-sm dark:border-gray-600 dark:bg-gray-700"
                                                @focus="line.serialOpen = true"
                                                @blur="line.serialOpen = false"
                                                @keydown.enter.prevent="addSerial(line)"
                                            />
                                            <ul
                                                v-if="line.serialOpen && serialSuggestions(line).length"
                                                class="absolute z-20 mt-1 max-h-40 w-full overflow-auto rounded border border-gray-200 bg-white shadow-lg dark:border-gray-600 dark:bg-gray-700"
                                            >
                                                <li
                                                    v-for="sn in serialSuggestions(line)"
                                                    :key="sn"
                                                    class="cursor-pointer px-2 py-1 font-mono text-sm hover:bg-blue-50 dark:hover:bg-gray-600"
                                                    @mousedown.prevent="addSerial(line, sn)"
                                                >
                                                    {{ sn }}
                                                </li>
                                            </ul>
                                        </div>
                                        <div class="flex flex-wrap gap-1">
                                            <span v-for="sn in line.serials" :key="sn" class="flex items-center gap-1 rounded bg-gray-100 px-2 py-0.5 font-mono text-xs dark:bg-gray-700">
                                                {{ sn }}
                                                <button type="button" class="text-gray-500 hover:text-red-600" @click="removeSerial(line, sn)">&times;</button>
                                            </span>
                                            <span v-if="!line.serials.length" class="text-xs text-red-500">Select at least one IMEI / serial</span>
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </div>

                        <!-- Totals & payment -->
                        <div class="space-y-3 rounded-xl bg-white p-4 shadow-sm dark:bg-gray-800">
                            <div class="space-y-1.5 text-sm">
                                <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-300">Subtotal</span><span>RM {{ money(subtotal) }}</span></div>
                                <div class="flex items-center justify-between">
                                    <span class="flex items-center gap-1 text-gray-600 dark:text-gray-300">
                                        Tax
                                        <input v-model.number="taxPercentage" type="number" min="0" max="100" step="0.01" class="w-14 rounded border border-gray-200 px-1 py-0 text-xs dark:border-gray-600 dark:bg-gray-700" />%
                                    </span>
                                    <span>RM {{ money(tax) }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-600 dark:text-gray-300">Discount</span>
                                    <input v-model.number="discount" type="number" min="0" step="0.01" class="w-28 rounded border border-gray-200 px-2 py-0.5 text-right dark:border-gray-600 dark:bg-gray-700" />
                                </div>
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-gray-600 dark:text-gray-300">Channel</span>
                                    <select v-model="deliveryMethod" class="rounded border border-gray-200 py-0.5 text-sm dark:border-gray-600 dark:bg-gray-700">
                                        <option v-for="m in deliveryMethods" :key="m.value" :value="m.value">{{ m.label }}</option>
                                    </select>
                                </div>
                                <div v-if="deliveryMethod === 'delivery'" class="flex items-center justify-between">
                                    <span class="text-gray-600 dark:text-gray-300">Delivery fee</span>
                                    <input v-model.number="deliveryCost" type="number" min="0" step="0.01" class="w-28 rounded border border-gray-200 px-2 py-0.5 text-right dark:border-gray-600 dark:bg-gray-700" />
                                </div>
                                <div class="flex justify-between border-t border-gray-100 pt-2 text-lg font-bold dark:border-gray-700">
                                    <span>Total</span><span>RM {{ money(total) }}</span>
                                </div>
                                <div v-if="deposit > 0" class="flex justify-between text-green-700 dark:text-green-400">
                                    <span>Deposit already paid</span><span>− RM {{ money(deposit) }}</span>
                                </div>
                                <div v-if="deposit > 0" class="flex justify-between font-semibold">
                                    <span>To collect</span><span>RM {{ money(toCollect) }}</span>
                                </div>
                            </div>

                            <div class="grid grid-cols-4 gap-1.5">
                                <button
                                    v-for="m in paymentMethods"
                                    :key="m.value"
                                    type="button"
                                    @click="paymentMethod = m.value"
                                    :class="[
                                        'flex flex-col items-center gap-0.5 rounded-lg border px-1 py-2 text-xs font-medium transition',
                                        paymentMethod === m.value
                                            ? 'border-blue-600 bg-blue-600 text-white'
                                            : 'border-gray-200 bg-gray-50 text-gray-700 hover:bg-blue-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200',
                                    ]"
                                >
                                    <component :is="m.icon" class="h-5 w-5" />
                                    {{ m.label }}
                                </button>
                            </div>

                            <div>
                                <div class="flex items-center gap-2">
                                    <label class="w-24 text-sm text-gray-600 dark:text-gray-300">Received</label>
                                    <input
                                        v-model.number="tendered"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-right text-lg font-semibold dark:border-gray-600 dark:bg-gray-700"
                                    />
                                </div>
                                <div class="mt-1.5 flex flex-wrap justify-end gap-1.5">
                                    <button type="button" @click="tendered = toCollect" class="rounded bg-gray-100 px-2 py-1 text-xs hover:bg-gray-200 dark:bg-gray-700">Exact</button>
                                    <button
                                        v-for="note in cashNotes"
                                        :key="note"
                                        type="button"
                                        @click="tendered = note"
                                        class="rounded bg-gray-100 px-2 py-1 text-xs hover:bg-gray-200 dark:bg-gray-700"
                                    >
                                        {{ note }}
                                    </button>
                                    <button type="button" @click="tendered = 0" class="rounded bg-gray-100 px-2 py-1 text-xs hover:bg-gray-200 dark:bg-gray-700">Pay later</button>
                                </div>
                                <div class="mt-2 flex justify-between text-sm">
                                    <span v-if="change > 0" class="font-semibold text-green-700 dark:text-green-400">Change: RM {{ money(change) }}</span>
                                    <span v-else-if="due > 0" class="font-semibold text-red-600">Balance due: RM {{ money(due) }}</span>
                                    <span v-else class="text-gray-500">Fully paid</span>
                                </div>
                            </div>

                            <textarea
                                v-model="remarks"
                                rows="2"
                                placeholder="Order remark (optional)"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                            ></textarea>

                            <button
                                type="button"
                                @click="saveOrder"
                                :disabled="saving || !lines.length"
                                class="flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 py-3.5 text-lg font-bold text-white shadow hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <CheckCircle2 class="h-5 w-5" />
                                {{ saving ? 'Saving…' : `Charge RM ${money(total)}` }}
                            </button>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <!-- Success dialog -->
        <div v-if="completed" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-xl dark:bg-gray-800">
                <CheckCircle2 class="mx-auto h-14 w-14 text-emerald-500" />
                <div class="mt-2 text-xl font-bold text-gray-900 dark:text-gray-100">Sale completed</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Invoice #{{ completed.formatted_invoice_number }}</div>
                <div v-if="completed.change > 0" class="mt-3 rounded-lg bg-green-50 p-3 text-2xl font-bold text-green-700 dark:bg-green-900/30 dark:text-green-300">
                    Change RM {{ money(completed.change) }}
                </div>
                <div v-else-if="completed.due > 0" class="mt-3 rounded-lg bg-red-50 p-3 text-lg font-semibold text-red-700 dark:bg-red-900/30 dark:text-red-300">
                    Balance due RM {{ money(completed.due) }}
                </div>
                <div class="mt-5 grid grid-cols-2 gap-2">
                    <a :href="route('orders.invoice', completed.id)" target="_blank" class="flex items-center justify-center gap-1 rounded-lg bg-gray-900 py-2 text-sm font-medium text-white hover:bg-gray-700 dark:bg-gray-100 dark:text-gray-900">
                        <Printer class="h-4 w-4" /> Receipt
                    </a>
                    <Link :href="route('orders.show', completed.id)" class="rounded-lg border border-gray-300 py-2 text-sm font-medium hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-700">View order</Link>
                    <a
                        v-if="completedWhatsapp"
                        :href="completedWhatsapp"
                        target="_blank"
                        class="col-span-2 rounded-lg bg-green-600 py-2 text-sm font-medium text-white hover:bg-green-700"
                    >
                        Send thank-you on WhatsApp
                    </a>
                    <button type="button" @click="newSale" class="col-span-2 rounded-lg bg-emerald-600 py-2.5 font-semibold text-white hover:bg-emerald-700">New sale</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import CustomerPicker from '@/Components/service/CustomerPicker.vue';
import { money, whatsappUrl } from '@/composables/useFlashToast';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Banknote,
    CheckCircle2,
    CreditCard,
    Cpu,
    Landmark,
    Minus,
    Plus,
    Printer,
    ScanLine,
    ShieldCheck,
    Smartphone,
    Trash2,
    Wallet,
    Wrench,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

const props = defineProps({
    tax_percentage: { type: [Number, String], default: 0 },
    repair_job: { type: Object, default: null },
    default_warranty_days: { type: Number, default: 30 },
});

const repairJob = props.repair_job;
const products = ref([]);
const loadingProducts = ref(true);
const search = ref('');
const searchInput = ref(null);
const typeFilter = ref('');
const lines = ref([]);
const customer = ref(repairJob?.customer ?? null);
const taxPercentage = ref(Number(props.tax_percentage) || 0);
const discount = ref(0);
const deliveryMethod = ref('walk-in');
const deliveryCost = ref(0);
const paymentMethod = ref('cash');
const tendered = ref(0);
const remarks = ref(repairJob ? `Repair ${repairJob.job_number}` : '');
const saving = ref(false);
const completed = ref(null);
const deposit = computed(() => Number(repairJob?.deposit || 0));

let keySeq = 0;

const typeFilters = [
    { value: '', label: 'All' },
    { value: 'product', label: 'Products' },
    { value: 'part', label: 'Parts' },
    { value: 'service', label: 'Services' },
];
const paymentMethods = [
    { value: 'cash', label: 'Cash', icon: Banknote },
    { value: 'card', label: 'Card', icon: CreditCard },
    { value: 'e-wallet', label: 'E-Wallet', icon: Wallet },
    { value: 'online_transfer', label: 'Transfer', icon: Landmark },
];
const deliveryMethods = [
    { value: 'walk-in', label: 'Walk-in' },
    { value: 'pickup', label: 'Pickup' },
    { value: 'delivery', label: 'Delivery' },
    { value: 'shopee', label: 'Shopee' },
    { value: 'tiktok', label: 'TikTok' },
    { value: 'lazada', label: 'Lazada' },
];

const typeLabel = (t) => ({ product: 'Product', part: 'Spare part', service: 'Service' })[t] || 'Product';
const typeStyle = (t) =>
    ({
        part: { icon: Cpu, bg: 'bg-amber-100 dark:bg-amber-900/40', fg: 'text-amber-700 dark:text-amber-300', badge: 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200' },
        service: { icon: Wrench, bg: 'bg-purple-100 dark:bg-purple-900/40', fg: 'text-purple-700 dark:text-purple-300', badge: 'bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-200' },
    })[t] || { icon: Smartphone, bg: 'bg-blue-100 dark:bg-blue-900/40', fg: 'text-blue-700 dark:text-blue-300', badge: 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-200' };

const normalize = (s) => String(s || '').toLowerCase().replace(/[\s\-/*]/g, '');
const typeCounts = computed(() => {
    const c = { '': products.value.length, product: 0, part: 0, service: 0 };
    products.value.forEach((p) => (c[p.type || 'product'] = (c[p.type || 'product'] || 0) + 1));
    return c;
});
const filteredProducts = computed(() => {
    const q = normalize(search.value);
    return products.value
        .filter((p) => !typeFilter.value || (p.type || 'product') === typeFilter.value)
        .filter((p) => !q || [p.name, p.barcode, p.sku, p.brand, p.compatible_models].some((f) => normalize(f).includes(q)))
        .slice(0, 80);
});
const quickServices = computed(() => products.value.filter((p) => p.type === 'service').slice(0, 8));

const lineTotal = (l) => (Number(l.price) || 0) * (Number(l.quantity) || 0);
const round2 = (n) => Math.round((Number(n) || 0) * 100) / 100;
const subtotal = computed(() => round2(lines.value.reduce((s, l) => s + lineTotal(l), 0)));
const tax = computed(() => round2(subtotal.value * ((Number(taxPercentage.value) || 0) / 100)));
const total = computed(() =>
    Math.max(0, round2(subtotal.value + tax.value + (deliveryMethod.value === 'delivery' ? Number(deliveryCost.value) || 0 : 0) - (Number(discount.value) || 0))),
);
const toCollect = computed(() => Math.max(0, round2(total.value - deposit.value)));
const received = computed(() => round2((Number(tendered.value) || 0) + deposit.value));
const paidAmount = computed(() => Math.min(received.value, total.value));
const change = computed(() => Math.max(0, round2(received.value - total.value)));
const due = computed(() => Math.max(0, round2(total.value - received.value)));
const cashNotes = computed(() => {
    const t = toCollect.value;
    if (t <= 0) return [];
    return [...new Set([10, 20, 50, 100].map((n) => Math.ceil(t / n) * n))].filter((n) => n > t).slice(0, 3);
});

const makeLine = (product, overrides = {}) => ({
    key: ++keySeq,
    product,
    name: product?.name ?? '',
    type: product?.type ?? 'service',
    price: product ? Number(product.price) : 0,
    cost_price: 0,
    quantity: product?.serial_tracked ? 0 : 1,
    remark: '',
    showRemark: false,
    warranty_days: product?.warranty_days ?? null,
    serial_tracked: !!product?.serial_tracked,
    serials: [],
    serialScan: '',
    availableSerials: [],
    serialOpen: false,
    ...overrides,
});

const addProduct = (p) => {
    if (p.type !== 'service' && !p.serial_tracked) {
        const existing = lines.value.find((l) => l.product?.id === p.id);
        const inCart = existing ? existing.quantity : 0;
        if (inCart + 1 > p.stock) {
            toast.warning(`Only ${p.stock} × ${p.name} in stock`);
            return;
        }
        if (existing) {
            existing.quantity++;
            return;
        }
    }
    const line = makeLine(p);
    lines.value.push(line);
    if (line.serial_tracked) loadSerials(line);
    search.value = '';
    searchInput.value?.focus();
};

const addCustomLine = (name = '') => {
    lines.value.push(makeLine(null, { name, warranty_days: props.default_warranty_days }));
    search.value = '';
};

const onSearchEnter = () => {
    const q = search.value.trim();
    if (!q) return;
    const exact = products.value.find((p) => p.barcode === q || (p.sku && p.sku.toLowerCase() === q.toLowerCase()));
    if (exact) return addProduct(exact);
    if (filteredProducts.value.length === 1) return addProduct(filteredProducts.value[0]);
    if (!filteredProducts.value.length) toast.info('No match — tap "Custom charge" to bill it manually');
};

const clearLines = () => {
    lines.value = [];
};

// ---- serials / IMEI ----
const loadSerials = async (line) => {
    try {
        const { data } = await axios.get(route('products.serials.index', line.product.id));
        line.availableSerials = data.serials.map((s) => s.serial_number);
    } catch {
        line.availableSerials = [];
    }
};
const serialSuggestions = (line) => {
    const chosen = new Set(line.serials);
    const q = (line.serialScan || '').trim().toLowerCase();
    return line.availableSerials.filter((sn) => !chosen.has(sn) && (!q || sn.toLowerCase().includes(q))).slice(0, 8);
};
const addSerial = (line, explicit = null) => {
    const sn = (explicit ?? line.serialScan ?? '').trim();
    if (!sn || line.serials.includes(sn)) {
        line.serialScan = '';
        return;
    }
    if (!line.availableSerials.includes(sn)) {
        toast.error(`IMEI / serial "${sn}" is not in stock for this product`);
        line.serialScan = '';
        return;
    }
    line.serials.push(sn);
    line.quantity = line.serials.length;
    line.serialScan = '';
    line.serialOpen = false;
};
const removeSerial = (line, sn) => {
    line.serials = line.serials.filter((s) => s !== sn);
    line.quantity = line.serials.length;
};

// ---- save ----
const saveOrder = async () => {
    const bad = lines.value.find((l) => (!l.product && !l.name.trim()) || (l.serial_tracked && !l.serials.length) || (!l.serial_tracked && l.quantity < 1));
    if (bad) {
        toast.error(bad.serial_tracked ? `Pick an IMEI / serial for ${bad.name}` : 'Every line needs a description and quantity');
        return;
    }
    saving.value = true;
    try {
        const payload = {
            items: lines.value.map((l) => ({
                id: l.product?.id ?? null,
                name: l.product ? null : l.name.trim(),
                quantity: l.quantity,
                price: Number(l.price) || 0,
                cost_price: l.product ? undefined : Number(l.cost_price) || 0,
                remark: l.remark || null,
                warranty_days: l.warranty_days === '' || l.warranty_days === null ? null : Number(l.warranty_days),
                ...(l.serial_tracked ? { serials: l.serials } : {}),
            })),
            customer_id: customer.value?.id || null,
            repair_job_id: repairJob?.id || null,
            subtotal: subtotal.value,
            tax: tax.value,
            delivery_cost: deliveryMethod.value === 'delivery' ? Number(deliveryCost.value) || 0 : 0,
            discount: Number(discount.value) || 0,
            total: total.value,
            paid_amount: paidAmount.value,
            due_amount: due.value,
            change_amount: change.value,
            payment_method: paymentMethod.value,
            delivery_method: deliveryMethod.value,
            remarks: remarks.value,
        };
        const { data } = await axios.post('/orders', payload);
        if (!data.success) throw new Error(data.message);
        completed.value = { ...data.order, change: change.value, due: due.value, customer: customer.value };
        loadProducts();
    } catch (error) {
        const errs = error.response?.data?.errors;
        toast.error(errs ? Object.values(errs).flat().join('\n') : error.response?.data?.message || error.message || 'Failed to save sale');
    } finally {
        saving.value = false;
    }
};

const completedWhatsapp = computed(() => {
    const c = completed.value?.customer;
    if (!c?.phone) return null;
    return whatsappUrl(c.phone, `Hi ${c.name}, thank you for your purchase! Invoice #${completed.value.formatted_invoice_number}, total RM ${money(completed.value.total)}.`);
});

const newSale = () => {
    if (repairJob) {
        router.visit(route('repairs.show', repairJob.id));
        return;
    }
    completed.value = null;
    lines.value = [];
    customer.value = null;
    discount.value = 0;
    deliveryCost.value = 0;
    deliveryMethod.value = 'walk-in';
    paymentMethod.value = 'cash';
    tendered.value = 0;
    remarks.value = '';
    searchInput.value?.focus();
};

const loadProducts = async () => {
    try {
        const { data } = await axios.get('/pos-products');
        products.value = data.products;
    } finally {
        loadingProducts.value = false;
    }
};

onMounted(async () => {
    await loadProducts();
    if (repairJob) {
        repairJob.items.forEach((i) => {
            const live = i.product ? products.value.find((p) => p.id === i.product.id) || i.product : null;
            lines.value.push(
                makeLine(live, {
                    name: i.name,
                    type: i.item_type,
                    price: i.price,
                    cost_price: i.cost_price,
                    quantity: i.quantity,
                    warranty_days: i.warranty_days,
                    serial_tracked: false,
                }),
            );
        });
        if (!repairJob.items.length) {
            lines.value.push(
                makeLine(null, { name: `Repair – ${repairJob.device}`, price: Number(repairJob.estimated_cost) || 0, warranty_days: repairJob.warranty_days }),
            );
        }
        tendered.value = toCollect.value;
    }
    searchInput.value?.focus();
});
</script>
