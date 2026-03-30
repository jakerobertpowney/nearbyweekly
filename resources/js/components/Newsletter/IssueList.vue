<script setup lang="ts">
import { Inbox } from 'lucide-vue-next';
import { computed } from 'vue';

type NewsletterRun = {
    id: number;
    sent_at: string | null;
    events: { id: number }[];
};

const props = defineProps<{
    runs: NewsletterRun[];
    selectedRunId: number | null;
}>();

const emit = defineEmits<{
    select: [id: number];
}>();

function relativeTime(iso: string | null): string {
    if (!iso) return '';
    const diffMs = Date.now() - new Date(iso).getTime();
    const hours = Math.floor(diffMs / 3600000);
    const days = Math.floor(diffMs / 86400000);
    if (hours < 1) return 'Just now';
    if (hours < 24) return `${hours}h ago`;
    if (days < 14) return `${days} days ago`;
    return new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short' }).format(new Date(iso));
}

function formatDate(iso: string | null): string {
    if (!iso) return '—';
    return new Intl.DateTimeFormat('en-GB', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(new Date(iso));
}
</script>

<template>
    <aside class="flex w-64 flex-shrink-0 flex-col border-r border-slate-100 bg-slate-50">
        <!-- Panel header -->
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
            <span class="font-heading text-sm font-semibold text-slate-700">Your picks</span>
            <span class="text-xs text-slate-400">{{ runs.length }}</span>
        </div>

        <!-- Empty state -->
        <div v-if="runs.length === 0" class="flex flex-1 flex-col items-center justify-center gap-3 px-4 py-8 text-center">
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100">
                <Inbox class="h-6 w-6 text-slate-400" />
            </div>
            <div class="space-y-1">
                <p class="text-sm font-medium text-slate-700">No picks sent yet</p>
                <p class="text-xs text-slate-400">Your first weekly picks will appear here.</p>
            </div>
        </div>

        <!-- Issue list -->
        <nav v-else class="flex-1 overflow-y-auto">
            <button
                v-for="run in runs"
                :key="run.id"
                type="button"
                class="w-full border-l-2 px-4 py-3 text-left transition-colors"
                :class="run.id === selectedRunId
                    ? 'border-[#C4623A] bg-white'
                    : 'border-transparent hover:bg-white'"
                @click="emit('select', run.id)"
            >
                <p class="text-sm font-medium text-slate-900">{{ formatDate(run.sent_at) }}</p>
                <p class="mt-0.5 text-xs text-slate-400">
                    {{ relativeTime(run.sent_at) }} · {{ run.events.length }} {{ run.events.length === 1 ? 'pick' : 'picks' }}
                </p>
            </button>
        </nav>
    </aside>
</template>
