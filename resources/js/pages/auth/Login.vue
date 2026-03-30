<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Mail } from 'lucide-vue-next';
import LoginLinkController from '@/actions/App/Http/Controllers/Auth/LoginLinkController';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { register } from '@/routes';

defineProps<{
    status?: string;
    canRegister: boolean;
}>();
</script>

<template>
    <AuthBase
        title="Log in to your account"
        description="Enter your email and we'll send you a secure sign-in link"
    >
        <Head title="Log in" />

        <div
            v-if="status"
            class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-center text-sm font-medium text-emerald-700"
        >
            {{ status }}
        </div>

        <Form
            v-bind="LoginLinkController.store.form()"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-3"
        >
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <div class="relative">
                        <Mail class="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            required
                            autofocus
                            :tabindex="1"
                            autocomplete="email"
                            placeholder="you@example.com"
                            class="h-14 rounded-2xl border-slate-300 pl-12 text-lg focus-visible:ring-[#E8956D]"
                        />
                    </div>
                    <InputError :message="errors.email" />
                </div>

                <Button
                    type="submit"
                    class="h-14 w-full rounded-2xl bg-[#C4623A] text-base font-semibold text-white hover:bg-[#A84E2C]"
                    :tabindex="2"
                    :disabled="processing"
                    data-test="login-button"
                >
                    <Spinner v-if="processing" />
                    Email me a sign-in link
                </Button>
            </div>

            <div
                class="text-center text-sm text-slate-500"
                v-if="canRegister"
            >
                New to NearbyWeekly?
                <TextLink :href="register()" :tabindex="3" class="text-[#C4623A] hover:text-[#A84E2C]">Get started</TextLink>
            </div>
        </Form>
    </AuthBase>
</template>
