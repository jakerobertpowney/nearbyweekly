<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ChevronDown, Database, MapPin, Sparkles } from 'lucide-vue-next';
import { ref } from 'vue';
import HeroCards from '@/components/HeroCards.vue';
import type { HeroCard, CardPlacement } from '@/components/HeroCards.vue';
import { normalizePostcode, validatePostcode } from '@/composables/usePostcodeValidation';
import { dashboard } from '@/routes';
import { login } from '@/routes';

withDefaults(
    defineProps<{
        canRegister?: boolean;
    }>(),
    {
        canRegister: true,
    },
);

// --- Postcode inputs ---
const postcodePattern = /^[A-Z]{1,2}\d[A-Z\d]?\s?\d[A-Z]{2}$/i;
const heroPostcode = ref('');
const heroPostcodeError = ref('');
const heroPostcodeChecking = ref(false);
const footerPostcode = ref('');
const footerPostcodeError = ref('');
const footerPostcodeChecking = ref(false);

type PostcodeSource = 'hero' | 'footer';

async function goWithPostcode(
    postcode: string,
    source: PostcodeSource,
): Promise<void> {
    const errorRef =
        source === 'hero' ? heroPostcodeError : footerPostcodeError;
    const loadingRef =
        source === 'hero' ? heroPostcodeChecking : footerPostcodeChecking;
    const val = normalizePostcode(postcode);

    if (!val) {
        errorRef.value = 'Enter your postcode to get started.';
        return;
    }
    if (!postcodePattern.test(val)) {
        errorRef.value = 'Enter a valid UK postcode (e.g. SW1A 1AA).';
        return;
    }

    loadingRef.value = true;

    try {
        const result = await validatePostcode(val);

        if (!result.valid) {
            errorRef.value = result.message;
            return;
        }

        errorRef.value = '';

        const existing = JSON.parse(
            localStorage.getItem('nearbyweekly-onboarding') ?? '{}',
        );
        localStorage.setItem(
            'nearbyweekly-onboarding',
            JSON.stringify({
                ...existing,
                postcode: result.postcode,
                postcode_verified: true,
            }),
        );
    } catch {
        errorRef.value = 'We could not save your postcode. Please try again.';
        return;
    } finally {
        loadingRef.value = false;
    }

    router.visit('/start');
}

// --- Preview tab ---
const activeTab = ref<'email' | 'browser'>('email');

// --- FAQ accordion ---
const openFaq = ref<number | null>(null);

function toggleFaq(index: number): void {
    openFaq.value = openFaq.value === index ? null : index;
}

const faqs = [
    {
        q: 'Is it really free?',
        a: 'Yes. Completely. No premium tier, no paywall. We earn from affiliate ticket links — you pay the same price either way.',
    },
    {
        q: 'How often will I get emails?',
        a: 'Once a week, every Thursday morning. That\'s it. No drip campaigns, no "you might also like" spam. One email. The good stuff.',
    },
    {
        q: 'What if I move or my interests change?',
        a: 'Update your postcode, radius, or interests any time from your preferences page. Your next issue adapts instantly.',
    },
    {
        q: 'Can I unsubscribe easily?',
        a: 'One click in every email. No guilt trip. No "are you sure?" gauntlet. Just gone.',
    },
    {
        q: 'Where do the events come from?',
        a: 'We pull from Ticketmaster, Billetto, Data Thistle, and other UK event feeds. Thousands of events, filtered down to the ones that matter to you.',
    },
];

const categories = [
    {
        emoji: '🎵',
        name: 'Music',
        sub: 'Live gigs, concerts, festivals, indie, jazz, folk...',
        img: '/img/landing/concert-crowd.webp',
    },
    {
        emoji: '🍽️',
        name: 'Food & Drink',
        sub: 'Markets, tastings, cooking classes, wine, craft beer...',
        img: '/img/landing/food-market.webp',
    },
    {
        emoji: '🎭',
        name: 'Arts & Entertainment',
        sub: 'Theatre, comedy, film, spoken word, arts & culture...',
        img: '/img/landing/theatre-stage.webp',
    },
    {
        emoji: '💪',
        name: 'Health & Fitness',
        sub: 'Yoga, runs, cycling, martial arts, wellness...',
        img: '/img/landing/outdoor-yoga.webp',
    },
    {
        emoji: '👨‍👩‍👧',
        name: 'Family',
        sub: 'Days out, kids activities, educational days...',
        img: '/img/landing/family-day-out.webp',
    },
    {
        emoji: '🌿',
        name: 'Outdoors & Nature',
        sub: 'Hiking, wildlife, farming, outdoor adventures...',
        img: '/img/landing/hiking-trail.webp',
    },
    {
        emoji: '💼',
        name: 'Tech & Professional',
        sub: 'Meetups, conferences, workshops, startup events...',
        img: '/img/landing/tech-meetup.webp',
    },
];

// Hero floating cards
const heroCards: HeroCard[] = [
    {
        img: '/img/hero/comedy.webp',
        categoryEmoji: '🎭',
        category: 'Comedy',
        title: 'Monkey Barrel Comedy Club',
        venue: 'Old Street, EC1',
        date: 'Sat 29 Mar',
        distance: '1.2 miles',
    },
    {
        img: '/img/hero/food-market.webp',
        categoryEmoji: '🍕',
        category: 'Food',
        title: 'Borough Market Night Market',
        venue: 'London Bridge, SE1',
        date: 'Fri 28 Mar',
        distance: '0.4 miles',
    },
    {
        img: '/img/hero/concert.webp',
        categoryEmoji: '🎵',
        category: 'Live Music',
        title: 'Jazz Café Sessions',
        venue: 'Camden, NW1',
        date: 'Tue 1 Apr',
        distance: '3.4 miles',
    },
    {
        img: '/img/hero/outdoors.webp',
        categoryEmoji: '🥾',
        category: 'Outdoors',
        title: 'Box Hill Sunset Hike',
        venue: 'Dorking, Surrey',
        date: 'Sun 6 Apr',
        distance: '12 miles',
    },
    {
        img: '/img/hero/yoga.webp',
        categoryEmoji: '🧘',
        category: 'Wellness',
        title: 'Sunrise Yoga — Clapham',
        venue: 'Clapham Common',
        date: 'Wed 2 Apr',
        distance: '2.1 miles',
    },
    {
        img: '/img/hero/theatre.webp',
        categoryEmoji: '🎪',
        category: 'Theatre',
        title: 'Les Misérables — Open Air',
        venue: "Regent's Park, NW1",
        date: 'Fri 4 Apr',
        distance: '3.1 miles',
    },
    {
        img: '/img/hero/family.webp',
        categoryEmoji: '👨‍👩‍👧',
        category: 'Family',
        title: 'Kew Gardens Easter Trail',
        venue: 'Kew, TW9',
        date: 'Sat 5 Apr',
        distance: '5.8 miles',
    },
];

// Organic floating card placements — each side gets scattered positions, varied sizes
const leftPlacements: CardPlacement[] = [
    {
        top: '2%',
        offset: '-10px',
        rotate: -6,
        scale: 0.92,
        width: '190px',
        delay: 150,
        zIndex: 3,
    },
    {
        top: '31.5%',
        offset: '40px',
        rotate: 2,
        scale: 1.05,
        width: '210px',
        delay: 350,
        zIndex: 5,
    },
    {
        top: '62%',
        offset: '-20px',
        rotate: -3,
        scale: 0.88,
        width: '175px',
        delay: 500,
        zIndex: 2,
    },
];

const rightPlacements: CardPlacement[] = [
    {
        top: '2%',
        offset: '-10px',
        rotate: 7,
        scale: 0.92,
        width: '190px',
        delay: 150,
        zIndex: 3,
    },
    {
        top: '31.5%',
        offset: '40px',
        rotate: -1,
        scale: 1.05,
        width: '210px',
        delay: 350,
        zIndex: 5,
    },
    {
        top: '62%',
        offset: '-20px',
        rotate: 4,
        scale: 0.88,
        width: '175px',
        delay: 500,
        zIndex: 2,
    },
];

// Event cards for the newsletter preview — 3 time-bucket sections × 2 cards
const previewSections = [
    {
        label: '🎉 THIS WEEKEND',
        events: [
            {
                emoji: '🎭',
                category: 'Comedy',
                title: 'Monkey Barrel Comedy Club',
                venue: 'Old Street, EC1',
                date: 'Sat 29 Mar',
                dist: '1.2 miles',
                img: '/img/landing/comedy-crowd.webp',
            },
            {
                emoji: '🍕',
                category: 'Food',
                title: 'Borough Market Night Market',
                venue: 'London Bridge, SE1',
                date: 'Fri 28 Mar',
                dist: '0.4 miles',
                img: '/img/landing/food-market.webp',
            },
        ],
    },
    {
        label: '🗓️ THIS WEEK',
        events: [
            {
                emoji: '🎵',
                category: 'Live Music',
                title: 'Jazz Café Sessions',
                venue: 'Camden, NW1',
                date: 'Tue 1 Apr',
                dist: '3.4 miles',
                img: '/img/landing/jazz-cafe.webp',
            },
            {
                emoji: '🧘',
                category: 'Wellness',
                title: 'Sunrise Yoga — Clapham',
                venue: 'Clapham Common',
                date: 'Wed 2 Apr',
                dist: '2.1 miles',
                img: '/img/landing/sunrise-yoga.webp',
            },
        ],
    },
    {
        label: '🔜 COMING UP',
        events: [
            {
                emoji: '👨‍👩‍👧',
                category: 'Family',
                title: 'Kew Gardens Easter Trail',
                venue: 'Kew, TW9',
                date: 'Sat 5 Apr',
                dist: '5.8 miles',
                img: '/img/landing/family-day-out.webp',
            },
            {
                emoji: '🥾',
                category: 'Outdoors',
                title: 'Box Hill Sunset Hike',
                venue: 'Dorking, Surrey',
                date: 'Sun 6 Apr',
                dist: '12 miles',
                img: '/img/landing/hiking-trail.webp',
            },
        ],
    },
];
</script>

<template>
    <div class="min-h-screen bg-white text-slate-900">
        <!-- ───── NAV ───── -->
        <nav
            class="sticky top-0 z-50 bg-white/90 backdrop-blur"
        >
            <div
                class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4"
            >
                <a href="/" class="flex items-center gap-2.5">
                    <span class="flex h-8 w-8 items-center justify-center rounded-xl border" style="background:#F5EAE3; border-color:#E8D5C8;">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="#C4623A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/>
                            <path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/>
                        </svg>
                    </span>
                    <span class="font-heading text-lg font-bold">
                        <span style="color:#1C1109">Nearby</span><span style="color:#C4623A" class="ml-1">Weekly</span>
                    </span>
                </a>
                <div class="flex items-center gap-3">
                    <template v-if="$page.props.auth?.user">
                        <a
                            :href="dashboard().url"
                            class="rounded-full px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90"
                            style="background: #c4623a"
                        >
                            Dashboard
                        </a>
                    </template>
                    <template v-else>
                        <a
                            :href="login().url"
                            class="text-sm font-medium transition hover:opacity-70"
                            style="color: #6b4535"
                            >Sign in</a
                        >
                        <a
                            v-if="canRegister"
                            href="/start"
                            class="rounded-full px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90"
                            style="background: #c4623a"
                        >
                            Build my picks
                        </a>
                    </template>
                </div>
            </div>
        </nav>

        <!-- ───── HERO ───── -->
        <section style="background: #ffffff">
            <!--
                Desktop (lg+): 3-column grid — card column | headline+CTA | card column
                Tablet/mobile: single centred column, scroll strip below CTA
            -->
            <div class="hero-grid">
                <!-- Left cards (desktop only) -->
                <HeroCards
                    :cards="heroCards.slice(0, 3)"
                    side="left"
                    :placements="leftPlacements"
                />

                <!-- Centre: headline + CTA -->
                <div class="hero-center max-w-2xl mx-auto">
                    <span class="hero-pill">Weekly UK events newsletter</span>
                    <h1 class="hero-heading font-heading">
                        Events you'd actually go to
                    </h1>
                    <p class="hero-sub">
                        A weekly email of events happening near you, matched to
                        what you love.
                    </p>

                    <!-- Postcode CTA -->
                    <div class="hero-cta-wrap">
                        <div class="hero-cta-row">
                            <div class="hero-input-wrap">
                                <MapPin class="hero-input-icon" />
                                <input
                                    v-model="heroPostcode"
                                    type="text"
                                    placeholder="Enter your postcode..."
                                    class="hero-input"
                                    :disabled="heroPostcodeChecking"
                                    @keyup.enter="
                                        goWithPostcode(
                                            heroPostcode,
                                            'hero',
                                        )
                                    "
                                />
                            </div>
                            <button
                                class="hero-btn"
                                :disabled="heroPostcodeChecking"
                                @click="
                                    goWithPostcode(
                                        heroPostcode,
                                        'hero',
                                    )
                                "
                            >
                                {{
                                    heroPostcodeChecking
                                        ? 'Checking postcode...'
                                        : "Show me what's on →"
                                }}
                            </button>
                        </div>
                        <p
                            v-if="heroPostcodeError"
                            class="mt-2 text-sm text-red-500"
                        >
                            {{ heroPostcodeError }}
                        </p>
                        <p v-else class="hero-trust">
                            ✓ 100% free &nbsp;·&nbsp; ✓ Unsubscribe anytime
                            &nbsp;·&nbsp; ✓ No spam, ever
                        </p>
                    </div>
                </div>

                <!-- Right cards (desktop only) -->
                <HeroCards
                    :cards="heroCards.slice(3, 6)"
                    side="right"
                    :placements="rightPlacements"
                />
            </div>

            <!-- Mobile scroll strip (hidden on desktop) -->
            <div class="mobile-cards-strip" aria-hidden="true">
                <p class="mobile-strip-label">Swipe to see what you'd get</p>
                <div class="mobile-strip">
                    <div
                        v-for="card in heroCards.slice(0, 5)"
                        :key="card.title"
                        class="mobile-card"
                    >
                        <div class="mobile-photo">
                            <img :src="card.img" :alt="''" loading="lazy" />
                        </div>
                        <div class="mobile-card-body">
                            <span class="mobile-badge"
                                >{{ card.categoryEmoji }}
                                {{ card.category }}</span
                            >
                            <p class="mobile-title">{{ card.title }}</p>
                            <p class="mobile-venue">{{ card.venue }}</p>
                            <span class="mobile-dist"
                                >📍 {{ card.distance }}</span
                            >
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ───── INLINE NEWSLETTER PREVIEW ───── -->
        <section class="py-16" style="background: #fdf7f4">
            <div class="mx-auto max-w-3xl px-4">
                <div class="mb-10 space-y-3 text-center">
                    <p
                        class="text-sm font-semibold tracking-widest uppercase"
                        style="color: #c4623a"
                    >
                        Here's what landed last Thursday
                    </p>
                    <h2
                        class="font-heading text-4xl font-bold"
                        style="color: #1c1109"
                    >
                        See exactly what you'd get
                    </h2>
                    <p class="mx-auto max-w-sm" style="color: #6b4535">
                        A real mock of your Thursday morning email — events
                        matched to your interests, sorted by distance.
                    </p>
                </div>

                <!-- Tab bar -->
<!--                <div class="mb-6 flex justify-center">-->
<!--                    <div-->
<!--                        class="inline-flex rounded-full border p-1 shadow-sm"-->
<!--                        style="border-color: #e8d5c8; background: white"-->
<!--                    >-->
<!--                        <button-->
<!--                            type="button"-->
<!--                            class="rounded-full px-5 py-2 text-sm font-medium transition-all"-->
<!--                            :class="-->
<!--                                activeTab === 'email'-->
<!--                                    ? 'text-white shadow-sm'-->
<!--                                    : 'hover:opacity-70'-->
<!--                            "-->
<!--                            :style="-->
<!--                                activeTab === 'email'-->
<!--                                    ? 'background: #C4623A; color: white;'-->
<!--                                    : 'color: #6B4535;'-->
<!--                            "-->
<!--                            @click="activeTab = 'email'"-->
<!--                        >-->
<!--                            Via email-->
<!--                        </button>-->
<!--                        <button-->
<!--                            type="button"-->
<!--                            class="rounded-full px-5 py-2 text-sm font-medium transition-all"-->
<!--                            :class="-->
<!--                                activeTab === 'browser'-->
<!--                                    ? 'text-white shadow-sm'-->
<!--                                    : 'hover:opacity-70'-->
<!--                            "-->
<!--                            :style="-->
<!--                                activeTab === 'browser'-->
<!--                                    ? 'background: #C4623A; color: white;'-->
<!--                                    : 'color: #6B4535;'-->
<!--                            "-->
<!--                            @click="activeTab = 'browser'"-->
<!--                        >-->
<!--                            In the browser-->
<!--                        </button>-->
<!--                    </div>-->
<!--                </div>-->

                <!-- Email frame -->
                <div
                    v-if="activeTab === 'email'"
                    class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-300/40"
                    style="box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12)"
                >
                    <!-- Gmail chrome -->
                    <div
                        class="flex items-center gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3"
                    >
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-full bg-red-500 text-xs font-bold text-white"
                        >
                            M
                        </div>
                        <span class="text-sm font-medium text-slate-500"
                            >Gmail</span
                        >
                        <div class="mx-2 h-4 w-px bg-slate-300" />
                        <div class="flex flex-1 items-center gap-2 truncate">
                            <div
                                class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md border"
                                style="background:#F5EAE3; border-color:#E8D5C8;"
                            >
                                <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="#C4623A" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/>
                                    <path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/>
                                </svg>
                            </div>
                            <span
                                class="truncate text-sm font-medium text-slate-700"
                                >Nearby Weekly — What's on near SE1 this week
                                🎉</span
                            >
                        </div>
                        <span
                            class="hidden shrink-0 text-xs text-slate-400 sm:inline"
                            >1 of 32</span
                        >
                    </div>

                    <!-- Scrollable email body -->
                    <div class="relative">
                        <div class="overflow-y-auto" style="max-height: 600px">
                            <div
                                class="px-6 py-8 sm:px-10"
                                style="
                                    font-family:
                                        -apple-system, 'Helvetica Neue', Arial,
                                        sans-serif;
                                "
                            >
                                <!-- Email header -->
                                <div class="mb-6 flex items-center gap-3">
                                    <div
                                        class="flex h-10 w-10 items-center justify-center rounded-xl border"
                                        style="background:#F5EAE3; border-color:#E8D5C8;"
                                    >
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="#C4623A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/>
                                            <path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="font-bold text-slate-900">
                                            <span>Nearby</span> <span style="color:#C4623A">Weekly</span>
                                        </p>
                                        <p class="text-xs text-slate-500">
                                            hello@nearbyweekly.co.uk · Thursday
                                            8:00 AM
                                        </p>
                                    </div>
                                </div>

                                <div
                                    class="mb-2 h-1 w-full rounded-full"
                                    style="
                                        background: linear-gradient(
                                            to right,
                                            #c4623a,
                                            #a84e2c
                                        );
                                    "
                                />

                                <h2
                                    class="mt-6 mb-1 font-heading text-2xl font-bold"
                                    style="color: #1c1109"
                                >
                                    Here's what's on near SE1 this week
                                </h2>
                                <p class="mb-8 text-sm" style="color: #9c6b54">
                                    6 events matched to your interests · within
                                    12 miles
                                </p>

                                <!-- Time-bucketed event sections -->
                                <div
                                    v-for="section in previewSections"
                                    :key="section.label"
                                    class="mb-8"
                                >
                                    <p
                                        class="mb-4 text-xs font-bold tracking-widest uppercase"
                                        style="color: #c4623a"
                                    >
                                        {{ section.label }}
                                    </p>
                                    <div
                                        class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                                    >
                                        <div
                                            v-for="event in section.events"
                                            :key="event.title"
                                            class="overflow-hidden rounded-xl bg-white shadow-sm"
                                            style="border: 1px solid #e8d5c8"
                                        >
                                            <!-- Event photo -->
                                            <div
                                                class="relative aspect-video overflow-hidden bg-slate-100"
                                            >
                                                <img
                                                    :src="event.img"
                                                    :alt="event.title"
                                                    class="h-full w-full object-cover"
                                                    loading="lazy"
                                                    style="
                                                        filter: saturate(1.05);
                                                    "
                                                />
                                                <div
                                                    class="absolute inset-0"
                                                    style="
                                                        background: rgba(
                                                            26,
                                                            53,
                                                            40,
                                                            0.04
                                                        );
                                                    "
                                                />
                                                <!-- Category badge overlay -->
                                                <div
                                                    class="absolute top-3 left-3"
                                                >
                                                    <span
                                                        class="rounded-full bg-white/90 px-2.5 py-1 text-xs font-semibold shadow-sm backdrop-blur-sm"
                                                        style="color: #c4623a"
                                                    >
                                                        {{ event.emoji }}
                                                        {{ event.category }}
                                                    </span>
                                                </div>
                                            </div>

                                            <!-- Event details -->
                                            <div class="p-4">
                                                <p
                                                    class="mb-1 font-heading leading-snug font-bold"
                                                    style="color: #1c1109"
                                                >
                                                    {{ event.title }}
                                                </p>
                                                <p
                                                    class="text-sm"
                                                    style="color: #9c6b54"
                                                >
                                                    {{ event.venue }}
                                                </p>
                                                <div
                                                    class="mt-2 flex items-center gap-2"
                                                >
                                                    <span
                                                        class="text-xs"
                                                        style="color: #9c6b54"
                                                        >{{ event.date }}</span
                                                    >
                                                    <span style="color: #e8d5c8"
                                                        >·</span
                                                    >
                                                    <span
                                                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                                                        style="
                                                            background: #fdf7f4;
                                                            color: #9c6b54;
                                                        "
                                                    >
                                                        📍 {{ event.dist }}
                                                    </span>
                                                </div>
                                                <a
                                                    href="/start"
                                                    class="mt-3 block w-full rounded-lg py-2.5 text-center text-sm font-semibold text-white transition hover:opacity-90"
                                                    style="background: #c4623a"
                                                >
                                                    Get Tickets →
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Email footer -->
                                <div
                                    class="mt-8 border-t pt-6 text-center text-xs"
                                    style="
                                        border-color: #e8d5c8;
                                        color: #9c6b54;
                                    "
                                >
                                    <p>
                                        <a
                                            href="/preferences"
                                            class="underline hover:opacity-70"
                                            >Update preferences</a
                                        >
                                        &nbsp;·&nbsp;
                                        <a
                                            href="#"
                                            class="underline hover:opacity-70"
                                            >Unsubscribe</a
                                        >
                                    </p>
                                    <p class="mt-2">
                                        Made with ☕ somewhere in the UK
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Fade-to-white gradient hint -->
                        <div
                            class="pointer-events-none absolute right-0 bottom-0 left-0 h-16 rounded-b-2xl"
                            style="
                                background: linear-gradient(
                                    to bottom,
                                    transparent,
                                    rgba(255, 255, 255, 0.95)
                                );
                            "
                        />
                    </div>
                </div>

                <!-- Browser frame -->
                <div
                    v-else
                    class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-300/40"
                    style="box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12)"
                >
                    <!-- Browser chrome -->
                    <div
                        class="flex items-center gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3"
                    >
                        <div class="flex gap-1.5">
                            <div class="h-3 w-3 rounded-full bg-red-400" />
                            <div class="h-3 w-3 rounded-full bg-amber-400" />
                            <div class="h-3 w-3 rounded-full bg-green-400" />
                        </div>
                        <div
                            class="flex flex-1 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-1.5"
                        >
                            <span class="text-xs text-slate-400">🔒</span>
                            <span class="text-xs text-slate-500"
                                >nearbyweekly.co.uk/newsletter/preview</span
                            >
                        </div>
                    </div>

                    <!-- Same content, browser context -->
                    <div class="relative">
                        <div class="overflow-y-auto" style="max-height: 600px">
                            <div
                                class="px-6 py-8 sm:px-10"
                                style="background: #fdf7f4"
                            >
                                <div
                                    class="mx-auto max-w-2xl rounded-2xl bg-white px-8 py-8 shadow-sm"
                                >
                                    <div class="mb-6 flex items-center gap-3">
                                        <div
                                            class="flex h-10 w-10 items-center justify-center rounded-xl border"
                                            style="background:#F5EAE3; border-color:#E8D5C8;"
                                        >
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="#C4623A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/>
                                                <path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="font-bold" style="color: #1c1109">
                                                <span>Nearby</span> <span style="color:#C4623A">Weekly</span>
                                            </p>
                                            <p class="text-xs" style="color: #9c6b54">
                                                Weekly picks · SE1
                                            </p>
                                        </div>
                                    </div>

                                    <div
                                        class="mb-2 h-1 w-full rounded-full"
                                        style="
                                            background: linear-gradient(
                                                to right,
                                                #c4623a,
                                                #a84e2c
                                            );
                                        "
                                    />

                                    <h2
                                        class="mt-6 mb-1 font-heading text-2xl font-bold"
                                        style="color: #1c1109"
                                    >
                                        Here's what's on near SE1 this week
                                    </h2>
                                    <p
                                        class="mb-8 text-sm"
                                        style="color: #9c6b54"
                                    >
                                        6 events matched to your interests ·
                                        within 12 miles
                                    </p>

                                    <div
                                        v-for="section in previewSections"
                                        :key="section.label"
                                        class="mb-8"
                                    >
                                        <p
                                            class="mb-4 text-xs font-bold tracking-widest uppercase"
                                            style="color: #c4623a"
                                        >
                                            {{ section.label }}
                                        </p>
                                        <div
                                            class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                                        >
                                            <div
                                                v-for="event in section.events"
                                                :key="event.title"
                                                class="overflow-hidden rounded-xl bg-white shadow-sm"
                                                style="
                                                    border: 1px solid #e8d5c8;
                                                "
                                            >
                                                <div
                                                    class="relative aspect-video overflow-hidden bg-slate-100"
                                                >
                                                    <img
                                                        :src="event.img"
                                                        :alt="event.title"
                                                        class="h-full w-full object-cover"
                                                        loading="lazy"
                                                        style="
                                                            filter: saturate(
                                                                1.05
                                                            );
                                                        "
                                                    />
                                                    <div
                                                        class="absolute inset-0"
                                                        style="
                                                            background: rgba(
                                                                26,
                                                                53,
                                                                40,
                                                                0.04
                                                            );
                                                        "
                                                    />
                                                    <div
                                                        class="absolute top-3 left-3"
                                                    >
                                                        <span
                                                            class="rounded-full bg-white/90 px-2.5 py-1 text-xs font-semibold shadow-sm backdrop-blur-sm"
                                                            style="
                                                                color: #c4623a;
                                                            "
                                                        >
                                                            {{ event.emoji }}
                                                            {{ event.category }}
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="p-4">
                                                    <p
                                                        class="mb-1 font-heading leading-snug font-bold"
                                                        style="color: #1c1109"
                                                    >
                                                        {{ event.title }}
                                                    </p>
                                                    <p
                                                        class="text-sm"
                                                        style="color: #9c6b54"
                                                    >
                                                        {{ event.venue }}
                                                    </p>
                                                    <div
                                                        class="mt-2 flex items-center gap-2"
                                                    >
                                                        <span
                                                            class="text-xs"
                                                            style="
                                                                color: #9c6b54;
                                                            "
                                                            >{{
                                                                event.date
                                                            }}</span
                                                        >
                                                        <span
                                                            style="
                                                                color: #e8d5c8;
                                                            "
                                                            >·</span
                                                        >
                                                        <span
                                                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                                                            style="
                                                                background: #fdf7f4;
                                                                color: #9c6b54;
                                                            "
                                                            >📍
                                                            {{
                                                                event.dist
                                                            }}</span
                                                        >
                                                    </div>
                                                    <a
                                                        href="/start"
                                                        class="mt-3 block w-full rounded-lg py-2.5 text-center text-sm font-semibold text-white transition hover:opacity-90"
                                                        style="
                                                            background: #c4623a;
                                                        "
                                                    >
                                                        Get Tickets →
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        class="mt-8 border-t pt-6 text-center text-xs"
                                        style="
                                            border-color: #e8d5c8;
                                            color: #9c6b54;
                                        "
                                    >
                                        <p>
                                            <a
                                                href="/preferences"
                                                class="underline hover:opacity-70"
                                                >Update preferences</a
                                            >
                                            &nbsp;·&nbsp;
                                            <a
                                                href="#"
                                                class="underline hover:opacity-70"
                                                >Unsubscribe</a
                                            >
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div
                            class="pointer-events-none absolute right-0 bottom-0 left-0 h-16 rounded-b-2xl"
                            style="
                                background: linear-gradient(
                                    to bottom,
                                    transparent,
                                    rgba(255, 255, 255, 0.95)
                                );
                            "
                        />
                    </div>
                </div>

                <!-- Below-preview CTA -->
                <div class="mt-8 space-y-4 text-center">
                    <p class="font-medium" style="color: #6b4535">
                        This could be YOUR inbox 👆
                    </p>
                    <a
                        href="/start"
                        class="inline-flex items-center gap-2 rounded-2xl px-8 py-3.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-90"
                        style="background: #c4623a"
                    >
                        Build my weekly picks →
                    </a>
                </div>
            </div>
        </section>

        <!-- ───── HOW IT WORKS ───── -->
        <section class="bg-white py-20">
            <div class="mx-auto max-w-6xl px-6">
                <div class="mb-12 space-y-3 text-center">
                    <p
                        class="text-sm font-semibold tracking-widest uppercase"
                        style="color: #c4623a"
                    >
                        Simple by design
                    </p>
                    <h2
                        class="font-heading text-4xl font-bold"
                        style="color: #1c1109"
                    >
                        How it works
                    </h2>
                    <p class="mx-auto max-w-md" style="color: #6b4535">
                        (It's literally three things)
                    </p>
                </div>

                <div class="relative grid gap-8 md:grid-cols-3">
                    <div
                        v-for="(step, i) in [
                            {
                                emoji: '📍',
                                title: 'Drop your postcode',
                                body: 'We search every corner of your local area for events worth leaving the sofa for.',
                            },
                            {
                                emoji: '🎭',
                                title: 'Pick your passions',
                                body: 'Comedy? Live music? Food markets? You choose from 40+ interests. We do the matching.',
                            },
                            {
                                emoji: '📬',
                                title: 'Open your inbox',
                                body: 'Every Thursday morning, your curated picks land. Read it in 60 seconds. Act on the best ones.',
                            },
                        ]"
                        :key="i"
                        class="relative text-center"
                    >
                        <div
                            class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-2xl text-3xl shadow-sm"
                            style="background: #f5eae3"
                        >
                            {{ step.emoji }}
                        </div>
                        <h3
                            class="mb-2 font-heading text-lg font-bold"
                            style="color: #1c1109"
                        >
                            {{ step.title }}
                        </h3>
                        <p
                            class="text-sm leading-relaxed"
                            style="color: #6b4535"
                        >
                            {{ step.body }}
                        </p>
                    </div>
                </div>

                <div class="mt-12 text-center">
                    <a
                        href="/start"
                        class="inline-flex items-center gap-2 rounded-2xl px-8 py-3.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-90"
                        style="background: #c4623a"
                    >
                        Build my weekly picks →
                    </a>
                </div>
            </div>
        </section>

        <!-- ───── INTEREST SHOWCASE ───── -->
        <section class="py-20" style="background: #fdf7f4">
            <div class="mx-auto max-w-6xl px-6">
                <div class="mb-12 space-y-3 text-center">
                    <p
                        class="text-sm font-semibold tracking-widest uppercase"
                        style="color: #c4623a"
                    >
                        40+ interests
                    </p>
                    <h2
                        class="font-heading text-4xl font-bold"
                        style="color: #1c1109"
                    >
                        Whatever you're into, we've got it
                    </h2>
                    <p class="mx-auto max-w-md" style="color: #6b4535">
                        Pick as many as you like. We'll surface events that hit
                        multiple interests at once.
                    </p>
                </div>

                <div
                    class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4"
                >
                    <a
                        v-for="cat in categories"
                        :key="cat.name"
                        href="/start"
                        class="group relative overflow-hidden rounded-2xl transition-transform hover:-translate-y-1"
                        style="min-height: 160px"
                    >
                        <!-- Background photo -->
                        <img
                            :src="cat.img"
                            :alt="cat.name"
                            class="absolute inset-0 h-full w-full object-cover"
                            loading="lazy"
                        />
                        <!-- Forest green overlay -->
                        <div
                            class="absolute inset-0 transition-opacity group-hover:opacity-80"
                            style="background: rgba(196, 98, 58, 0.72)"
                        />
                        <!-- Content -->
                        <div
                            class="relative flex h-full flex-col justify-end p-4"
                            style="min-height: 160px"
                        >
                            <span class="mb-1 text-2xl">{{ cat.emoji }}</span>
                            <p
                                class="font-heading text-sm font-bold text-white"
                            >
                                {{ cat.name }}
                            </p>
                            <p
                                class="mt-0.5 text-xs leading-relaxed text-white/70"
                            >
                                {{ cat.sub }}
                            </p>
                        </div>
                    </a>
                </div>

                <p class="mt-8 text-center text-sm" style="color: #6b4535">
                    40+ interests. You pick. We match.
                </p>
            </div>
        </section>

        <!-- ───── BORROWED CREDIBILITY ───── -->
        <section class="bg-white py-20">
            <div class="mx-auto max-w-4xl px-6">
                <div class="mb-12 space-y-3 text-center">
                    <p
                        class="text-sm font-semibold tracking-widest uppercase"
                        style="color: #c4623a"
                    >
                        How it works behind the scenes
                    </p>
                    <h2
                        class="font-heading text-4xl font-bold"
                        style="color: #1c1109"
                    >
                        Thousands of events. One email worth opening.
                    </h2>
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                    <div
                        class="rounded-2xl bg-white p-6 shadow-sm"
                        style="border: 1px solid #e8d5c8"
                    >
                        <div
                            class="mb-4 flex h-10 w-10 items-center justify-center rounded-xl"
                            style="background: #fdf7f4"
                        >
                            <Database class="h-5 w-5" style="color: #c4623a" />
                        </div>
                        <h3
                            class="mb-2 font-heading font-semibold"
                            style="color: #1c1109"
                        >
                            Real event feeds
                        </h3>
                        <p
                            class="text-sm leading-relaxed"
                            style="color: #6b4535"
                        >
                            We pull from Ticketmaster, Data Thistle, and UK-wide
                            event APIs — thousands of events refreshed every
                            week.
                        </p>
                    </div>

                    <div
                        class="rounded-2xl bg-white p-6 shadow-sm"
                        style="border: 1px solid #e8d5c8"
                    >
                        <div
                            class="mb-4 flex h-10 w-10 items-center justify-center rounded-xl"
                            style="background: #fdf7f4"
                        >
                            <MapPin class="h-5 w-5" style="color: #c4623a" />
                        </div>
                        <h3
                            class="mb-2 font-heading font-semibold"
                            style="color: #1c1109"
                        >
                            Matched to your postcode
                        </h3>
                        <p
                            class="text-sm leading-relaxed"
                            style="color: #6b4535"
                        >
                            Events are scored by distance, category fit, and
                            timing. You only see what's actually near you and
                            actually on soon.
                        </p>
                    </div>

                    <div
                        class="rounded-2xl bg-white p-6 shadow-sm"
                        style="border: 1px solid #e8d5c8"
                    >
                        <div
                            class="mb-4 flex h-10 w-10 items-center justify-center rounded-xl"
                            style="background: #fdf7f4"
                        >
                            <Sparkles class="h-5 w-5" style="color: #c4623a" />
                        </div>
                        <h3
                            class="mb-2 font-heading font-semibold"
                            style="color: #1c1109"
                        >
                            No spam. No filler.
                        </h3>
                        <p
                            class="text-sm leading-relaxed"
                            style="color: #6b4535"
                        >
                            One email, every Thursday. Up to 8 events, ranked by
                            how well they match. No ads, no promoted listings,
                            no noise.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ───── FOUNDER STORY ───── -->
        <section class="py-20" style="background: #fdf7f4">
            <div class="mx-auto max-w-xl px-6 text-center">
                <p
                    class="mb-4 text-xl font-bold"
                    style="color: #c4623a"
                    aria-hidden="true"
                >
                    ◆
                </p>
                <p
                    class="mb-3 text-sm font-semibold tracking-widest uppercase"
                    style="color: #c4623a"
                >
                    Why this exists
                </p>
                <h2
                    class="mb-10 font-heading text-3xl font-bold"
                    style="color: #1c1109"
                >
                    I kept missing things happening on my doorstep.
                </h2>

                <div class="founder-story space-y-5 text-left">
                    <p>
                        I'd hear about a food market the Monday after it
                        happened. A friend would mention a comedy night I
                        would've loved — "Oh, it was last Thursday." I tried
                        Eventbrite, Ticketmaster, Instagram, local Facebook
                        groups. Every week I'd spend 20 minutes scrolling and
                        still end up on the sofa feeling like I'd missed out.
                    </p>
                    <p>
                        The events were out there. I just couldn't find them
                        without it feeling like a second job.
                    </p>
                    <p>
                        So I built Nearby Weekly. It pulls from the same feeds
                        the big ticket sites use — Ticketmaster, Data Thistle,
                        local listings — and matches them to your interests and
                        your postcode. Then it sends you one email, every
                        Thursday morning, with the stuff that's actually worth
                        leaving the house for.
                    </p>
                    <p>
                        No app to check. No algorithm to fight. Just a short
                        email with things happening near you that you'd
                        genuinely care about.
                    </p>
                    <p>That's it. I built the thing I wished existed.</p>
                </div>

                <p class="mt-8 text-sm font-medium" style="color: #9c6b54">
                    — Jake, founder
                </p>
            </div>
        </section>

        <!-- ───── FAQ ───── -->
        <section class="bg-white py-20">
            <div class="mx-auto max-w-3xl px-6">
                <div class="mb-10 space-y-3 text-center">
                    <p
                        class="text-sm font-semibold tracking-widest uppercase"
                        style="color: #c4623a"
                    >
                        Questions
                    </p>
                    <h2
                        class="font-heading text-4xl font-bold"
                        style="color: #1c1109"
                    >
                        You might be wondering...
                    </h2>
                </div>

                <div
                    class="overflow-hidden rounded-2xl"
                    style="border: 1px solid #e8d5c8"
                >
                    <div
                        v-for="(faq, i) in faqs"
                        :key="i"
                        style="border-top: 1px solid #e8d5c8"
                        :style="i === 0 ? 'border-top: none;' : ''"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center justify-between px-6 py-5 text-left transition-colors"
                            style="color: #1c1109"
                            @click="toggleFaq(i)"
                        >
                            <span class="font-medium">{{ faq.q }}</span>
                            <ChevronDown
                                class="h-4 w-4 shrink-0 transition-transform duration-200"
                                :class="{ 'rotate-180': openFaq === i }"
                                style="color: #9c6b54"
                            />
                        </button>
                        <div
                            v-show="openFaq === i"
                            class="px-6 pb-5 text-sm leading-relaxed"
                            style="color: #6b4535"
                        >
                            {{ faq.a }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ───── FINAL CTA ───── -->
        <section class="py-20" style="background: #c4623a">
            <div class="mx-auto max-w-3xl px-6 text-center">
                <div
                    class="space-y-6 rounded-3xl bg-white p-10 shadow-2xl"
                    style="box-shadow: 0 24px 64px rgba(0, 0, 0, 0.25)"
                >
                    <div class="space-y-3">
                        <h2
                            class="font-heading text-3xl font-bold sm:text-4xl"
                            style="color: #1c1109"
                        >
                            Stop searching.<br />Start discovering.
                        </h2>
                        <p class="leading-relaxed" style="color: #6b4535">
                            Your next favourite night out is already happening
                            near you. Let us find it.
                        </p>
                    </div>

                    <div class="space-y-2">
                        <div class="flex flex-col gap-3 sm:flex-row">
                            <div class="relative flex-1">
                                <MapPin
                                    class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2"
                                    style="color: #9c6b54"
                                />
                                <input
                                    v-model="footerPostcode"
                                    type="text"
                                    placeholder="Your postcode..."
                                    class="h-12 w-full rounded-2xl border bg-white pr-4 pl-10 text-sm font-medium tracking-wide uppercase transition outline-none"
                                    :disabled="footerPostcodeChecking"
                                    style="
                                        border-color: #e8d5c8;
                                        color: #1c1109;
                                    "
                                    @keyup.enter="
                                        goWithPostcode(
                                            footerPostcode,
                                            'footer',
                                        )
                                    "
                                />
                            </div>
                            <button
                                class="h-12 rounded-2xl px-6 text-sm font-semibold text-white shadow-sm transition hover:opacity-90 active:scale-95"
                                :disabled="footerPostcodeChecking"
                                style="background: #c4623a"
                                @click="
                                    goWithPostcode(
                                        footerPostcode,
                                        'footer',
                                    )
                                "
                            >
                                {{
                                    footerPostcodeChecking
                                        ? 'Checking...'
                                        : "Let's go →"
                                }}
                            </button>
                        </div>
                        <p
                            v-if="footerPostcodeError"
                            class="text-sm text-red-500"
                        >
                            {{ footerPostcodeError }}
                        </p>
                    </div>

                    <ul class="space-y-1.5 text-sm" style="color: #9c6b54">
                        <li>✓ Free forever</li>
                        <li>✓ One email per week</li>
                        <li>✓ Unsubscribe in one click</li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- ───── FOOTER ───── -->
        <footer class="bg-white px-6 py-12 text-slate-600">
            <div class="mx-auto max-w-6xl space-y-6">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-8 w-8 items-center justify-center rounded-xl" style="background:#F5EAE3; border:1px solid #E8D5C8;">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="#E8956D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/>
                            <path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/>
                        </svg>
                    </span>
                    <span class="font-heading text-lg font-bold">
                        <span class="text-slate-900">Nearby</span><span class="ml-1" style="color:#E8956D">Weekly</span>
                    </span>
                </div>
                <p class="max-w-xs text-sm leading-relaxed">
                    Weekly UK event picks, tailored before you type your email.
                </p>
                <nav class="flex flex-wrap gap-5 text-sm">
                    <a
                        href="/preferences"
                        class="transition-colors hover:text-slate-900"
                        >Preferences</a
                    >
                    <a
                        :href="login().url"
                        class="transition-colors hover:text-slate-900"
                        >Sign in</a
                    >
                    <a href="/start" class="transition-colors hover:text-slate-900"
                        >Get started</a
                    >
                </nav>
                <p class="text-xs text-slate-600">
                    Made with ☕ somewhere in the UK
                </p>
            </div>
        </footer>
    </div>
</template>

<style scoped>
/* ── Hero 3-column grid ── */
.hero-grid {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 48px 24px 32px;
    gap: 32px;
}

@media (min-width: 1024px) {
    .hero-grid {
        display: grid;
        grid-template-columns: 280px 1fr 280px;
        align-items: center;
        min-height: calc(100vh - 65px);
        padding: 40px 32px;
        gap: 16px;
    }
}

@media (min-width: 1280px) {
    .hero-grid {
        grid-template-columns: 320px 1fr 320px;
        padding: 40px 48px;
    }
}

/* ── Centre column ── */
.hero-center {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    width: 100%;
}

.hero-pill {
    display: inline-block;
    margin-bottom: 20px;
    border-radius: 99px;
    padding: 6px 16px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    background: #f5eae3;
    color: #c4623a;
}

.hero-heading {
    margin-bottom: 20px;
    font-size: clamp(2.5rem, 6vw, 4.5rem);
    font-weight: 900;
    text-transform: uppercase;
    line-height: 1;
    letter-spacing: -0.02em;
    color: #1c1109;
}

.hero-sub {
    margin-bottom: 36px;
    max-width: 380px;
    font-size: 1.05rem;
    line-height: 1.65;
    color: #6b4535;
}

/* ── CTA ── */
.hero-cta-wrap {
    width: 100%;
    max-width: 520px;
}

.hero-cta-row {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

@media (min-width: 480px) {
    .hero-cta-row {
        flex-direction: row;
    }
}

.hero-input-wrap {
    position: relative;
    flex: 1;
}

.hero-input-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    width: 16px;
    height: 16px;
    color: #9c6b54;
    pointer-events: none;
}

.hero-input {
    height: 48px;
    width: 100%;
    border-radius: 16px;
    border: 1.5px solid #e8d5c8;
    background: #ffffff;
    padding-left: 40px;
    padding-right: 16px;
    font-size: 14px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #1c1109;
    outline: none;
    transition:
        border-color 0.2s,
        box-shadow 0.2s;
}

.hero-input:focus {
    border-color: #c4623a;
    box-shadow: 0 0 0 3px rgba(196, 98, 58, 0.15);
}

.hero-btn {
    height: 48px;
    border-radius: 16px;
    padding: 0 20px;
    font-size: 14px;
    font-weight: 700;
    color: #ffffff;
    background: #c4623a;
    border: none;
    cursor: pointer;
    transition:
        background 0.2s,
        transform 0.1s;
    white-space: nowrap;
}

.hero-btn:hover {
    background: #a84e2c;
}
.hero-btn:active {
    transform: scale(0.97);
}

.hero-trust {
    margin-top: 10px;
    font-size: 12px;
    color: #9c6b54;
}

/* ── Founder story typography ── */
.founder-story p {
    font-size: 16px;
    line-height: 1.75;
    color: #6b4535;
}

/* ── Hide card columns on mobile ── */
.hero-grid > :first-child,
.hero-grid > :last-child {
    display: none;
}

@media (min-width: 1024px) {
    .hero-grid > :first-child,
    .hero-grid > :last-child {
        display: block;
    }
}

/* ── Mobile scroll strip ── */
.mobile-cards-strip {
    display: block;
    padding: 0 0 32px;
}

@media (min-width: 1024px) {
    .mobile-cards-strip {
        display: none;
    }
}

.mobile-strip-label {
    font-size: 11px;
    color: #9c6b54;
    text-align: center;
    margin-bottom: 10px;
    letter-spacing: 0.04em;
}

.mobile-strip {
    display: flex;
    gap: 12px;
    overflow-x: auto;
    padding: 4px 24px 12px;
    scroll-snap-type: x mandatory;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
}

.mobile-strip::-webkit-scrollbar {
    display: none;
}

.mobile-card {
    flex: 0 0 155px;
    background: #ffffff;
    border-radius: 14px;
    border: 1px solid #e8d5c8;
    box-shadow: 0 2px 10px rgba(196, 98, 58, 0.08);
    overflow: hidden;
    scroll-snap-align: start;
}

.mobile-photo {
    aspect-ratio: 16 / 9;
    overflow: hidden;
}

.mobile-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.mobile-card-body {
    padding: 8px 10px 10px;
}

.mobile-badge {
    display: inline-block;
    background: #f5eae3;
    color: #c4623a;
    font-size: 9px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 99px;
    margin-bottom: 4px;
}

.mobile-title {
    font-size: 11px;
    font-weight: 600;
    color: #1c1109;
    line-height: 1.3;
    margin: 0 0 2px;
}

.mobile-venue {
    font-size: 10px;
    color: #9c6b54;
    margin: 0 0 3px;
}

.mobile-dist {
    font-size: 10px;
    background: #fdf7f4;
    color: #6b4535;
    padding: 1px 6px;
    border-radius: 99px;
}
</style>
