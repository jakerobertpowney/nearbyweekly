<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowRight, MapPin, Sparkles } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import PreferenceController from '@/actions/App/Http/Controllers/PreferenceController';
import InputError from '@/components/InputError.vue';
import InterestGroupPicker from '@/components/InterestGroupPicker.vue';
import {
    Alert,
    AlertDescription,
    AlertTitle,
} from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { radiusDescription } from '@/composables/useNearbyWeeklyHelpers';
import AppLayout from '@/layouts/AppLayout.vue';
import type { InterestGroup } from '@/types';

const props = defineProps<{
    interests: InterestGroup[];
    radiusOptions: number[];
    preferences: {
        postcode: string | null;
        radius_miles: number | null;
        newsletter_enabled: boolean;
        interest_ids: number[];
    };
    status?: string | null;
}>();

const postcodePattern = /^[A-Z]{1,2}\d[A-Z\d]?\s?\d[A-Z]{2}$/i;
const postcodeError = ref('');

const form = useForm({
    postcode: props.preferences.postcode ?? '',
    radius_miles: props.preferences.radius_miles ?? 25,
    interests: [...props.preferences.interest_ids],
    newsletter_enabled: props.preferences.newsletter_enabled,
});

const allSubInterests = computed(() =>
    props.interests.flatMap((group) => group.children),
);

const selectedInterests = computed(() =>
    allSubInterests.value.filter((interest) => form.interests.includes(interest.id)),
);

function validatePostcode(): void {
    const val = form.postcode.trim();
    if (val !== '' && !postcodePattern.test(val)) {
        postcodeError.value = 'Enter a valid UK postcode.';
    } else {
        postcodeError.value = '';
    }
}

function submit(): void {
    validatePostcode();
    if (postcodeError.value) return;

    form.put(PreferenceController.update.url(), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Preferences" />

    <AppLayout>
        <div class="bg-white min-h-screen">
            <div class="mx-auto w-full max-w-3xl px-4 py-10 pb-28">

                <!-- Success alert -->
                <Alert v-if="status" class="mb-8 border-emerald-200 bg-emerald-50 text-emerald-800">
                    <Sparkles class="h-4 w-4 text-emerald-700" />
                    <AlertTitle>Preferences saved</AlertTitle>
                    <AlertDescription>{{ status }}</AlertDescription>
                </Alert>

                <!-- Section 1: Interests -->
                <section class="space-y-8">
                    <div class="space-y-3">
                        <p class="text-sm font-semibold uppercase tracking-widest text-[#C4623A]">YOUR INTERESTS</p>
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

                    <div v-if="selectedInterests.length > 0" class="flex flex-wrap items-center gap-2">
                        <span class="text-sm text-slate-400">{{ selectedInterests.length }} selected:</span>
                        <span
                            v-for="interest in selectedInterests"
                            :key="interest.id"
                            class="rounded-full bg-[#F5EAE3] px-3 py-0.5 text-sm font-medium text-[#6B4535]"
                        >{{ interest.name }}</span>
                    </div>

                    <InputError :message="form.errors.interests" />
                </section>

                <!-- Section 2: Location -->
                <section class="space-y-8 pt-12">
                    <div class="space-y-3">
                        <p class="text-sm font-semibold uppercase tracking-widest text-[#C4623A]">YOUR LOCATION</p>
                        <h2 class="font-heading text-3xl font-bold text-slate-900 sm:text-4xl">
                            What postcode should we search around?
                        </h2>
                        <p class="max-w-2xl text-base leading-relaxed text-slate-500">
                            Use your home base or the area you most often want plans for.
                        </p>
                    </div>

                    <div class="space-y-4">
                        <div class="relative">
                            <MapPin class="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                            <Input
                                id="postcode"
                                v-model="form.postcode"
                                type="text"
                                placeholder="SW1A 1AA"
                                class="h-14 rounded-2xl border-slate-300 pl-12 text-lg font-semibold uppercase tracking-wide focus-visible:ring-[#E8956D]"
                                @blur="validatePostcode"
                            />
                        </div>
                        <InputError :message="postcodeError || form.errors.postcode" />

                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-center text-sm text-slate-600">
                                🇬🇧 We currently cover events across the UK
                            </p>
                        </div>
                    </div>
                </section>

                <!-- Section 3: Travel Radius -->
                <section class="space-y-8 pt-12">
                    <div class="space-y-3">
                        <p class="text-sm font-semibold uppercase tracking-widest text-[#C4623A]">HOW FAR WILL YOU TRAVEL?</p>
                        <h2 class="font-heading text-3xl font-bold text-slate-900 sm:text-4xl">
                            How far are you happy to travel?
                        </h2>
                        <p class="max-w-2xl text-base leading-relaxed text-slate-500">
                            Be realistic — a tighter radius means sharper, more actionable recommendations.
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
                                        ? 'bg-[#FDF7F4]0 text-white'
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
                                class="absolute right-4 top-4 flex h-5 w-5 items-center justify-center rounded-full bg-[#FDF7F4]0"
                            >
                                <CheckCircle2 class="h-3 w-3 text-white" />
                            </div>
                        </button>
                    </div>

                    <InputError :message="form.errors.radius_miles" />
                </section>

                <!-- Section 4: Newsletter Toggle -->
                <section class="space-y-8 pt-12">
                    <div class="space-y-3">
                        <p class="text-sm font-semibold uppercase tracking-widest text-[#C4623A]">NEWSLETTER</p>
                        <h2 class="font-heading text-3xl font-bold text-slate-900 sm:text-4xl">
                            Keep your weekly picks active
                        </h2>
                        <p class="max-w-2xl text-base leading-relaxed text-slate-500">
                            Turn this off to pause sends without removing your preferences.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="relative w-full rounded-2xl border-2 p-5 text-left transition-all duration-200"
                        :class="
                            form.newsletter_enabled
                                ? 'border-[#C4623A] bg-[#FDF7F4] shadow-sm'
                                : 'border-slate-200 bg-white hover:border-[#E8D5C8] hover:bg-[#FDF7F4]/40'
                        "
                        @click="form.newsletter_enabled = !form.newsletter_enabled"
                    >
                        <div class="flex items-center gap-4">
                            <div
                                class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full text-xl transition-all duration-200"
                                :class="form.newsletter_enabled ? 'bg-[#FDF7F4]0' : 'bg-slate-100'"
                            >
                                {{ form.newsletter_enabled ? '📬' : '📭' }}
                            </div>
                            <div>
                                <p
                                    class="font-semibold"
                                    :class="form.newsletter_enabled ? 'text-[#1C1109]' : 'text-slate-900'"
                                >
                                    {{ form.newsletter_enabled ? 'Weekly picks active' : 'Weekly picks paused' }}
                                </p>
                                <p class="mt-0.5 text-sm text-slate-500">
                                    {{ form.newsletter_enabled ? 'You\'ll receive your curated picks every Thursday.' : 'Click to resume your weekly picks.' }}
                                </p>
                            </div>
                        </div>
                        <div
                            v-if="form.newsletter_enabled"
                            class="absolute right-4 top-4 flex h-5 w-5 items-center justify-center rounded-full bg-[#FDF7F4]0"
                        >
                            <CheckCircle2 class="h-3 w-3 text-white" />
                        </div>
                    </button>
                </section>

            </div>
        </div>

        <!-- Sticky save footer -->
        <footer class="sticky bottom-0 border-t border-slate-100 bg-white/95 px-6 py-4 backdrop-blur">
            <div class="mx-auto flex max-w-3xl items-center justify-end">
                <Button
                    class="rounded-xl bg-[#FDF7F4]0 px-6 text-white hover:bg-[#A84E2C]"
                    :disabled="form.processing"
                    @click="submit"
                >
                    Save preferences
                    <ArrowRight class="h-4 w-4" />
                </Button>
            </div>
        </footer>
    </AppLayout>
</template>
