<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
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
                        <Link :href="home()" class="flex flex-col items-center gap-3 font-medium">
                            <AppLogo />
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
