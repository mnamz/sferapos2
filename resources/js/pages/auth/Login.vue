<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import TextLink from '@/Components/TextLink.vue';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import AuthBase from '@/Layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

defineProps<{
    status?: string;
    canResetPassword: boolean;
    demoMode: boolean;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <AuthBase title="Sign in" description="Enter your email and password to continue.">
        <Head title="Log in" />

        <div v-if="status" class="mb-4 rounded-lg bg-green-50 px-3 py-2 text-sm font-medium text-green-700">
            {{ status }}
        </div>

        <div v-if="demoMode" class="mb-4 rounded border border-yellow-300 bg-yellow-50 p-4 text-sm text-yellow-800 shadow-sm">
            <div class="font-semibold mb-1">Demo Credentials</div>
            <div><span class="font-medium">Email:</span> test@example.com</div>
            <div><span class="font-medium">Password:</span> password</div>
        </div>

        <form @submit.prevent="submit" class="flex flex-col gap-4">
            <div class="grid gap-4">
                <div class="grid gap-2">
                    <Label for="email">Email</Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="email"
                        v-model="form.email"
                        placeholder="email@example.com"
                    />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Password</Label>
                    <Input
                        id="password"
                        type="password"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        v-model="form.password"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="flex items-center justify-between" :tabindex="3">
                    <Label for="remember" class="flex items-center space-x-3">
                        <Checkbox id="remember" v-model="form.remember" :tabindex="4" />
                        <span>Remember me</span>
                    </Label>
                </div>

                <Button
                    type="submit"
                    class="h-10 w-full rounded-lg bg-indigo-600 font-semibold text-white hover:bg-indigo-500 focus-visible:ring-indigo-500/50"
                    :tabindex="4"
                    :disabled="form.processing"
                >
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    {{ form.processing ? 'Signing in…' : 'Sign in' }}
                </Button>
            </div>

        </form>
    </AuthBase>
</template>
