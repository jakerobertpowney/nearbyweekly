<script setup lang="ts">
import { useStorage } from '@vueuse/core';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    CheckCircle2,
    Mail,
    MapPin,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import OnboardingController from '@/actions/App/Http/Controllers/OnboardingController';
import InputError from '@/components/InputError.vue';
import InterestGroupPicker from '@/components/InterestGroupPicker.vue';
import {
    normalizePostcode,
    validatePostcode,
} from '@/composables/usePostcodeValidation';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { login } from '@/routes';
import type { InterestGroup } from '@/types';

type FormState = {
    postcode: string;
    radius_miles: number | null;
    interests: number[];
    email: string;
};

type PersistedOnboardingState = FormState & {
    postcode_verified: boolean;
};

type StepDefinition = {
    id: number;
    label: string;
    eyebrow: string;
    title: string;
    description: string;
};

const props = defineProps<{
    interests: InterestGroup[];
    radiusOptions: number[];
    successEmail?: string | null;
}>();

const defaultState: PersistedOnboardingState = {
    postcode: '',
    radius_miles: 25,
    interests: [],
    email: '',
    postcode_verified: false,
};

const steps: StepDefinition[] = [
    {
        id: 1,
        label: 'Interests',
        eyebrow: 'Step 1',
        title: 'Choose the categories you care about most',
        description:
            'Start with intent. Tell us what you would actually open a weekly event email for.',
    },
    {
        id: 2,
        label: 'Location',
        eyebrow: 'Step 2',
        title: 'What postcode should we search around?',
        description:
            'Use your home base or the area you most often want plans for.',
    },
    {
        id: 3,
        label: 'Radius',
        eyebrow: 'Step 3',
        title: 'How far are you happy to travel?',
        description:
            'A realistic travel radius gives you sharper recommendations.',
    },
    {
        id: 4,
        label: 'Email',
        eyebrow: 'Final step',
        title: 'Where should we send your weekly picks?',
        description:
            'Email comes last. We only ask once we know your tastes and location.',
    },
];

const persistedState = useStorage<PersistedOnboardingState>(
    'nearbyweekly-onboarding',
    defaultState,
);
const form = useForm<FormState>({
    postcode: persistedState.value.postcode,
    radius_miles: persistedState.value.radius_miles,
    interests: persistedState.value.interests,
    email: persistedState.value.email,
});
const currentStep = ref(props.successEmail ? 5 : getStepFromUrl());
const stepErrors = reactive<Record<string, string>>({});
const postcodeVerified = ref(
    persistedState.value.postcode_verified &&
        persistedState.value.postcode.trim() !== '',
);
const postcodeChecking = ref(false);
const applyingValidatedPostcode = ref(false);
const postcodePattern = /^[A-Z]{1,2}\d[A-Z\d]?\s?\d[A-Z]{2}$/i;
const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const shouldSkipPostcodeStep = computed(
    () => postcodeVerified.value && form.postcode.trim() !== '',
);
const totalSteps = computed(() => (shouldSkipPostcodeStep.value ? 3 : 4));
const visibleStep = computed(() => Math.min(currentStep.value, 4));
const progress = computed(
    () =>
        ((displayStepNumber(visibleStep.value) - 1) /
            Math.max(totalSteps.value - 1, 1)) *
        100,
);
const currentStepDefinition = computed<StepDefinition | null>(
    () => steps.find((step) => step.id === visibleStep.value) ?? null,
);
const allSubInterests = computed(() =>
    props.interests.flatMap((group) => group.children),
);

const selectedInterests = computed(() =>
    allSubInterests.value.filter((interest) => form.interests.includes(interest.id)),
);

watch(
    () => ({
        postcode: form.postcode,
        radius_miles: form.radius_miles,
        interests: form.interests,
        email: form.email,
        postcode_verified: postcodeVerified.value,
    }),
    (value) => {
        persistedState.value = { ...value };
    },
    { deep: true },
);

watch(
    () => form.postcode,
    (value, previousValue) => {
        if (applyingValidatedPostcode.value) {
            applyingValidatedPostcode.value = false;
            return;
        }

        if (normalizePostcode(value) !== normalizePostcode(previousValue ?? '')) {
            postcodeVerified.value = false;
        }
    },
);

watch(shouldSkipPostcodeStep, (value) => {
    if (value && currentStep.value === 2) {
        currentStep.value = 3;
        syncStepInHistory('replace');
    }
});

function getStepFromUrl(): number {
    if (typeof window === 'undefined') {
        return 1;
    }

    const rawStep = Number(
        new URL(window.location.href).searchParams.get('step') ?? '1',
    );

    return Number.isNaN(rawStep) ? 1 : Math.min(Math.max(rawStep, 1), 5);
}

function syncStepInHistory(mode: 'push' | 'replace' = 'push'): void {
    if (typeof window === 'undefined') {
        return;
    }

    const url = new URL(window.location.href);
    url.searchParams.set('step', String(currentStep.value));

    window.history[mode === 'push' ? 'pushState' : 'replaceState']({}, '', url);
}

function resetStepErrors(): void {
    Object.keys(stepErrors).forEach((key) => delete stepErrors[key]);
}

function displayStepNumber(stepId: number): number {
    if (!shouldSkipPostcodeStep.value) {
        return stepId;
    }

    if (stepId <= 1) {
        return stepId;
    }

    if (stepId === 4) {
        return 3;
    }

    return 2;
}

function stepLabel(stepId: number): string {
    if (stepId === 4) {
        return 'Final step';
    }

    return `Step ${displayStepNumber(stepId)} of ${totalSteps.value}`;
}

async function verifyPostcode(): Promise<boolean> {
    postcodeChecking.value = true;

    try {
        const result = await validatePostcode(form.postcode);

        if (!result.valid) {
            stepErrors.postcode = result.message;
            postcodeVerified.value = false;

            return false;
        }

        applyingValidatedPostcode.value = true;
        form.postcode = result.postcode;
        postcodeVerified.value = true;

        return true;
    } finally {
        postcodeChecking.value = false;
    }
}

async function validateCurrentStep(): Promise<boolean> {
    resetStepErrors();

    if (currentStep.value === 1 && form.interests.length === 0) {
        stepErrors.interests = 'Choose at least one interest.';
    }

    if (currentStep.value === 2 && form.postcode.trim() === '') {
        stepErrors.postcode =
            'Enter your postcode to start tailoring your weekly picks.';
    }

    if (
        currentStep.value === 2 &&
        form.postcode.trim() !== '' &&
        !postcodePattern.test(form.postcode.trim())
    ) {
        stepErrors.postcode = 'Enter a valid UK postcode.';
    }

    if (
        currentStep.value === 2 &&
        form.postcode.trim() !== '' &&
        postcodePattern.test(form.postcode.trim()) &&
        !(await verifyPostcode())
    ) {
        return false;
    }

    if (currentStep.value === 3 && form.radius_miles === null) {
        stepErrors.radius_miles =
            'Pick the distance you are realistically happy to travel.';
    }

    if (currentStep.value === 4 && form.email.trim() === '') {
        stepErrors.email =
            'Add your email so we can send your weekly picks.';
    }

    if (
        currentStep.value === 4 &&
        form.email.trim() !== '' &&
        !emailPattern.test(form.email.trim())
    ) {
        stepErrors.email = 'Enter a valid email address.';
    }

    return Object.keys(stepErrors).length === 0;
}

async function nextStep(): Promise<void> {
    if (!(await validateCurrentStep())) {
        return;
    }

    if (currentStep.value === 1 && shouldSkipPostcodeStep.value) {
        currentStep.value = 3;
    } else {
        currentStep.value = Math.min(currentStep.value + 1, 5);
    }

    syncStepInHistory();
}

function previousStep(): void {
    if (currentStep.value === 3 && shouldSkipPostcodeStep.value) {
        currentStep.value = 1;
    } else {
        currentStep.value = Math.max(currentStep.value - 1, 1);
    }

    syncStepInHistory();
}

async function submit(): Promise<void> {
    if (!(await validateCurrentStep())) {
        return;
    }

    form.post(OnboardingController.store.url(), {
        preserveScroll: true,
        onError: (errors) => {
            if (errors.interests) {
                currentStep.value = 1;
            } else if (errors.postcode) {
                currentStep.value = 2;
            } else if (errors.radius_miles) {
                currentStep.value = 3;
            } else if (errors.email) {
                currentStep.value = 4;
            }

            syncStepInHistory('replace');
        },
        onSuccess: () => {
            persistedState.value = { ...defaultState };
            form.defaults({
                postcode: defaultState.postcode,
                radius_miles: defaultState.radius_miles,
                interests: defaultState.interests,
                email: defaultState.email,
            });
            postcodeVerified.value = false;
        },
    });
}

function handlePopState(): void {
    currentStep.value = getStepFromUrl();
}

function isCompletedStep(stepId: number): boolean {
    return currentStep.value > stepId || currentStep.value === 5;
}

function radiusDescription(radius: number): string {
    if (radius <= 5) {
        return 'Around the corner';
    }

    if (radius <= 10) {
        return 'Close to home';
    }

    if (radius <= 25) {
        return 'Easy evening radius';
    }

    if (radius <= 50) {
        return 'Broader weekend plans';
    }

    return 'Big day-out range';
}

const canProceed = computed<boolean>(() => {
    if (currentStep.value === 1) {
        return form.interests.length > 0;
    }
    if (currentStep.value === 2) {
        return (
            form.postcode.trim() !== '' &&
            postcodePattern.test(form.postcode.trim()) &&
            !postcodeChecking.value
        );
    }
    if (currentStep.value === 3) {
        return form.radius_miles !== null;
    }
    if (currentStep.value === 4) {
        return form.email.trim() !== '' && emailPattern.test(form.email.trim());
    }
    return true;
});

const headerBadge = computed(() => {
    if (currentStep.value === 1) {
        return 'Interests';
    } else if (currentStep.value === 2) {
        return 'Location';
    } else if (currentStep.value === 3) {
        return 'Radius';
    } else if (currentStep.value === 4) {
        return 'Email';
    } else {
        return 'Done';
    }
});

const headerText = computed(() => {
    if (currentStep.value === 1) {
        return 'Lets get started by picking your interests.';
    } else if (currentStep.value === 2) {
        return 'Next, set your area of interest.';
    } else if (currentStep.value === 3) {
        return 'How far are you happy to travel?';
    } else if (currentStep.value === 4) {
        return 'Finally, where should we send your weekly digest?';
    } else {
        return 'Done';
    }
});

onMounted(() => {
    if (props.successEmail) {
        persistedState.value = { ...defaultState };
        form.defaults({
            postcode: defaultState.postcode,
            radius_miles: defaultState.radius_miles,
            interests: defaultState.interests,
            email: defaultState.email,
        });
        form.reset();
        postcodeVerified.value = false;
    }

    if (shouldSkipPostcodeStep.value && currentStep.value === 2) {
        currentStep.value = 3;
    }

    syncStepInHistory('replace');
    window.addEventListener('popstate', handlePopState);
});

onUnmounted(() => {
    window.removeEventListener('popstate', handlePopState);
});
</script>

<template>
    <Head title="Start your weekly picks" />

    <div class="flex min-h-screen flex-col bg-white">

        <!-- Top bar -->
        <header class="border-b border-slate-100 bg-white px-6 py-4">
            <div class="mx-auto flex max-w-3xl items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-brand-surface border border-brand-border">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="#C4623A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/>
                            <path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/>
                        </svg>
                    </div>
                    <span class="font-heading text-lg font-bold">
                        <span class="text-brand-text">Nearby</span><span class="text-brand-primary ml-1">Weekly</span>
                    </span>
                </div>
<!--                <span v-if="currentStep < 5" class="text-sm text-slate-400">-->
<!--                    Step {{ visibleStep }} of 4-->
<!--                </span>-->
            </div>
        </header>

<!--        &lt;!&ndash; Step indicator &ndash;&gt;-->
<!--        <div v-if="currentStep < 5" class="border-b border-slate-100 bg-white px-6 py-5">-->
<!--            <div class="mx-auto max-w-3xl">-->
<!--                <div class="flex items-center">-->
<!--                    <template v-for="(step, index) in steps" :key="step.id">-->
<!--                        <div class="flex flex-col items-center gap-2">-->
<!--                            <div-->
<!--                                class="flex h-9 w-9 items-center justify-center rounded-full text-sm font-semibold transition-all duration-300"-->
<!--                                :class="[-->
<!--                                    isCompletedStep(step.id)-->
<!--                                        ? 'bg-[#C4623A] text-white'-->
<!--                                        : visibleStep === step.id-->
<!--                                          ? 'border-2 border-[#C4623A] bg-white text-[#A84E2C]'-->
<!--                                          : 'border-2 border-slate-200 bg-white text-slate-400',-->
<!--                                ]"-->
<!--                            >-->
<!--                                <CheckCircle2 v-if="isCompletedStep(step.id)" class="h-4 w-4" />-->
<!--                                <span v-else>{{ step.id }}</span>-->
<!--                            </div>-->
<!--                            <span-->
<!--                                class="hidden text-xs font-medium sm:block"-->
<!--                                :class="[-->
<!--                                    visibleStep === step.id-->
<!--                                        ? 'text-[#A84E2C]'-->
<!--                                        : isCompletedStep(step.id)-->
<!--                                          ? 'text-slate-600'-->
<!--                                          : 'text-slate-400',-->
<!--                                ]"-->
<!--                            >{{ step.label }}</span>-->
<!--                        </div>-->
<!--                        <div-->
<!--                            v-if="index < steps.length - 1"-->
<!--                            class="mx-3 mb-5 h-0.5 flex-1 transition-all duration-500 sm:mb-7"-->
<!--                            :class="isCompletedStep(step.id) ? 'bg-orange-400' : 'bg-slate-200'"-->
<!--                        />-->
<!--                    </template>-->
<!--                </div>-->
<!--            </div>-->
<!--        </div>-->

        <!-- Main content -->
        <main class="flex flex-1 flex-col">
            <div class="mx-auto w-full max-w-3xl flex-1 py-10">

                <!-- Step 1: Interests -->
                <section v-if="currentStep === 1" class="space-y-8">
                    <div class="space-y-3">
                        <p class="text-sm font-semibold uppercase tracking-widest text-[#C4623A]">{{ stepLabel(1) }}</p>
                        <h1 class="font-heading text-3xl font-bold text-slate-900 sm:text-4xl">
                            What kind of events do you love?
                        </h1>
                        <p class="max-w-2xl text-base leading-relaxed text-slate-500">
                            Pick everything you'd genuinely open an email for. We'll curate the best of each category near you every week.
                        </p>
                    </div>

                    <InterestGroupPicker
                        :groups="interests"
                        v-model="form.interests"
                    />

                    <div v-if="form.interests.length > 0" class="flex flex-wrap items-center gap-2">
                        <span class="text-sm text-slate-400">{{ form.interests.length }} selected:</span>
                        <span
                            v-for="interest in selectedInterests"
                            :key="interest.id"
                            class="rounded-full bg-[#F5EAE3] px-3 py-0.5 text-sm font-medium text-[#6B4535]"
                        >{{ interest.name }}</span>
                    </div>

                    <InputError :message="stepErrors.interests || form.errors.interests" />
                </section>

                <!-- Step 2: Location -->
                <section v-else-if="currentStep === 2" class="space-y-8">
                    <div class="space-y-3">
                        <p class="text-sm font-semibold uppercase tracking-widest text-[#C4623A]">{{ stepLabel(2) }}</p>
                        <h1 class="font-heading text-3xl font-bold text-slate-900 sm:text-4xl">
                            Where are you based?
                        </h1>
                        <p class="max-w-2xl text-base leading-relaxed text-slate-500">
                            We'll search for events around this postcode each week. Use your home or wherever you usually want recommendations for.
                        </p>
                    </div>

                    <div class="mx-auto space-y-4">
                        <div class="relative">
                            <MapPin class="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                            <Input
                                id="postcode"
                                v-model="form.postcode"
                                type="text"
                                placeholder="SW1A 1AA"
                                :disabled="postcodeChecking"
                                class="h-14 rounded-2xl border-slate-300 pl-12 text-lg font-semibold uppercase tracking-wide focus-visible:ring-[#E8956D]"
                            />
                        </div>
                        <InputError :message="stepErrors.postcode || form.errors.postcode" />
                        <p class="text-center text-sm text-slate-400">
                            Only used to find nearby events — never shared or sold.
                        </p>
                    </div>

                    <div class="mx-auto rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-center text-sm text-slate-600">
                            🇬🇧 We currently cover events across the UK
                        </p>
                    </div>
                </section>

                <!-- Step 3: Distance -->
                <section v-else-if="currentStep === 3" class="space-y-8">
                    <div class="space-y-3">
                        <p class="text-sm font-semibold uppercase tracking-widest text-[#C4623A]">{{ stepLabel(3) }}</p>
                        <h1 class="font-heading text-3xl font-bold text-slate-900 sm:text-4xl">
                            How far will you travel?
                        </h1>
                        <p class="max-w-2xl text-base leading-relaxed text-slate-500">
                            Be realistic — a tighter radius means sharper, more actionable recommendations every week.
                        </p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <button
                            v-for="radius in radiusOptions"
                            :key="radius"
                            type="button"
                            class="relative flex items-center gap-4 rounded-2xl border-2 p-5 text-left transition-all duration-200"
                            :class="
                                form.radius_miles === radius
                                    ? 'border-[#C4623A] bg-[#FDF7F4] shadow-sm'
                                    : 'border-slate-200 bg-white hover:border-[#E8D5C8] hover:bg-[#FDF7F4]/40'
                            "
                            @click="form.radius_miles = radius"
                        >
                            <div
                                class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full text-base font-bold transition-all duration-200"
                                :class="
                                    form.radius_miles === radius
                                        ? 'bg-[#C4623A] text-white'
                                        : 'bg-slate-100 text-slate-600'
                                "
                            >
                                {{ radius }}
                            </div>
                            <div>
                                <p
                                    class="font-semibold"
                                    :class="form.radius_miles === radius ? 'text-[#1C1109]' : 'text-slate-900'"
                                >{{ radius }} miles</p>
                                <p class="mt-0.5 text-sm text-slate-500">{{ radiusDescription(radius) }}</p>
                            </div>
                            <div
                                v-if="form.radius_miles === radius"
                                class="absolute right-4 top-4 flex h-5 w-5 items-center justify-center rounded-full bg-[#C4623A]"
                            >
                                <CheckCircle2 class="h-3 w-3 text-white" />
                            </div>
                        </button>
                    </div>

                    <InputError :message="stepErrors.radius_miles || form.errors.radius_miles" />
                </section>

                <!-- Step 4: Email -->
                <section v-else-if="currentStep === 4" class="space-y-8">
                    <div class="space-y-3">
                        <p class="text-sm font-semibold uppercase tracking-widest text-[#C4623A]">Final step</p>
                        <h1 class="font-heading text-3xl font-bold text-slate-900 sm:text-4xl">
                            Where should we send it?
                        </h1>
                        <p class="max-w-2xl text-base leading-relaxed text-slate-500">
                            Your personalised weekly event digest, curated from everything you just told us.
                        </p>
                    </div>

                    <!-- Summary preview -->
                    <div class="rounded-2xl border border-[#E8D5C8] bg-gradient-to-br from-[#FDF7F4] to-[#FDF7F4] p-5">
                        <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-[#C4623A]">Your weekly picks will include</p>
                        <div class="space-y-2.5">
                            <div class="flex items-start gap-3 text-sm">
                                <span class="mt-0.5 text-base leading-none">✨</span>
                                <div>
                                    <span class="font-medium text-slate-700">Interests: </span>
                                    <span class="text-slate-600">{{ selectedInterests.length ? selectedInterests.map((i) => i.name).join(', ') : '—' }}</span>
                                </div>
                            </div>
                            <div class="flex items-start gap-3 text-sm">
                                <span class="mt-0.5 text-base leading-none">📍</span>
                                <div>
                                    <span class="font-medium text-slate-700">Around: </span>
                                    <span class="text-slate-600">{{ form.postcode || '—' }}</span>
                                </div>
                            </div>
                            <div class="flex items-start gap-3 text-sm">
                                <span class="mt-0.5 text-base leading-none">🗺️</span>
                                <div>
                                    <span class="font-medium text-slate-700">Within: </span>
                                    <span class="text-slate-600">{{ form.radius_miles ? `${form.radius_miles} miles` : '—' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Email input -->
                    <div class="mx-auto space-y-4">
                        <div class="relative">
                            <Mail class="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                                placeholder="you@example.com"
                                class="h-14 rounded-2xl border-slate-300 pl-12 text-lg focus-visible:ring-[#E8956D]"
                            />
                        </div>
                        <InputError :message="stepErrors.email || form.errors.email" />
                        <p class="text-center text-sm text-slate-400">
                            No password needed. We'll send a magic link to manage preferences later.
                        </p>
                    </div>
                </section>

                <!-- Step 5: Success -->
                <section v-else class="flex flex-col items-center space-y-8 py-8 text-center">
                    <div class="flex h-20 w-20 items-center justify-center rounded-full bg-emerald-100">
                        <CheckCircle2 class="h-10 w-10 text-emerald-500" />
                    </div>

                    <div class="space-y-3">
                        <h1 class="font-heading text-3xl font-bold text-slate-900 sm:text-4xl">You're all set!</h1>
                        <p class="mx-auto max-w-sm text-base leading-relaxed text-slate-500">
                            Your first weekly picks are on their way. We've also sent a magic link so you can update your preferences any time.
                        </p>
                    </div>

                    <div class="w-full max-w-sm rounded-2xl border border-slate-200 bg-slate-50 p-5 text-left">
                        <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-slate-400">Saved profile</p>
                        <div class="space-y-2.5 text-sm">
                            <div class="flex items-start gap-3">
                                <span class="mt-0.5 text-base leading-none">✨</span>
                                <span class="text-slate-700">{{ selectedInterests.length ? selectedInterests.map((i) => i.name).join(', ') : 'Interests saved' }}</span>
                            </div>
                            <div class="flex items-start gap-3">
                                <span class="mt-0.5 text-base leading-none">📍</span>
                                <span class="text-slate-700">{{ form.postcode || 'Location saved' }}</span>
                            </div>
                            <div class="flex items-start gap-3">
                                <span class="mt-0.5 text-base leading-none">🗺️</span>
                                <span class="text-slate-700">{{ form.radius_miles ? `${form.radius_miles} miles` : 'Radius saved' }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row">
                        <Button
                            as-child
                            variant="outline"
                            class="rounded-xl border-slate-300 text-black hover:bg-slate-100"
                        >
                            <Link :href="login()">Open sign-in</Link>
                        </Button>
                        <Button
                            as-child
                            class="rounded-xl bg-[#C4623A] text-white hover:bg-[#A84E2C]"
                        >
                            <Link href="/">Back to home</Link>
                        </Button>
                    </div>
                </section>

            </div>
        </main>

        <!-- Sticky bottom nav -->
        <footer
            v-if="currentStep < 5"
            class="sticky bottom-0 border-t border-slate-100 bg-white/95 px-6 py-4 backdrop-blur"
        >
            <div class="mx-auto flex max-w-3xl items-center justify-between">
                <Button
                    v-if="currentStep > 1"
                    type="button"
                    variant="ghost"
                    class="gap-2 text-slate-500 hover:text-slate-900"
                    @click="previousStep"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Back
                </Button>
                <div v-else />

                <Button
                    v-if="currentStep < 4"
                    type="button"
                    class="rounded-xl bg-[#C4623A] px-6 text-white hover:bg-[#A84E2C]"
                    :disabled="!canProceed"
                    @click="nextStep"
                >
                    {{
                        currentStep === 2 && postcodeChecking
                            ? 'Checking postcode...'
                            : 'Continue'
                    }}
                    <ArrowRight class="h-4 w-4" />
                </Button>
                <Button
                    v-else
                    type="button"
                    class="rounded-xl bg-[#C4623A] px-6 text-white hover:bg-[#A84E2C]"
                    :disabled="!canProceed || form.processing"
                    @click="submit"
                >
                    Get my weekly picks
                    <ArrowRight class="h-4 w-4" />
                </Button>
            </div>
        </footer>

    </div>
</template>
