<template>
    <Head title="Accounting Categories" />
    <AppLayout :breadcrumbs="[{ name: 'Accounting', href: route('accounting.index') }, { name: 'Categories', href: route('accounting.categories') }]">
        <div class="py-6">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold">Categories</h3>
                            <Button @click="openNew" class="bg-indigo-600 hover:bg-indigo-700">New Category</Button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Type</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Description</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                                    <tr v-for="c in categories" :key="c.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">{{ c.name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm capitalize">{{ c.type }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">{{ c.description }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <button @click="edit(c)" class="text-indigo-600 hover:text-indigo-800 mr-3">Edit</button>
                                            <button @click="destroy(c)" class="text-red-600 hover:text-red-800">Delete</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>

    <Modal :show="showModal" @close="close">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4">{{ editing ? 'Edit' : 'New' }} Category</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <Label class="mb-1 block">Name</Label>
                    <Input v-model="form.name" type="text" placeholder="Name" />
                </div>
                <div>
                    <Label class="mb-1 block">Type</Label>
                    <select v-model="form.type" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="income">Income</option>
                        <option value="expense">Expense</option>
                    </select>
                </div>
                <div>
                    <Label class="mb-1 block">Subtype</Label>
                    <select v-model="form.subtype" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="general">General</option>
                        <option value="payroll">Payroll</option>
                        <option value="cogs">COGS</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <Label class="mb-1 block">Description</Label>
                    <Input v-model="form.description" type="text" placeholder="Description (optional)" />
                </div>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <Button @click="close" class="bg-gray-500 hover:bg-gray-600">Cancel</Button>
                <Button @click="save" class="bg-indigo-600 hover:bg-indigo-700">Save</Button>
            </div>
        </div>
    </Modal>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

const props = defineProps({ categories: Array });

const categories = ref(props.categories || []);
const showModal = ref(false);
const editing = ref(false);
const form = ref({ id: null, name: '', type: 'expense', subtype: 'general', description: '' });

const openNew = () => {
    editing.value = false;
    form.value = { id: null, name: '', type: 'expense', subtype: 'general', description: '' };
    showModal.value = true;
};

const edit = (c) => {
    editing.value = true;
    form.value = { id: c.id, name: c.name, type: c.type, subtype: c.subtype || 'general', description: c.description || '' };
    showModal.value = true;
};

const close = () => { showModal.value = false; };

const save = () => {
    const payload = { ...form.value };
    if (editing.value && payload.id) {
        router.put(route('accounting.categories.update', payload.id), payload);
    } else {
        router.post(route('accounting.categories.store'), payload);
    }
    showModal.value = false;
};

const destroy = (c) => {
    if (!confirm('Delete this category?')) return;
    router.delete(route('accounting.categories.destroy', c.id));
};
</script>


