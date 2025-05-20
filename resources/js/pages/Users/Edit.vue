<template>
    <Head title="Edit User" />

    <AppLayout :breadcrumbs="[
        { name: 'Users', href: route('users.index') },
        { name: 'Edit', href: route('users.edit', user.id) }
    ]">
        <div class="container mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <div class="p-4 sm:p-6 lg:p-8 text-gray-900 dark:text-gray-100">
                        <form @submit.prevent="submit">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
                                <div class="space-y-4 sm:space-y-6">
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

                                    <div>
                                        <Label for="email" class="text-gray-900 dark:text-gray-100">Email</Label>
                                        <Input
                                            id="email"
                                            v-model="form.email"
                                            type="email"
                                            class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                            required
                                        />
                                        <InputError :message="form.errors.email" class="mt-2" />
                                    </div>

                                    <div>
                                        <Label for="password" class="text-gray-900 dark:text-gray-100">Password</Label>
                                        <Input
                                            id="password"
                                            v-model="form.password"
                                            type="password"
                                            class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                            placeholder="Leave blank to keep current password"
                                        />
                                        <InputError :message="form.errors.password" class="mt-2" />
                                    </div>
                                </div>

                                <div class="space-y-4 sm:space-y-6">
                                    <div>
                                        <Label for="role" class="text-gray-900 dark:text-gray-100">Role</Label>
                                        <Select
                                            id="role"
                                            v-model="form.role_id"
                                            class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                            required
                                        >
                                            <option value="">Select a role</option>
                                            <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
                                        </Select>
                                        <InputError :message="form.errors.role_id" class="mt-2" />
                                    </div>

                                    <div>
                                        <div class="flex items-center space-x-2">
                                            <input
                                                id="status"
                                                v-model="form.status"
                                                type="checkbox"
                                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600"
                                            />
                                            <Label for="status" class="text-gray-900 dark:text-gray-100">Active</Label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-col sm:flex-row items-center justify-end gap-4 mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                                <Link
                                    :href="route('users.index')"
                                    class="w-full sm:w-auto px-4 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600 text-center"
                                >
                                    Cancel
                                </Link>
                                <Button :disabled="form.processing" class="w-full sm:w-auto bg-indigo-600 hover:bg-indigo-700">
                                    Update User
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
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    user: Object,
    roles: Array
});

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    password: '',
    role_id: props.user.role_id,
    status: props.user.status
});

const submit = () => {
    form.put(route('users.update', props.user.id), {
        preserveScroll: true,
        preserveState: true,
    });
};
</script> 