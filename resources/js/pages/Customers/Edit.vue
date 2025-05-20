<template>
    <Head title="Edit Customer" />

    <AppLayout :breadcrumbs="[
        { name: 'Customers', href: route('customers.index') },
        { name: 'Edit', href: route('customers.edit', customer.id) }
    ]">
        <div class="container mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <div class="p-4 sm:p-6 lg:p-8 text-gray-900 dark:text-gray-100">
                        <form @submit.prevent="submit">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
                                <div class="space-y-4 sm:space-y-6">
                                    <!-- Name -->
                                    <div>
                                        <Label for="name" class="text-gray-900 dark:text-gray-100">Name</Label>
                                        <Input
                                            id="name"
                                            v-model="form.name"
                                            type="text"
                                            class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                            required
                                        />
                                        <InputError :message="form.errors.name" class="mt-2" />
                                    </div>

                                    <!-- Email -->
                                    <div>
                                        <Label for="email" class="text-gray-900 dark:text-gray-100">Email</Label>
                                        <Input
                                            id="email"
                                            v-model="form.email"
                                            type="email"
                                            class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                        />
                                        <InputError :message="form.errors.email" class="mt-2" />
                                    </div>

                                    <!-- Phone -->
                                    <div>
                                        <Label for="phone" class="text-gray-900 dark:text-gray-100">Phone</Label>
                                        <Input
                                            id="phone"
                                            v-model="form.phone"
                                            type="tel"
                                            class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                        />
                                        <InputError :message="form.errors.phone" class="mt-2" />
                                    </div>
                                </div>

                                <div class="space-y-4 sm:space-y-6">
                                    <!-- Address -->
                                    <div>
                                        <Label for="address" class="text-gray-900 dark:text-gray-100">Address</Label>
                                        <Textarea
                                            id="address"
                                            v-model="form.address"
                                            class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                            rows="3"
                                        />
                                        <InputError :message="form.errors.address" class="mt-2" />
                                    </div>

                                    <!-- Status -->
                                    <div>
                                        <Label for="status" class="text-gray-900 dark:text-gray-100">Status</Label>
                                        <Select
                                            id="status"
                                            v-model="form.status"
                                            class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                            required
                                        >
                                            <option value="active">Active</option>
                                            <option value="inactive">Inactive</option>
                                        </Select>
                                        <InputError :message="form.errors.status" class="mt-2" />
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-col sm:flex-row items-center justify-end gap-4 mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                                <Link
                                    :href="route('customers.index')"
                                    class="w-full sm:w-auto px-4 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600 text-center"
                                >
                                    Cancel
                                </Link>
                                <Button :disabled="form.processing" class="w-full sm:w-auto bg-indigo-600 hover:bg-indigo-700">
                                    Update Customer
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select } from '@/Components/ui/select';
import { Textarea } from '@/Components/ui/textarea';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    customer: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    name: props.customer.name,
    email: props.customer.email,
    phone: props.customer.phone,
    address: props.customer.address,
    status: props.customer.status,
    _method: 'PUT',
});

const submit = () => {
    form.post(route('customers.update', props.customer.id), {
        preserveScroll: true,
        preserveState: true,
    });
};
</script> 