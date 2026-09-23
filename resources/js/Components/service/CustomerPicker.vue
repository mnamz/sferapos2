<template>
    <div class="relative" ref="root">
        <div v-if="modelValue" class="flex items-center justify-between rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 dark:border-blue-800 dark:bg-blue-900/20">
            <div class="min-w-0">
                <div class="truncate font-medium text-blue-800 dark:text-blue-200">{{ modelValue.name }}</div>
                <div class="truncate text-sm text-blue-700 dark:text-blue-300">{{ modelValue.phone || modelValue.email || 'No contact info' }}</div>
            </div>
            <div class="flex items-center gap-2">
                <slot name="actions" :customer="modelValue" />
                <button v-if="!locked" type="button" @click="clear" class="rounded p-1 text-blue-700 hover:bg-blue-100 dark:text-blue-300 dark:hover:bg-blue-800" title="Change customer">
                    <X class="h-4 w-4" />
                </button>
            </div>
        </div>

        <template v-else>
            <div class="flex gap-2">
                <div class="relative flex-1">
                    <Search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <input
                        ref="searchInput"
                        v-model="query"
                        type="text"
                        @focus="open = true"
                        @keydown.enter.prevent="onEnter"
                        :placeholder="placeholder"
                        class="w-full rounded-lg border border-gray-300 bg-white py-2 pr-3 pl-9 text-gray-900 focus:border-transparent focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                    />
                </div>
                <button
                    type="button"
                    @click="startQuickAdd"
                    class="flex items-center gap-1 rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium whitespace-nowrap text-white hover:bg-blue-700"
                >
                    <UserPlus class="h-4 w-4" /> New
                </button>
            </div>

            <div
                v-if="open && !quickAdd && query.length >= 2"
                class="absolute z-50 mt-1 w-full overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800"
            >
                <div v-if="loading" class="p-3 text-sm text-gray-500">Searching…</div>
                <template v-else>
                    <button
                        v-for="c in results"
                        :key="c.id"
                        type="button"
                        @mousedown.prevent="select(c)"
                        class="block w-full px-3 py-2 text-left hover:bg-gray-100 dark:hover:bg-gray-700"
                    >
                        <div class="font-medium text-gray-900 dark:text-gray-100">{{ c.name }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ c.phone || c.email || 'No contact info' }}</div>
                    </button>
                    <button
                        type="button"
                        @mousedown.prevent="startQuickAdd"
                        class="block w-full border-t border-gray-100 px-3 py-2 text-left text-sm text-blue-600 hover:bg-blue-50 dark:border-gray-700 dark:text-blue-400 dark:hover:bg-gray-700"
                    >
                        + Add “{{ query }}” as new customer
                    </button>
                </template>
            </div>

            <div v-if="quickAdd" class="mt-2 grid grid-cols-1 gap-2 rounded-lg border border-gray-200 bg-gray-50 p-3 sm:grid-cols-3 dark:border-gray-700 dark:bg-gray-900/40">
                <input v-model="draft.phone" type="tel" placeholder="Phone e.g. 012-345 6789" class="rounded border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-700" />
                <input v-model="draft.name" type="text" placeholder="Name" class="rounded border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-700" @keydown.enter.prevent="saveQuickAdd" />
                <input v-model="draft.email" type="email" placeholder="Email (optional)" class="rounded border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-700" @keydown.enter.prevent="saveQuickAdd" />
                <div class="flex justify-end gap-2 sm:col-span-3">
                    <button type="button" @click="quickAdd = false" class="rounded px-3 py-1 text-sm text-gray-600 hover:bg-gray-200 dark:text-gray-300 dark:hover:bg-gray-700">Cancel</button>
                    <button type="button" @click="saveQuickAdd" :disabled="saving" class="rounded bg-blue-600 px-3 py-1 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                        {{ saving ? 'Saving…' : 'Save customer' }}
                    </button>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import axios from 'axios';
import debounce from 'lodash/debounce';
import { Search, UserPlus, X } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { toast } from 'vue3-toastify';

const props = defineProps({
    modelValue: { type: Object, default: null },
    placeholder: { type: String, default: 'Search customer by name or phone…' },
    locked: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue']);

const root = ref(null);
const query = ref('');
const open = ref(false);
const loading = ref(false);
const results = ref([]);
const quickAdd = ref(false);
const saving = ref(false);
const draft = reactive({ name: '', phone: '', email: '' });

const search = debounce(async (q) => {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/customers/search', { params: { q } });
        results.value = data;
    } catch {
        results.value = [];
    } finally {
        loading.value = false;
    }
}, 250);

watch(query, (q) => {
    if (q.length >= 2) search(q);
    else results.value = [];
});

const select = (c) => {
    emit('update:modelValue', c);
    open.value = false;
    query.value = '';
};
const clear = () => emit('update:modelValue', null);
const onEnter = () => {
    if (results.value.length) select(results.value[0]);
};

const startQuickAdd = () => {
    const q = query.value.trim();
    const looksLikePhone = /^[+\d][\d\s-]{5,}$/.test(q);
    draft.phone = looksLikePhone ? q : '';
    draft.name = looksLikePhone ? '' : q;
    draft.email = '';
    quickAdd.value = true;
    open.value = false;
};

const saveQuickAdd = async () => {
    if (!draft.name.trim() || !draft.phone.trim()) {
        toast.error('Name and phone are required');
        return;
    }
    saving.value = true;
    try {
        const { data } = await axios.post(route('customers.quick'), { ...draft });
        quickAdd.value = false;
        select(data);
    } catch (e) {
        const errs = e.response?.data?.errors;
        toast.error(errs ? Object.values(errs).flat().join('\n') : 'Could not save customer');
    } finally {
        saving.value = false;
    }
};

const onDocClick = (e) => {
    if (root.value && !root.value.contains(e.target)) open.value = false;
};
onMounted(() => document.addEventListener('click', onDocClick));
onBeforeUnmount(() => document.removeEventListener('click', onDocClick));
</script>
