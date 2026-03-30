<script setup lang="ts">
import { Inbox } from 'lucide-vue-next';
import { computed } from 'vue';
import BucketSection from './BucketSection.vue';

type NewsletterEvent = {
    id: number;
    title: string;
    category: string | null;
    city: string | null;
    venue_name: string | null;
    starts_at: string | null;
    url: string;
    score: number;
    image_url: string | null;
};

type NewsletterRun = {
    id: number;
    sent_at: string | null;
    events: NewsletterEvent[];
};

const props = defineProps<{
    run: NewsletterRun | null;
    issueNumber: number;
    totalIssues: number;
    hasPrev: boolean;
    hasNext: boolean;
}>();

const emit = defineEmits<{
    prev: [];
    next: [];
}>();

const buckets = computed(() => {
    if (!props.run) return {};

    const sent = props.run.sent_at ? new Date(props.run.sent_at) : new Date();
    const MS_DAY = 86400000;

    const result: Record<string, NewsletterEvent[]> = {
        weekend: [],
        week: [],
        month: [],
        coming_up: [],
    };

    for (const event of props.run.events) {
        if (!event.starts_at) { result.coming_up.push(event); continue; }
        const start = new Date(event.starts_at);
        const diffDays = (start.getTime() - sent.getTime()) / MS_DAY;
        const dow = start.getDay(); // 0=Sun, 6=Sat

        if (diffDays <= 7 && (dow === 0 || dow === 6)) {
            result.weekend.push(event);
        } else if (diffDays <= 7) {
            result.week.push(event);
        } else if (diffDays <= 31) {
            result.month.push(event);
        } else {
            result.coming_up.push(event);
        }
    }

    // Remove empty buckets
    return Object.fromEntries(Object.entries(result).filter(([, v]) => v.length > 0));
});

const bucketConfig: Record<string, { label: string; emoji: string }> = {
    weekend:   { label: 'This Weekend', emoji: '🎵' },
    week:      { label: 'This Week',    emoji: '📅' },
    month:     { label: 'This Month',   emoji: '🗓️' },
    coming_up: { label: 'Coming Up',    emoji: '🔮' },
};

function formatDay(iso: string | null): string {
    if (!iso) return '—';
    return new Intl.DateTimeFormat('en-GB', { day: 'numeric' }).format(new Date(iso));
}

function formatDayName(iso: string | null): string {
    if (!iso) return '';
    return new Intl.DateTimeFormat('en-GB', { weekday: 'long' }).format(new Date(iso));
}

function formatMonthYear(iso: string | null): string {
    if (!iso) return '';
    return new Intl.DateTimeFormat('en-GB', { month: 'long', year: 'numeric' }).format(new Date(iso));
}

function relativeTime(iso: string | null): string {
    if (!iso) return '';
    const diffMs = Date.now() - new Date(iso).getTime();
    const hours = Math.floor(diffMs / 3600000);
    const days = Math.floor(diffMs / 86400000);
    if (hours < 1) return 'just now';
    if (hours < 24) return `${hours}h ago`;
    return `${days} days ago`;
}
</script>

<template>
    <!-- No issue selected -->
    <div v-if="!run" class="flex flex-1 flex-col items-center justify-center gap-4 p-8 text-center">
        <div class="flex h-20 w-20 items-center justify-center rounded-full bg-slate-100">
            <Inbox class="h-10 w-10 text-slate-300" />
        </div>
        <div class="space-y-1">
            <p class="font-heading text-lg font-semibold text-slate-700">Your weekly picks will appear here</p>
            <p class="text-sm text-slate-400">Events are curated every Thursday morning.</p>
        </div>
    </div>

    <!-- Issue view -->
    <div v-else class="flex-1 overflow-y-auto">
        <div class="mx-auto max-w-3xl px-6 py-8">

            <!-- Date header -->
            <div class="mb-2">
                <span class="font-heading text-6xl font-bold text-slate-900">{{ formatDay(run.sent_at) }}</span>
            </div>
            <div class="mb-1">
                <span class="text-xl font-semibold text-[#C4623A]">{{ formatDayName(run.sent_at) }}</span>
            </div>
            <div class="mb-4">
                <span class="text-xl text-slate-400">{{ formatMonthYear(run.sent_at) }}</span>
            </div>

            <!-- Meta row -->
            <p class="mb-4 text-xs text-slate-400">
                Issue #{{ issueNumber }} · {{ run.events.length }} {{ run.events.length === 1 ? 'event' : 'events' }} · Sent {{ relativeTime(run.sent_at) }}
            </p>

            <!-- Issue nav -->
            <div class="mb-8 flex items-center gap-2">
                <button
                    type="button"
                    class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                    :disabled="!hasPrev"
                    @click="emit('prev')"
                >
                    ← Previous
                </button>
                <button
                    type="button"
                    class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                    :disabled="!hasNext"
                    @click="emit('next')"
                >
                    Next →
                </button>
            </div>

            <!-- Bucketed event sections -->
            <div v-if="Object.keys(buckets).length" class="space-y-8">
                <BucketSection
                    v-for="(events, key) in buckets"
                    :key="key"
                    :label="bucketConfig[key]?.label ?? String(key)"
                    :emoji="bucketConfig[key]?.emoji ?? '📌'"
                    :events="events"
                />
            </div>

            <!-- No events in run -->
            <div v-else class="rounded-2xl border border-dashed border-slate-200 py-12 text-center">
                <p class="text-sm text-slate-400">No events were matched for this issue.</p>
            </div>

        </div>
    </div>
</template>
