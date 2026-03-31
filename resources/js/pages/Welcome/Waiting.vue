<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CheckCircle2, Inbox, LayoutDashboard, Loader2 } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    newsletterRunId: number | null;
    email: string | null;
}>();

type RunStatus = 'pending' | 'sent' | 'no_matches' | 'unknown' | 'timeout';

const status = ref<RunStatus>('pending');
const stepVisible = ref(1);
let pollInterval: ReturnType<typeof setInterval> | null = null;
let stepInterval: ReturnType<typeof setInterval> | null = null;
let timeoutHandle: ReturnType<typeof setTimeout> | null = null;

const loadingSteps = [
    { label: 'Scanning events near your postcode', delay: 0 },
    { label: 'Matching against your interests', delay: 1400 },
    { label: 'Curating your personalised picks', delay: 2600 },
];

const isDone = computed(() => status.value === 'sent' || status.value === 'no_matches' || status.value === 'timeout');

async function poll(): Promise<void> {
    if (!props.newsletterRunId) {
        status.value = 'unknown';
        stopPolling();
        return;
    }

    try {
        const response = await fetch(`/welcome/status?run_id=${props.newsletterRunId}`);
        const data = await response.json();

        if (data.status === 'sent' || data.status === 'no_matches') {
            status.value = data.status;
            stopPolling();
        }
    } catch {
        // silently continue polling on network error
    }
}

function stopPolling(): void {
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
    if (stepInterval) {
        clearInterval(stepInterval);
        stepInterval = null;
    }
    if (timeoutHandle) {
        clearTimeout(timeoutHandle);
        timeoutHandle = null;
    }
}

onMounted(() => {
    if (!props.newsletterRunId) {
        status.value = 'unknown';
        return;
    }

    // Animate loading steps
    stepInterval = setInterval(() => {
        if (stepVisible.value < loadingSteps.length) {
            stepVisible.value++;
        } else {
            clearInterval(stepInterval!);
        }
    }, 1400);

    // Start polling after a short delay so the animation plays
    setTimeout(() => {
        poll();
        pollInterval = setInterval(poll, 2500);
    }, 1000);

    // Timeout after 45 seconds
    timeoutHandle = setTimeout(() => {
        if (!isDone.value) {
            status.value = 'timeout';
            stopPolling();
        }
    }, 45_000);
});

onUnmounted(() => {
    stopPolling();
});
</script>

<template>
    <Head title="Getting your first picks ready" />

    <div class="flex min-h-screen flex-col items-center justify-center bg-white px-6 py-16">
        <div class="w-full max-w-md space-y-10 text-center">

            <!-- Logo -->
            <div class="flex justify-center">
                <img src="/images/logo.svg" alt="Nearby Weekly" class="h-9 w-auto" />
            </div>

            <!-- Loading state -->
            <div v-if="!isDone" class="space-y-8">
                <!-- Spinner -->
                <div class="flex justify-center">
                    <div class="relative flex h-24 w-24 items-center justify-center">
                        <div class="absolute inset-0 animate-spin rounded-full border-4 border-[#F5EAE3] border-t-[#C4623A]" />
                        <span class="text-3xl">📬</span>
                    </div>
                </div>

                <div class="space-y-2">
                    <h1 class="font-heading text-2xl font-bold text-slate-900 sm:text-3xl">
                        Getting your first picks ready
                    </h1>
                    <p class="text-base text-slate-500">
                        We're searching for events that match your tastes — this takes just a moment.
                    </p>
                </div>

                <!-- Animated step list -->
                <div class="space-y-3 text-left">
                    <div
                        v-for="(step, index) in loadingSteps"
                        :key="index"
                        class="flex items-center gap-3 transition-all duration-500"
                        :class="index < stepVisible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-2'"
                    >
                        <div
                            class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full"
                            :class="index < stepVisible - 1 ? 'bg-[#FDF7F4]0' : 'bg-[#F5EAE3]'"
                        >
                            <CheckCircle2
                                v-if="index < stepVisible - 1"
                                class="h-3.5 w-3.5 text-white"
                            />
                            <Loader2
                                v-else
                                class="h-3.5 w-3.5 animate-spin text-[#C4623A]"
                            />
                        </div>
                        <span
                            class="text-sm font-medium"
                            :class="index < stepVisible - 1 ? 'text-slate-500 line-through' : 'text-slate-800'"
                        >{{ step.label }}</span>
                    </div>
                </div>
            </div>

            <!-- Success state -->
            <div v-else-if="status === 'sent'" class="space-y-8">
                <div class="flex justify-center">
                    <div class="flex h-24 w-24 items-center justify-center rounded-full bg-emerald-100">
                        <CheckCircle2 class="h-12 w-12 text-emerald-500" />
                    </div>
                </div>

                <div class="space-y-2">
                    <h1 class="font-heading text-2xl font-bold text-slate-900 sm:text-3xl">
                        Your first picks are on their way!
                    </h1>
                    <p class="text-base text-slate-500">
                        We've sent your personalised weekly event digest to
                        <span v-if="email" class="font-medium text-slate-800">{{ email }}</span>
                        <span v-else>your inbox</span>.
                        Check your email — your picks are waiting.
                    </p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 text-left">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-slate-400">What's inside</p>
                    <ul class="space-y-2 text-sm text-slate-600">
                        <li class="flex items-center gap-2"><span>🎯</span> Events matched to your interests</li>
                        <li class="flex items-center gap-2"><span>📍</span> Within your chosen travel distance</li>
                        <li class="flex items-center gap-2"><span>🔗</span> A magic link to manage your preferences</li>
                    </ul>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:justify-center">
                    <Button
                        as-child
                        class="rounded-xl bg-[#C4623A] text-white hover:bg-[#A84E2C]"
                    >
                        <a :href="`mailto:${email ?? ''}`">
                            <Inbox class="h-4 w-4" />
                            Open my inbox
                        </a>
                    </Button>
                    <Button
                        as-child
                        variant="outline"
                        class="rounded-xl border-slate-300 text-slate-700 hover:bg-slate-50"
                    >
                        <Link href="/dashboard">
                            <LayoutDashboard class="h-4 w-4" />
                            Go to dashboard
                        </Link>
                    </Button>
                </div>
            </div>

            <!-- No matches state -->
            <div v-else-if="status === 'no_matches'" class="space-y-8">
                <div class="flex justify-center">
                    <div class="flex h-24 w-24 items-center justify-center rounded-full bg-[#F5EAE3]">
                        <span class="text-4xl">🗺️</span>
                    </div>
                </div>

                <div class="space-y-2">
                    <h1 class="font-heading text-2xl font-bold text-slate-900 sm:text-3xl">
                        You're all set — more events coming soon
                    </h1>
                    <p class="text-base text-slate-500">
                        We didn't find enough matching events near you yet. As our event database grows, you'll start receiving your weekly picks automatically.
                    </p>
                </div>

                <div class="rounded-2xl border border-[#E8D5C8] bg-[#FDF7F4] p-4 text-sm text-[#6B4535]">
                    We've saved your preferences and we'll send your first weekly picks as soon as we have strong matches near <span class="font-medium">{{ email ?? 'you' }}</span>.
                </div>

                <Button
                    as-child
                    class="rounded-xl bg-[#C4623A] text-white hover:bg-[#A84E2C]"
                >
                    <Link href="/dashboard">
                        <LayoutDashboard class="h-4 w-4" />
                        Go to dashboard
                    </Link>
                </Button>
            </div>

            <!-- Timeout / unknown state -->
            <div v-else class="space-y-8">
                <div class="flex justify-center">
                    <div class="flex h-24 w-24 items-center justify-center rounded-full bg-slate-100">
                        <span class="text-4xl">⏳</span>
                    </div>
                </div>

                <div class="space-y-2">
                    <h1 class="font-heading text-2xl font-bold text-slate-900 sm:text-3xl">
                        This is taking a little longer than usual
                    </h1>
                    <p class="text-base text-slate-500">
                        Your picks are still being prepared. Check your inbox in a few minutes — we'll send them there as soon as they're ready.
                    </p>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:justify-center">
                    <Button
                        as-child
                        class="rounded-xl bg-[#C4623A] text-white hover:bg-[#A84E2C]"
                    >
                        <Link href="/dashboard">
                            <LayoutDashboard class="h-4 w-4" />
                            Go to dashboard
                        </Link>
                    </Button>
                    <Button
                        as-child
                        variant="outline"
                        class="rounded-xl border-slate-300 text-slate-700 hover:bg-slate-50"
                    >
                        <Link href="/">Back to home</Link>
                    </Button>
                </div>
            </div>

        </div>
    </div>
</template>
