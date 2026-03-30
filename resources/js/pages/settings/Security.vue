<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { onUnmounted, ref } from 'vue';
import TwoFactorRecoveryCodes from '@/components/TwoFactorRecoveryCodes.vue';
import TwoFactorSetupModal from '@/components/TwoFactorSetupModal.vue';
import { Button } from '@/components/ui/button';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import AppLayout from '@/layouts/AppLayout.vue';
import { disable, enable } from '@/routes/two-factor';

type Props = {
    canManageTwoFactor?: boolean;
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
};

withDefaults(defineProps<Props>(), {
    canManageTwoFactor: false,
    requiresConfirmation: false,
    twoFactorEnabled: false,
});

const { hasSetupData, clearTwoFactorAuthData } = useTwoFactorAuth();
const showSetupModal = ref<boolean>(false);

onUnmounted(() => clearTwoFactorAuthData());
</script>

<template>
    <Head title="Security settings" />

    <AppLayout>
        <div class="bg-white min-h-screen">
            <div class="mx-auto w-full max-w-3xl px-4 py-10">

                <!-- Section 1: Sign-in security -->
                <section class="space-y-8">
                    <div class="space-y-3">
                        <p class="text-sm font-semibold uppercase tracking-widest text-[#C4623A]">SECURITY</p>
                        <h1 class="font-heading text-3xl font-bold text-slate-900">Sign-in security</h1>
                        <p class="max-w-2xl text-base leading-relaxed text-slate-500">
                            Manage how you sign in to your account.
                        </p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4 space-y-2">
                        <div class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-[#C4623A]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                            </svg>
                            <p class="text-sm font-semibold text-slate-900">Magic link authentication</p>
                        </div>
                        <p class="text-sm text-slate-500">
                            Your account uses secure email sign-in links instead of passwords.
                            Each link expires after one use and cannot be reused.
                        </p>
                    </div>
                </section>

                <!-- Section 2: Two-factor authentication (if available) -->
                <section v-if="canManageTwoFactor" class="space-y-8 pt-10">
                    <div class="space-y-2">
                        <p class="text-sm font-semibold uppercase tracking-widest text-[#C4623A]">TWO-FACTOR AUTHENTICATION</p>
                        <h2 class="font-heading text-xl font-bold text-slate-900">Two-factor authentication</h2>
                        <p class="text-base leading-relaxed text-slate-500">
                            Add an extra layer of security to your account with a TOTP authenticator app.
                        </p>
                    </div>

                    <!-- 2FA disabled -->
                    <div v-if="!twoFactorEnabled" class="space-y-4">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4">
                            <p class="text-sm text-slate-500">
                                When you enable two-factor authentication, you will be prompted for a secure pin
                                during login. This pin can be retrieved from a TOTP-supported application on your phone.
                            </p>
                        </div>

                        <Button
                            v-if="hasSetupData"
                            class="rounded-xl bg-[#FDF7F4]0 text-white hover:bg-[#A84E2C]"
                            @click="showSetupModal = true"
                        >
                            Continue setup
                        </Button>
                        <Form
                            v-else
                            v-bind="enable.form()"
                            @success="showSetupModal = true"
                            #default="{ processing }"
                        >
                            <Button
                                type="submit"
                                class="rounded-xl bg-[#FDF7F4]0 text-white hover:bg-[#A84E2C]"
                                :disabled="processing"
                            >
                                Enable 2FA
                            </Button>
                        </Form>
                    </div>

                    <!-- 2FA enabled -->
                    <div v-else class="space-y-4">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4">
                            <p class="text-sm text-slate-500">
                                Two-factor authentication is active. You will be prompted for a secure, random pin
                                during login, which you can retrieve from the TOTP-supported application on your phone.
                            </p>
                        </div>

                        <Form v-bind="disable.form()" #default="{ processing }">
                            <Button
                                type="submit"
                                class="rounded-xl border border-red-200 bg-red-50 text-red-700 hover:bg-red-100"
                                :disabled="processing"
                            >
                                Disable 2FA
                            </Button>
                        </Form>

                        <TwoFactorRecoveryCodes />
                    </div>

                    <TwoFactorSetupModal
                        v-model:isOpen="showSetupModal"
                        :requiresConfirmation="requiresConfirmation"
                        :twoFactorEnabled="twoFactorEnabled"
                    />
                </section>

            </div>
        </div>
    </AppLayout>
</template>
