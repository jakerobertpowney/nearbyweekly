<script setup lang="ts">
import { CalendarDays, MapPin } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

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

defineProps<{ event: NewsletterEvent }>();

const emojiMap: Record<string, string> = {
    'concerts':          '🎵',
    'sports':            '⚽',
    'comedy':            '😂',
    'food-and-drink':    '🍽️',
    'tech':              '💻',
    'family-days-out':   '👨‍👩‍👧',
    'markets':           '🛍️',
    'wellness':          '🧘',
    'hiking':            '🌿',
    'theatre':           '🎭',
    'festivals':         '🎪',
    'farming-and-rural': '🌾',
    'arts-and-culture':  '🎨',
};

function formatEventDate(iso: string | null): string {
    if (!iso) return '—';
    return new Intl.DateTimeFormat('en-GB', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(iso));
}
</script>

<template>
    <Card class="group flex flex-col gap-0 overflow-hidden py-0 transition-all hover:border-[#E8D5C8] hover:shadow-sm">
        <!-- Image / placeholder -->
        <div v-if="event.image_url" class="h-36 w-full overflow-hidden bg-slate-100">
            <img
                :src="event.image_url"
                :alt="event.title"
                class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
            />
        </div>
        <div v-else class="flex h-28 items-center justify-center bg-gradient-to-br from-[#FDF7F4] to-[#F5EAE3]">
            <span class="text-3xl opacity-50">{{ emojiMap[event.category ?? ''] ?? '🎟️' }}</span>
        </div>

        <CardHeader class="px-4 pt-4 pb-2">
            <div class="flex flex-wrap gap-1.5">
                <Badge
                    v-if="event.category"
                    variant="secondary"
                    class="rounded-full text-xs"
                >
                    {{ emojiMap[event.category] ?? '🎟️' }} {{ event.category.replace(/-/g, ' ') }}
                </Badge>
                <Badge
                    v-if="event.city"
                    variant="outline"
                    class="rounded-full text-xs"
                >{{ event.city }}</Badge>
            </div>
            <CardTitle class="mt-2 line-clamp-2 text-base leading-snug text-slate-900">
                {{ event.title }}
            </CardTitle>
        </CardHeader>

        <CardContent class="flex-1 px-4 pb-3">
            <div class="space-y-1 text-xs text-slate-500">
                <div v-if="event.starts_at" class="flex items-center gap-1.5">
                    <CalendarDays class="h-3.5 w-3.5 flex-shrink-0" />
                    <span>{{ formatEventDate(event.starts_at) }}</span>
                </div>
                <div v-if="event.venue_name" class="flex items-center gap-1.5">
                    <MapPin class="h-3.5 w-3.5 flex-shrink-0" />
                    <span class="truncate">{{ event.venue_name }}</span>
                </div>
            </div>
        </CardContent>

        <CardFooter class="px-4 pb-4 pt-0">
            <Button as-child class="w-full rounded-xl bg-[#FDF7F4]0 text-sm text-white hover:bg-[#A84E2C]">
                <a :href="`/events/${event.id}/go`" target="_blank" rel="noopener noreferrer">
                    Get tickets →
                </a>
            </Button>
        </CardFooter>
    </Card>
</template>
