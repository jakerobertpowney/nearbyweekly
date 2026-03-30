<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { PanelLeft, Sparkles, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import IssueList from '@/components/Newsletter/IssueList.vue';
import IssueView from '@/components/Newsletter/IssueView.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { edit as editPreferences } from '@/routes/preferences';

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
    preferencesComplete: boolean;
    latestRun: NewsletterRun | null;
    archiveRuns: NewsletterRun[];
}>();

// All runs in order newest → oldest
const allRuns = computed<NewsletterRun[]>(() => [
    ...(props.latestRun ? [props.latestRun] : []),
    ...props.archiveRuns,
]);

const selectedRunId = ref<number | null>(props.latestRun?.id ?? null);

const selectedRun = computed(() =>
    allRuns.value.find((r) => r.id === selectedRunId.value) ?? null,
);

const selectedIndex = computed(() =>
    allRuns.value.findIndex((r) => r.id === selectedRunId.value),
);

// Issue #N — oldest is #1, newest is #total
const issueNumber = computed(() =>
    allRuns.value.length - selectedIndex.value,
);

function selectRun(id: number): void {
    selectedRunId.value = id;
    panelOpen.value = false;
}

function goPrev(): void {
    const i = selectedIndex.value;
    if (i < allRuns.value.length - 1) selectedRunId.value = allRuns.value[i + 1].id;
}

function goNext(): void {
    const i = selectedIndex.value;
    if (i > 0) selectedRunId.value = allRuns.value[i - 1].id;
}

// Mobile drawer state
const panelOpen = ref(false);
</script>

<template>
    <Head title="My Picks" />

    <AppLayout>
        <!-- Preferences incomplete banner -->
        <div v-if="!preferencesComplete" class="border-b border-[#E8D5C8] bg-[#FDF7F4] px-4 py-2.5">
            <Alert class="border-0 bg-transparent p-0 text-[#1C1109] shadow-none">
                <Sparkles class="h-4 w-4 text-[#C4623A]" />
                <AlertTitle class="text-sm">Preferences incomplete</AlertTitle>
                <AlertDescription class="text-xs">
                    <Link :href="editPreferences().url" class="underline underline-offset-2">Add your postcode and interests</Link>
                    so we can start matching events for your next picks.
                </AlertDescription>
            </Alert>
        </div>

        <!-- Three-zone body -->
        <div class="flex flex-1 overflow-hidden" style="height: calc(100vh - 3.5rem);">

            <!-- Mobile: drawer backdrop -->
            <div
                v-if="panelOpen"
                class="fixed inset-0 z-30 bg-black/20 md:hidden"
                @click="panelOpen = false"
            />

            <!-- Left panel: issue list -->
            <div
                class="flex-shrink-0 transition-transform duration-200 md:relative md:translate-x-0 md:flex"
                :class="panelOpen
                    ? 'fixed inset-y-14 left-0 z-40 flex'
                    : 'hidden md:flex'"
            >
                <IssueList
                    :runs="allRuns"
                    :selected-run-id="selectedRunId"
                    @select="selectRun"
                />
            </div>

            <!-- Main reading pane -->
            <main class="flex flex-1 flex-col overflow-hidden">
                <!-- Mobile top bar: toggle panel + "All edits" -->
                <div class="flex items-center border-b border-slate-100 px-4 py-2 md:hidden">
                    <button
                        type="button"
                        class="flex items-center gap-2 text-sm font-medium text-slate-600"
                        @click="panelOpen = !panelOpen"
                    >
                        <PanelLeft class="h-4 w-4" />
                        All picks
                    </button>
                </div>

                <IssueView
                    :run="selectedRun"
                    :issue-number="issueNumber"
                    :total-issues="allRuns.length"
                    :has-prev="selectedIndex < allRuns.length - 1"
                    :has-next="selectedIndex > 0"
                    @prev="goPrev"
                    @next="goNext"
                />
            </main>
        </div>
    </AppLayout>
</template>
