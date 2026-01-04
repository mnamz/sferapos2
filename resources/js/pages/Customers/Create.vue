<template>
    <Head title="Create Customer" />

    <AppLayout :breadcrumbs="[
        { name: 'Customers', href: route('customers.index') },
        { name: 'Create', href: route('customers.create') }
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

                                    <!-- City -->
                                    <div>
                                        <Label for="city" class="text-gray-900 dark:text-gray-100">City</Label>
                                        <Input
                                            id="city"
                                            v-model="form.city"
                                            type="text"
                                            class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                            placeholder="e.g., Kuala Lumpur"
                                        />
                                        <InputError :message="form.errors.city" class="mt-2" />
                                    </div>

                                    <!-- Postal Code -->
                                    <div>
                                        <Label for="postal_code" class="text-gray-900 dark:text-gray-100">Postal Code</Label>
                                        <Input
                                            id="postal_code"
                                            v-model="form.postal_code"
                                            type="text"
                                            class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                            placeholder="e.g., 50480"
                                        />
                                        <InputError :message="form.errors.postal_code" class="mt-2" />
                                    </div>

                                    <!-- State -->
                                    <div>
                                        <Label for="state_code" class="text-gray-900 dark:text-gray-100">State</Label>
                                        <Select
                                            id="state_code"
                                            v-model="form.state_code"
                                            class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                        >
                                            <option value="">Select State</option>
                                            <option value="01">01 - Johor</option>
                                            <option value="02">02 - Kedah</option>
                                            <option value="03">03 - Kelantan</option>
                                            <option value="04">04 - Melaka</option>
                                            <option value="05">05 - Negeri Sembilan</option>
                                            <option value="06">06 - Pahang</option>
                                            <option value="07">07 - Pulau Pinang</option>
                                            <option value="08">08 - Perak</option>
                                            <option value="09">09 - Perlis</option>
                                            <option value="10">10 - Selangor</option>
                                            <option value="11">11 - Terengganu</option>
                                            <option value="12">12 - Sabah</option>
                                            <option value="13">13 - Sarawak</option>
                                            <option value="14">14 - WP Kuala Lumpur</option>
                                            <option value="15">15 - WP Labuan</option>
                                            <option value="16">16 - WP Putrajaya</option>
                                        </Select>
                                        <InputError :message="form.errors.state_code" class="mt-2" />
                                    </div>

                                    <!-- TIN -->
                                    <div v-if="!isStaff">
                                        <Label for="tin" class="text-gray-900 dark:text-gray-100">TIN (Tax Identification Number)</Label>
                                        <Input
                                            id="tin"
                                            v-model="form.tin"
                                            type="text"
                                            class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                            placeholder="e.g., C12345678900"
                                        />
                                        <InputError :message="form.errors.tin" class="mt-2" />
                                    </div>

                                    <!-- BRN -->
                                    <div v-if="!isStaff">
                                        <Label for="brn" class="text-gray-900 dark:text-gray-100">BRN (Business Registration Number)</Label>
                                        <Input
                                            id="brn"
                                            v-model="form.brn"
                                            type="text"
                                            class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                            placeholder="e.g., 202001234567"
                                        />
                                        <InputError :message="form.errors.brn" class="mt-2" />
                                    </div>

                                    <!-- NRIC -->
                                    <div v-if="!isStaff">
                                        <Label for="nric" class="text-gray-900 dark:text-gray-100">NRIC (National Registration ID)</Label>
                                        <Input
                                            id="nric"
                                            v-model="form.nric"
                                            type="text"
                                            class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                            placeholder="e.g., 900101015678"
                                        />
                                        <InputError :message="form.errors.nric" class="mt-2" />
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
                                    Save Customer
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
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select } from '@/Components/ui/select';
import { Textarea } from '@/Components/ui/textarea';
import InputError from '@/Components/InputError.vue';

const page = usePage();
const roles = page.props.auth?.roles || [];
const isStaff = roles.includes('staff');

const form = useForm({
    name: '',
    email: '',
    phone: '',
    address: '',
    city: '',
    postal_code: '',
    state_code: '',
    country: 'MYS',
    tin: '',
    brn: '',
    nric: '',
    status: 'active',
});

const submit = () => {
    form.post(route('customers.store'), {
        preserveScroll: true,
        preserveState: true,
    });
};
</script> 