<template>
    <Head title="Track your repair" />
    <div class="min-h-screen bg-gradient-to-b from-indigo-50 to-white px-4 py-10 dark:from-gray-900 dark:to-gray-950">
        <div class="mx-auto max-w-md">
            <div class="mb-6 text-center">
                <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-600 text-white shadow-lg">
                    <Wrench class="h-7 w-7" />
                </div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ shop.name }}</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">Repair status tracking</p>
            </div>

            <div v-if="!result" class="rounded-2xl bg-white p-6 shadow-lg dark:bg-gray-800">
                <form @submit.prevent="submit" class="space-y-4">
                    <label class="block">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Job number</span>
                        <input
                            v-model="form.job_number"
                            required
                            placeholder="RJ2609-0001"
                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 font-mono uppercase dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                    </label>
                    <label class="block">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Phone number</span>
                        <input
                            v-model="form.phone"
                            required
                            type="tel"
                            placeholder="Phone number used at drop-off"
                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                    </label>
                    <p v-if="form.errors.job_number || form.errors.phone" class="text-sm text-red-600">{{ form.errors.job_number || form.errors.phone }}</p>
                    <button type="submit" :disabled="form.processing" class="w-full rounded-lg bg-indigo-600 py-3 font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                        {{ form.processing ? 'Checking…' : 'Check status' }}
                    </button>
                </form>
            </div>

            <div v-else class="space-y-4">
                <div class="rounded-2xl bg-white p-6 shadow-lg dark:bg-gray-800">
                    <div class="text-center">
                        <div class="font-mono text-sm text-gray-500">{{ result.job_number }}</div>
                        <div class="text-lg font-semibold text-gray-900 dark:text-white">{{ result.device }}</div>
                        <div :class="['mt-3 inline-block rounded-full px-4 py-1.5 text-sm font-bold', statusStyle(result.status).badge]">{{ result.status_label }}</div>
                    </div>

                    <div v-if="result.status !== 'cancelled'" class="mt-6 flex items-center">
                        <template v-for="(s, i) in steps" :key="s">
                            <div :class="['h-3 w-3 shrink-0 rounded-full', i <= stepIndex ? 'bg-emerald-500' : 'bg-gray-300 dark:bg-gray-600']"></div>
                            <div v-if="i < steps.length - 1" :class="['h-1 flex-1', i < stepIndex ? 'bg-emerald-500' : 'bg-gray-200 dark:bg-gray-700']"></div>
                        </template>
                    </div>
                    <div v-if="result.status !== 'cancelled'" class="mt-1 flex justify-between text-[10px] text-gray-500">
                        <span>Received</span><span>Checking</span><span>Repairing</span><span>Ready</span><span>Collected</span>
                    </div>

                    <dl class="mt-6 space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-gray-500">Issue</dt><dd class="max-w-[60%] text-right text-gray-900 dark:text-gray-100">{{ result.issue }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Received</dt><dd class="text-gray-900 dark:text-gray-100">{{ result.received_at }}</dd></div>
                        <div v-if="result.promised_at" class="flex justify-between"><dt class="text-gray-500">Estimated ready</dt><dd class="text-gray-900 dark:text-gray-100">{{ result.promised_at }}</dd></div>
                        <div v-if="result.quoted > 0" class="flex justify-between"><dt class="text-gray-500">Quoted</dt><dd class="font-semibold text-gray-900 dark:text-gray-100">RM {{ money(result.quoted) }}</dd></div>
                        <div v-if="result.deposit > 0" class="flex justify-between"><dt class="text-gray-500">Deposit paid</dt><dd class="text-gray-900 dark:text-gray-100">RM {{ money(result.deposit) }}</dd></div>
                        <div v-if="result.warranty_expires_at" class="flex justify-between"><dt class="text-gray-500">Warranty until</dt><dd class="text-emerald-700">{{ result.warranty_expires_at }}</dd></div>
                    </dl>
                </div>

                <div v-if="result.timeline.length" class="rounded-2xl bg-white p-6 shadow-lg dark:bg-gray-800">
                    <div class="mb-3 font-semibold text-gray-900 dark:text-white">Updates</div>
                    <ol class="space-y-3 border-l border-gray-200 pl-4 dark:border-gray-700">
                        <li v-for="(t, i) in result.timeline" :key="i">
                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ t.status || 'Note' }}</div>
                            <div v-if="t.note" class="text-sm text-gray-600 dark:text-gray-300">{{ t.note }}</div>
                            <div class="text-xs text-gray-400">{{ t.at }}</div>
                        </li>
                    </ol>
                </div>

                <Link :href="route('track.index')" class="block text-center text-sm text-indigo-600 hover:underline">Check another repair</Link>
            </div>

            <p class="mt-8 text-center text-xs text-gray-400">
                {{ shop.address }}<br v-if="shop.address && shop.phone" />
                <span v-if="shop.phone">Tel: {{ shop.phone }}</span>
            </p>
        </div>
    </div>
</template>

<script setup>
import { statusStyle } from '@/Components/service/repairStyles';
import { money } from '@/composables/useFlashToast';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Wrench } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps({
    result: { type: Object, default: null },
    shop: Object,
});

const params = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : new URLSearchParams();
const form = useForm({ job_number: params.get('job') || '', phone: '' });
const submit = () => form.post(route('track.lookup'), { preserveScroll: true });

const steps = ['received', 'diagnosing', 'in_progress', 'ready', 'collected'];
const stepIndex = computed(() => {
    const map = { received: 0, diagnosing: 1, awaiting_approval: 1, awaiting_parts: 2, in_progress: 2, ready: 3, collected: 4 };
    return map[props.result?.status] ?? 0;
});
</script>
