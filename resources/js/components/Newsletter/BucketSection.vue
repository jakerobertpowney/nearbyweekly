<script setup lang="ts">
import EventCard from './EventCard.vue';

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

defineProps<{
    label: string;
    emoji: string;
    events: NewsletterEvent[];
}>();
</script>

<template>
    <div class="space-y-4">
        <!-- Section header with rule -->
        <div class="flex items-center gap-3">
            <span class="font-heading whitespace-nowrap text-xs font-semibold tracking-widest text-[#C4623A] uppercase">
                {{ emoji }} {{ label }}
            </span>
            <div class="h-px flex-1 bg-slate-100" />
        </div>

        <!-- Event grid -->
        <div class="grid gap-3 sm:grid-cols-2">
            <EventCard v-for="event in events" :key="event.id" :event="event" />
        </div>
    </div>
</template>
