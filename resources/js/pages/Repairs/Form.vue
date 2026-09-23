<template>
    <Head :title="job ? `Edit ${job.job_number}` : 'New Repair'" />
    <AppLayout
        :breadcrumbs="[
            { name: 'Repairs', href: route('repairs.index') },
            job ? { name: job.job_number, href: route('repairs.show', job.id) } : { name: 'New', href: route('repairs.create') },
        ]"
    >
        <form @submit.prevent="submit" class="mx-auto max-w-6xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">{{ job ? `Edit ${job.job_number}` : 'Repair Intake' }}</h1>
                <label class="flex cursor-pointer items-center gap-2 text-sm font-medium">
                    <input type="checkbox" :checked="form.priority === 'urgent'" @change="form.priority = $event.target.checked ? 'urgent' : 'normal'" class="rounded text-red-600" />
                    <span :class="form.priority === 'urgent' ? 'text-red-600' : 'text-gray-600 dark:text-gray-300'">Urgent</span>
                </label>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div class="space-y-4 lg:col-span-2">
                    <!-- Customer -->
                    <Card title="Customer" :icon="UserIcon">
                        <CustomerPicker v-model="customer" />
                        <p v-if="form.errors.customer_id" class="mt-1 text-sm text-red-600">Please select or add the customer.</p>
                    </Card>

                    <!-- Device -->
                    <Card title="Device" :icon="Smartphone">
                        <div class="mb-3 flex flex-wrap gap-2">
                            <button
                                v-for="(label, key) in deviceTypes"
                                :key="key"
                                type="button"
                                @click="form.device_type = key"
                                :class="[
                                    'flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm',
                                    form.device_type === key ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-gray-300 hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-700',
                                ]"
                            >
                                <component :is="deviceIcon(key)" class="h-4 w-4" /> {{ label }}
                            </button>
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <Field label="Brand" :error="form.errors.brand">
                                <input v-model="form.brand" list="brand-list" class="input" placeholder="Apple" />
                                <datalist id="brand-list">
                                    <option v-for="b in brandSuggestions" :key="b" :value="b" />
                                </datalist>
                            </Field>
                            <Field label="Model" :error="form.errors.model">
                                <input v-model="form.model" class="input" placeholder="iPhone 13 Pro" />
                            </Field>
                            <Field label="IMEI / Serial" :error="form.errors.imei" hint="Dial *#06# to show IMEI">
                                <input v-model="form.imei" class="input font-mono" placeholder="35xxxxxxxxxxxxx" />
                            </Field>
                            <Field label="Colour" :error="form.errors.color">
                                <input v-model="form.color" class="input" placeholder="Graphite" />
                            </Field>
                        </div>

                        <!-- Passcode -->
                        <div class="mt-4">
                            <div class="mb-1 text-sm font-medium text-gray-700 dark:text-gray-200">Screen lock</div>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="opt in passcodeTypes"
                                    :key="opt.value"
                                    type="button"
                                    @click="setPasscodeType(opt.value)"
                                    :class="[
                                        'rounded-lg border px-3 py-1 text-sm',
                                        form.passcode_type === opt.value ? 'border-gray-900 bg-gray-900 text-white dark:border-white dark:bg-white dark:text-gray-900' : 'border-gray-300 dark:border-gray-600',
                                    ]"
                                >
                                    {{ opt.label }}
                                </button>
                            </div>
                            <div v-if="form.passcode_type === 'pin' || form.passcode_type === 'password'" class="mt-2 max-w-xs">
                                <input v-model="form.passcode" class="input font-mono" :placeholder="form.passcode_type === 'pin' ? 'e.g. 123456' : 'Password'" />
                            </div>
                            <div v-if="form.passcode_type === 'pattern'" class="mt-2 flex items-center gap-4">
                                <div class="grid grid-cols-3 gap-3 rounded-xl bg-gray-900 p-4">
                                    <button
                                        v-for="n in 9"
                                        :key="n"
                                        type="button"
                                        @click="tapPattern(n)"
                                        :class="[
                                            'relative flex h-10 w-10 items-center justify-center rounded-full text-xs font-bold transition',
                                            patternSeq.includes(n) ? 'bg-emerald-400 text-gray-900' : 'bg-gray-600 text-gray-300 hover:bg-gray-500',
                                        ]"
                                    >
                                        {{ patternSeq.includes(n) ? patternSeq.indexOf(n) + 1 : '' }}
                                    </button>
                                </div>
                                <div class="text-sm">
                                    <div class="font-mono text-gray-800 dark:text-gray-200">{{ patternSeq.join(' → ') || 'Tap dots in order' }}</div>
                                    <button type="button" class="mt-1 text-xs text-red-600 hover:underline" @click="patternSeq = []">Reset</button>
                                </div>
                            </div>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Stored for the technician only; never printed on the job sheet.</p>
                        </div>
                    </Card>

                    <!-- Issue -->
                    <Card title="Problem" :icon="AlertTriangle">
                        <div class="mb-2 flex flex-wrap gap-1.5">
                            <button
                                v-for="q in commonIssues"
                                :key="q"
                                type="button"
                                @click="appendIssue(q)"
                                class="rounded-full bg-gray-100 px-2.5 py-1 text-xs text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200"
                            >
                                + {{ q }}
                            </button>
                        </div>
                        <textarea v-model="form.issue" rows="3" class="input" placeholder="What does the customer report?" required></textarea>
                        <p v-if="form.errors.issue" class="mt-1 text-sm text-red-600">{{ form.errors.issue }}</p>
                        <div v-if="job" class="mt-3">
                            <div class="mb-1 text-sm font-medium text-gray-700 dark:text-gray-200">Diagnosis</div>
                            <textarea v-model="form.diagnosis" rows="2" class="input"></textarea>
                        </div>
                    </Card>

                    <!-- Condition -->
                    <Card title="Condition check at intake" :icon="ClipboardCheck">
                        <div class="grid grid-cols-1 gap-x-6 gap-y-1.5 sm:grid-cols-2">
                            <div v-for="(label, key) in preCheckOptions" :key="key" class="flex items-center justify-between gap-2">
                                <span class="text-sm text-gray-700 dark:text-gray-200">{{ label }}</span>
                                <div class="flex overflow-hidden rounded-lg border border-gray-300 text-xs dark:border-gray-600">
                                    <button
                                        v-for="opt in checkStates"
                                        :key="opt.value"
                                        type="button"
                                        @click="toggleCheck(key, opt.value)"
                                        :class="['px-2 py-1', form.pre_checks[key] === opt.value ? opt.active : 'bg-white text-gray-500 dark:bg-gray-800']"
                                    >
                                        {{ opt.label }}
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="mt-2 flex gap-2 text-xs">
                            <button type="button" class="text-emerald-700 hover:underline dark:text-emerald-400" @click="setAllChecks('ok')">Mark all OK</button>
                            <button type="button" class="text-gray-500 hover:underline" @click="setAllChecks('na')">Device dead – mark all N/A</button>
                        </div>
                        <textarea
                            v-model="form.condition_notes"
                            rows="2"
                            class="input mt-3"
                            placeholder="Scratches, dents, cracked back glass, bent frame, water indicator…"
                        ></textarea>

                        <div class="mt-3 text-sm font-medium text-gray-700 dark:text-gray-200">Accessories received</div>
                        <div class="mt-1 flex flex-wrap gap-2">
                            <label
                                v-for="a in accessoryOptions"
                                :key="a"
                                :class="[
                                    'cursor-pointer rounded-lg border px-2.5 py-1 text-sm select-none',
                                    form.accessories.includes(a) ? 'border-indigo-600 bg-indigo-50 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-200' : 'border-gray-300 dark:border-gray-600',
                                ]"
                            >
                                <input v-model="form.accessories" type="checkbox" :value="a" class="hidden" />
                                {{ a }}
                            </label>
                        </div>
                    </Card>
                </div>

                <!-- Right column -->
                <div class="space-y-4">
                    <Card title="Quote & deposit" :icon="Banknote">
                        <Field label="Estimated cost (RM)" :error="form.errors.estimated_cost">
                            <input v-model.number="form.estimated_cost" type="number" min="0" step="0.01" class="input text-right" />
                        </Field>
                        <Field label="Deposit taken (RM)" :error="form.errors.deposit" class="mt-3">
                            <input v-model.number="form.deposit" type="number" min="0" step="0.01" class="input text-right" :disabled="!!job?.order_id" />
                        </Field>
                        <div v-if="form.deposit > 0" class="mt-2 grid grid-cols-2 gap-1.5">
                            <button
                                v-for="m in depositMethods"
                                :key="m.value"
                                type="button"
                                @click="form.deposit_method = m.value"
                                :class="[
                                    'rounded border px-2 py-1 text-xs',
                                    form.deposit_method === m.value ? 'border-blue-600 bg-blue-600 text-white' : 'border-gray-300 dark:border-gray-600',
                                ]"
                            >
                                {{ m.label }}
                            </button>
                        </div>
                        <Field label="Warranty (days)" :error="form.errors.warranty_days" class="mt-3">
                            <input v-model.number="form.warranty_days" type="number" min="0" class="input text-right" />
                        </Field>
                    </Card>

                    <Card title="Scheduling" :icon="CalendarClock">
                        <Field label="Promised ready by" :error="form.errors.promised_at">
                            <input v-model="form.promised_at" type="datetime-local" class="input" />
                        </Field>
                        <div class="mt-1.5 flex flex-wrap gap-1.5">
                            <button v-for="p in promisePresets" :key="p.label" type="button" @click="setPromise(p)" class="rounded bg-gray-100 px-2 py-0.5 text-xs hover:bg-gray-200 dark:bg-gray-700">
                                {{ p.label }}
                            </button>
                        </div>
                        <Field label="Technician" class="mt-3">
                            <select v-model="form.technician_id" class="input">
                                <option :value="null">Unassigned</option>
                                <option v-for="t in technicians" :key="t.id" :value="t.id">{{ t.name }}</option>
                            </select>
                        </Field>
                        <Field label="Internal notes" class="mt-3" hint="Not shown to the customer">
                            <textarea v-model="form.internal_notes" rows="3" class="input"></textarea>
                        </Field>
                    </Card>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 py-3 text-lg font-semibold text-white shadow hover:bg-indigo-700 disabled:opacity-50"
                    >
                        <Save class="h-5 w-5" />
                        {{ form.processing ? 'Saving…' : job ? 'Save changes' : 'Create repair job' }}
                    </button>
                    <Link v-if="job" :href="route('repairs.show', job.id)" class="block text-center text-sm text-gray-600 hover:underline dark:text-gray-300">Cancel</Link>
                </div>
            </div>
        </form>
    </AppLayout>
</template>

<script setup>
import CustomerPicker from '@/Components/service/CustomerPicker.vue';
import { deviceIcon } from '@/Components/service/repairStyles';
import { useFlashToast } from '@/composables/useFlashToast';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { AlertTriangle, Banknote, CalendarClock, ClipboardCheck, Save, Smartphone, User as UserIcon } from 'lucide-vue-next';
import { defineComponent, h, ref, watch } from 'vue';

const props = defineProps({
    job: { type: Object, default: null },
    prefillCustomer: { type: Object, default: null },
    deviceTypes: Object,
    accessoryOptions: Array,
    preCheckOptions: Object,
    technicians: Array,
    defaultWarrantyDays: Number,
    brandSuggestions: Array,
});

useFlashToast();

// Small presentational helpers kept local to the page.
const Card = defineComponent({
    props: { title: String, icon: [Object, Function] },
    setup(p, { slots }) {
        return () =>
            h('div', { class: 'rounded-xl bg-white p-4 shadow-sm dark:bg-gray-800' }, [
                h('div', { class: 'mb-3 flex items-center gap-2 font-semibold text-gray-900 dark:text-gray-100' }, [p.icon ? h(p.icon, { class: 'h-4 w-4 text-gray-400' }) : null, p.title]),
                slots.default?.(),
            ]);
    },
});
const Field = defineComponent({
    props: { label: String, error: String, hint: String },
    setup(p, { slots }) {
        return () =>
            h('label', { class: 'block' }, [
                h('span', { class: 'mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200' }, p.label),
                slots.default?.(),
                p.hint ? h('span', { class: 'mt-0.5 block text-xs text-gray-500 dark:text-gray-400' }, p.hint) : null,
                p.error ? h('span', { class: 'mt-0.5 block text-sm text-red-600' }, p.error) : null,
            ]);
    },
});

const j = props.job;
const customer = ref(j?.customer ?? props.prefillCustomer ?? null);

const form = useForm({
    customer_id: customer.value?.id ?? null,
    technician_id: j?.technician_id ?? null,
    device_type: j?.device_type ?? 'phone',
    brand: j?.brand ?? '',
    model: j?.model ?? '',
    imei: j?.imei ?? '',
    color: j?.color ?? '',
    passcode_type: j?.passcode_type ?? 'none',
    passcode: j?.passcode ?? '',
    accessories: j?.accessories ?? [],
    pre_checks: { ...(j?.pre_checks ?? {}) },
    condition_notes: j?.condition_notes ?? '',
    issue: j?.issue ?? '',
    diagnosis: j?.diagnosis ?? '',
    priority: j?.priority ?? 'normal',
    estimated_cost: j?.estimated_cost ?? 0,
    deposit: j?.deposit ?? 0,
    deposit_method: j?.deposit_method ?? 'cash',
    warranty_days: j?.warranty_days ?? props.defaultWarrantyDays,
    promised_at: j?.promised_at ?? '',
    internal_notes: j?.internal_notes ?? '',
});

watch(customer, (c) => (form.customer_id = c?.id ?? null));

const passcodeTypes = [
    { value: 'none', label: 'No lock' },
    { value: 'pin', label: 'PIN' },
    { value: 'password', label: 'Password' },
    { value: 'pattern', label: 'Pattern' },
];
const patternSeq = ref(j?.passcode_type === 'pattern' && j?.passcode ? j.passcode.split('-').map(Number) : []);
const setPasscodeType = (t) => {
    form.passcode_type = t;
    form.passcode = '';
    patternSeq.value = [];
};
const tapPattern = (n) => {
    if (!patternSeq.value.includes(n)) patternSeq.value.push(n);
};
watch(patternSeq, (seq) => {
    if (form.passcode_type === 'pattern') form.passcode = seq.join('-');
}, { deep: true });

const checkStates = [
    { value: 'ok', label: 'OK', active: 'bg-emerald-600 text-white' },
    { value: 'faulty', label: 'Faulty', active: 'bg-red-600 text-white' },
    { value: 'na', label: 'N/A', active: 'bg-gray-500 text-white' },
];
const toggleCheck = (key, value) => {
    if (form.pre_checks[key] === value) delete form.pre_checks[key];
    else form.pre_checks[key] = value;
};
const setAllChecks = (value) => {
    Object.keys(props.preCheckOptions).forEach((k) => (form.pre_checks[k] = value));
};

const commonIssues = ['Cracked screen', 'Battery drains fast', 'Not charging', 'No power', 'Water damage', 'No sound', 'Camera not working', 'Face ID not working', 'Software / hang', 'Back glass broken'];
const appendIssue = (text) => {
    form.issue = form.issue ? `${form.issue.trim()}, ${text.toLowerCase()}` : text;
};

const depositMethods = [
    { value: 'cash', label: 'Cash' },
    { value: 'card', label: 'Card' },
    { value: 'e-wallet', label: 'E-Wallet' },
    { value: 'online_transfer', label: 'Transfer' },
];

const pad = (n) => String(n).padStart(2, '0');
const toLocalInput = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
const promisePresets = [
    { label: '+1 hour', hours: 1 },
    { label: '+3 hours', hours: 3 },
    { label: 'Tomorrow 6pm', days: 1, at: 18 },
    { label: '+3 days', days: 3, at: 18 },
    { label: '+1 week', days: 7, at: 18 },
];
const setPromise = (p) => {
    const d = new Date();
    if (p.hours) d.setHours(d.getHours() + p.hours, 0, 0, 0);
    if (p.days) {
        d.setDate(d.getDate() + p.days);
        d.setHours(p.at, 0, 0, 0);
    }
    form.promised_at = toLocalInput(d);
};

const submit = () => {
    const opts = { preserveScroll: true };
    if (props.job) form.put(route('repairs.update', props.job.id), opts);
    else form.post(route('repairs.store'), opts);
};
</script>

<style scoped>
@reference "../../../css/app.css";
.input {
    @apply w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-transparent focus:ring-2 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100;
}
</style>
