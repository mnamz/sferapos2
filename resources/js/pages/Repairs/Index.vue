<template>
    <Head title="Repairs" />
    <AppLayout :breadcrumbs="[{ name: 'Repairs', href: route('repairs.index') }]">
        <div class="container mx-auto space-y-4 px-4 py-6 sm:px-6 lg:px-8">
            <div class="flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Repair Jobs</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ openCount }} on the bench<span v-if="overdueCount" class="text-red-600"> · {{ overdueCount }} overdue</span>
                    </p>
                </div>
                <Link :href="route('repairs.create')" class="flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700">
                    <Plus class="h-4 w-4" /> New Repair
                </Link>
            </div>

            <!-- Status tabs -->
            <div class="flex gap-2 overflow-x-auto pb-1">
                <button
                    v-for="tab in tabs"
                    :key="tab.value"
                    type="button"
                    @click="apply({ status: tab.value })"
                    :class="[
                        'flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium whitespace-nowrap transition',
                        filters.status === tab.value ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'bg-white text-gray-700 shadow-sm hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200',
                    ]"
                >
                    <span v-if="tab.dot" :class="['h-2 w-2 rounded-full', tab.dot]"></span>
                    {{ tab.label }}
                    <span v-if="tab.count !== undefined" class="opacity-60">{{ tab.count }}</span>
                </button>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row">
                <div class="relative flex-1">
                    <Search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <input
                        v-model="search"
                        @input="debouncedSearch"
                        type="text"
                        placeholder="Search job no, IMEI, customer, phone, model, issue…"
                        class="w-full rounded-lg border border-gray-300 py-2 pr-3 pl-9 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    />
                </div>
                <select
                    :value="filters.technician || ''"
                    @change="apply({ technician: $event.target.value || undefined })"
                    class="rounded-lg border border-gray-300 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                >
                    <option value="">All technicians</option>
                    <option v-for="t in technicians" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
            </div>

            <div class="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800">
                <div v-if="!jobs.data.length" class="p-12 text-center text-gray-500 dark:text-gray-400">
                    <Wrench class="mx-auto mb-2 h-10 w-10 opacity-40" />
                    No repair jobs here.
                </div>
                <ul v-else class="divide-y divide-gray-100 dark:divide-gray-700">
                    <li v-for="job in jobs.data" :key="job.id">
                        <Link :href="route('repairs.show', job.id)" class="flex flex-col gap-2 px-4 py-3 hover:bg-gray-50 sm:flex-row sm:items-center dark:hover:bg-gray-700/50">
                            <div class="flex items-center gap-3 sm:w-44">
                                <div :class="['flex h-10 w-10 shrink-0 items-center justify-center rounded-lg', statusStyle(job.status).bg]">
                                    <component :is="deviceIcon(job.device_type)" :class="['h-5 w-5', statusStyle(job.status).fg]" />
                                </div>
                                <div>
                                    <div class="font-mono text-sm font-semibold text-gray-900 dark:text-gray-100">{{ job.job_number }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ job.age }} ago</div>
                                </div>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="truncate font-medium text-gray-900 dark:text-gray-100">{{ job.device }}</span>
                                    <span v-if="job.priority === 'urgent'" class="rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-bold text-red-700 uppercase dark:bg-red-900/50 dark:text-red-200">Urgent</span>
                                </div>
                                <div class="truncate text-sm text-gray-600 dark:text-gray-300">{{ job.issue }}</div>
                                <div class="truncate text-xs text-gray-500 dark:text-gray-400">
                                    {{ job.customer?.name || 'No customer' }}<span v-if="job.customer?.phone"> · {{ job.customer.phone }}</span>
                                    <span v-if="job.imei" class="font-mono"> · {{ job.imei }}</span>
                                </div>
                            </div>
                            <div class="flex items-center justify-between gap-4 sm:w-72 sm:justify-end">
                                <div class="text-right text-xs text-gray-500 dark:text-gray-400">
                                    <div v-if="job.technician"><User class="inline h-3 w-3" /> {{ job.technician }}</div>
                                    <div v-if="job.promised_at" :class="job.overdue ? 'font-semibold text-red-600' : ''">
                                        <Clock class="inline h-3 w-3" /> {{ job.promised_at }}
                                    </div>
                                </div>
                                <span :class="['rounded-full px-2.5 py-1 text-xs font-semibold whitespace-nowrap', statusStyle(job.status).badge]">
                                    {{ statuses[job.status] }}
                                </span>
                            </div>
                        </Link>
                    </li>
                </ul>
            </div>

            <Pagination :links="jobs.links" />
        </div>
    </AppLayout>
</template>

<script setup>
import Pagination from '@/Components/Pagination.vue';
import { statusStyle, deviceIcon } from '@/Components/service/repairStyles';
import { useFlashToast } from '@/composables/useFlashToast';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import debounce from 'lodash/debounce';
import { Clock, Plus, Search, User, Wrench } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps({
    jobs: Object,
    filters: Object,
    statuses: Object,
    counts: Object,
    openCount: Number,
    overdueCount: Number,
    technicians: Array,
});

useFlashToast();

const search = ref(props.filters.search || '');

const tabs = computed(() => [
    { value: 'open', label: 'Open', count: props.openCount },
    ...(props.overdueCount ? [{ value: 'overdue', label: 'Overdue', count: props.overdueCount, dot: 'bg-red-500' }] : []),
    ...Object.entries(props.statuses).map(([value, label]) => ({
        value,
        label,
        count: props.counts[value] || 0,
        dot: statusStyle(value).dot,
    })),
    { value: 'all', label: 'All' },
]);

const apply = (changes) => {
    router.get(
        route('repairs.index'),
        { ...props.filters, search: search.value || undefined, ...changes },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};
const debouncedSearch = debounce(() => apply({}), 300);
</script>
