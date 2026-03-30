<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import DeleteUser from '@/components/DeleteUser.vue';
import AppLayout from '@/layouts/AppLayout.vue';

defineProps<{
    mustVerifyEmail?: boolean;
    status?: string;
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head title="Settings" />

    <AppLayout>
        <div class="bg-white min-h-screen">
            <div class="mx-auto w-full max-w-3xl px-4 py-10">

                <!-- Section 1: Account / Email -->
                <section class="space-y-8">
                    <div class="space-y-3">
                        <p class="text-sm font-semibold uppercase tracking-widest text-[#C4623A]">YOUR ACCOUNT</p>
                        <h1 class="font-heading text-3xl font-bold text-slate-900">Account details</h1>
                        <p class="max-w-2xl text-base leading-relaxed text-slate-500">
                            Your email address is used to send your weekly picks and sign-in links.
                        </p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4">
                        <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Email address</p>
                        <p class="mt-1 text-sm font-medium text-slate-900">{{ user.email }}</p>
                        <p class="mt-1 text-xs text-slate-400">
                            To change your email, contact us — your email is tied to your sign-in link.
                        </p>
                    </div>
                </section>

                <!-- Section 2: Security -->
                <section class="space-y-8 pt-10">
                    <div class="space-y-2">
                        <p class="text-sm font-semibold uppercase tracking-widest text-[#C4623A]">SECURITY</p>
                        <h2 class="font-heading text-xl font-bold text-slate-900">Sign-in method</h2>
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

                <!-- Section 3: Danger Zone -->
                <section class="space-y-8 pt-10">
                    <div class="space-y-2">
                        <p class="text-sm font-semibold uppercase tracking-widest text-[#C4623A]">DANGER ZONE</p>
                    </div>

                    <div class="rounded-2xl border border-red-100 bg-red-50/50 px-5 py-5 space-y-3">
                        <p class="text-sm font-semibold uppercase tracking-widest text-red-400">Delete account</p>
                        <p class="text-sm text-slate-600">
                            Permanently deletes your account, preferences, and all newsletter history.
                            This cannot be undone.
                        </p>
                        <DeleteUser />
                    </div>
                </section>

            </div>
        </div>
    </AppLayout>
</template>
