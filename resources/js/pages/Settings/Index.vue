<template>
    <Head title="Shop Settings" />

    <AppLayout :breadcrumbs="[
        { name: 'Settings', href: route('pos.settings') }
    ]">
        <div class="container mx-auto py-6">
            <div class="max-w-7xl mx-auto">
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <form @submit.prevent="submit">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-6">
                                    <!-- Shop Name -->
                                    <div>
                                        <Label for="shop_name">Shop Name</Label>
                                        <Input
                                            id="shop_name"
                                            v-model="form.shop_name"
                                            type="text"
                                            class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                            required
                                        />
                                        <InputError :message="form.errors.shop_name" class="mt-2" />
                                    </div>

                                    <!-- Logo -->
                                    <div>
                                        <Label for="logo">Shop Logo</Label>
                                        <div class="mt-2 flex items-center gap-4">
                                            <div v-if="settings.logo_path" class="relative">
                                                <img 
                                                    :src="'/storage/' + settings.logo_path" 
                                                    alt="Shop Logo" 
                                                    class="h-20 w-auto rounded-lg object-contain border border-gray-200 dark:border-gray-700"
                                                >
                                            </div>
                                            <div class="flex-1">
                                                <Input
                                                    id="logo"
                                                    type="file"
                                                    @input="handleLogoUpload"
                                                    accept="image/jpeg,image/png,image/jpg,image/gif"
                                                    class="block w-full text-sm text-gray-500 dark:text-gray-400
                                                        file:mr-4 file:py-2 file:px-4
                                                        file:rounded-md file:border-0
                                                        file:text-sm file:font-semibold
                                                        file:bg-indigo-50 file:text-indigo-700
                                                        dark:file:bg-indigo-900 dark:file:text-indigo-300
                                                        hover:file:bg-indigo-100 dark:hover:file:bg-indigo-800"
                                                />
                                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                                    Recommended size: 200x200px. Max file size: 2MB
                                                </p>
                                            </div>
                                        </div>
                                        <InputError :message="form.errors.logo" class="mt-2" />
                                    </div>

                                    <!-- Email -->
                                    <div>
                                        <Label for="email">Email</Label>
                                        <Input
                                            id="email"
                                            v-model="form.shop_email"
                                            type="email"
                                            class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                            required
                                        />
                                        <InputError :message="form.errors.shop_email" class="mt-2" />
                                    </div>

                                    <!-- Phone -->
                                    <div>
                                        <Label for="phone">Phone</Label>
                                        <Input
                                            id="phone"
                                            v-model="form.shop_phone"
                                            type="text"
                                            class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                            required
                                        />
                                        <InputError :message="form.errors.shop_phone" class="mt-2" />
                                    </div>

                                    <!-- Address -->
                                    <div>
                                        <Label for="address">Address</Label>
                                        <Textarea
                                            id="address"
                                            v-model="form.shop_address"
                                            class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                            rows="3"
                                            required
                                        />
                                        <InputError :message="form.errors.shop_address" class="mt-2" />
                                    </div>

                                    <!-- Currency -->
                                    <div>
                                        <Label for="currency">Currency</Label>
                                        <select
                                            id="currency"
                                            v-model="form.currency"
                                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500"
                                            required
                                        >
                                            <option v-for="currency in currencies" :key="currency.code" :value="currency.code">
                                                {{ currency.code }} ({{ currency.symbol }}) - {{ currency.name }}
                                            </option>
                                        </select>
                                        <InputError :message="form.errors.currency" class="mt-2" />
                                    </div>

                                    <!-- Tax Percentage -->
                                    <div>
                                        <Label for="tax_percentage">Tax Percentage (%)</Label>
                                        <Input
                                            id="tax_percentage"
                                            v-model="form.tax_percentage"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            max="100"
                                            class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                            required
                                        />
                                        <InputError :message="form.errors.tax_percentage" class="mt-2" />
                                    </div>
                                </div>

                                <div class="space-y-6">
                                    <!-- Company Number -->
                                    <div>
                                        <Label for="company_number">Company Number</Label>
                                        <Input
                                            id="company_number"
                                            v-model="form.company_number"
                                            type="text"
                                            class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                        />
                                        <InputError :message="form.errors.company_number" class="mt-2" />
                                    </div>

                                    <!-- Tax Number -->
                                    <div>
                                        <Label for="tax_number">Tax Number</Label>
                                        <Input
                                            id="tax_number"
                                            v-model="form.tax_number"
                                            type="text"
                                            class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                        />
                                        <InputError :message="form.errors.tax_number" class="mt-2" />
                                    </div>

                                    <!-- Payment Details -->
                                    <div>
                                        <Label for="payment_details">Payment Details</Label>
                                        <Textarea
                                            id="payment_details"
                                            v-model="form.payment_details"
                                            class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                            rows="3"
                                            placeholder="Enter bank account details or payment instructions"
                                        />
                                        <InputError :message="form.errors.payment_details" class="mt-2" />
                                    </div>

                                    <!-- Footer Text -->
                                    <div>
                                        <Label for="footer_text">Footer Text</Label>
                                        <Textarea
                                            id="footer_text"
                                            v-model="form.footer_text"
                                            class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                            rows="3"
                                            placeholder="Enter text to be displayed on receipts footer"
                                        />
                                        <InputError :message="form.errors.footer_text" class="mt-2" />
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-end gap-4 mt-6 border-t border-gray-200 dark:border-gray-700 pt-6">
                                <Button :disabled="form.processing" class="bg-indigo-600 hover:bg-indigo-700">
                                    Save Settings
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import InputError from '@/Components/InputError.vue';
import { currencies, defaultCurrency } from '@/config/currency';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

const props = defineProps<{
    settings: {
        shop_name: string;
        shop_address: string;
        shop_phone: string;
        shop_email: string;
        currency: string;
        tax_percentage: number;
        logo_path?: string;
        company_number?: string;
        tax_number?: string;
        payment_details?: string;
        footer_text?: string;
    };
}>();

const form = useForm({
    shop_name: props.settings.shop_name,
    shop_address: props.settings.shop_address,
    shop_phone: props.settings.shop_phone,
    shop_email: props.settings.shop_email,
    currency: props.settings.currency || defaultCurrency.code,
    tax_percentage: props.settings.tax_percentage,
    logo: null,
    company_number: props.settings.company_number || '',
    tax_number: props.settings.tax_number || '',
    payment_details: props.settings.payment_details || '',
    footer_text: props.settings.footer_text || '',
});

const submit = () => {
    form.post(route('pos.settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.logo = null;
            toast.success('Settings saved successfully!', {
                autoClose: 3000,
                position: toast.POSITION.TOP_RIGHT,
            });
        },
    });
};

const handleLogoUpload = (event) => {
    const file = event.target.files[0];
    if (file) {
        // Validate file size (2MB max)
        if (file.size > 2 * 1024 * 1024) {
            toast.error('Logo file size must be less than 2MB');
            event.target.value = '';
            return;
        }
        
        // Validate file type
        const validTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
        if (!validTypes.includes(file.type)) {
            toast.error('Invalid file type. Please upload a JPEG, PNG, or GIF image.');
            event.target.value = '';
            return;
        }

        form.logo = file;
    }
};
</script> 