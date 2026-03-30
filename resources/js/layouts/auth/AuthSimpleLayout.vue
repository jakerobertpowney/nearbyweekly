<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="flex min-h-svh items-center justify-center bg-white p-6">
        <div class="w-full max-w-md">
            <Card class="border-none py-0 shadow-none">
                <CardHeader class="px-6 py-3">
                    <div class="flex flex-col items-center gap-4 text-center">
                        <Link
                            :href="home()"
                            class="flex flex-col items-center gap-3 font-medium"
                        >
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-surface border border-brand-border">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="#C4623A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/>
                                    <path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/>
                                </svg>
                            </div>
                            <span class="font-heading text-lg font-bold">
                                <span class="text-brand-text">Nearby</span><span class="text-brand-primary ml-1">Weekly</span>
                            </span>
                            <span class="sr-only">{{ title }}</span>
                        </Link>
                        <div class="space-y-2">
                            <CardTitle class="font-heading text-2xl font-bold text-slate-900">{{ title }}</CardTitle>
                            <CardDescription class="text-sm leading-6 text-slate-500">{{ description }}</CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="px-6 pb-6">
                    <slot />
                </CardContent>
            </Card>
        </div>
    </div>
</template>
