<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { LogOut, Newspaper, Settings, SlidersHorizontal } from 'lucide-vue-next';
import { ref } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { dashboard } from '@/routes';
import { edit as editPreferences } from '@/routes/preferences';
import { edit as editSettings } from '@/routes/profile';

const page = usePage();
const dropdownOpen = ref(false);

function signOut(): void {
    router.post('/logout');
}

function toggleDropdown(): void {
    dropdownOpen.value = !dropdownOpen.value;
}

function closeDropdown(): void {
    dropdownOpen.value = false;
}

const userInitial = (): string => {
    const email = page.props.auth?.user?.email ?? '';
    return email.charAt(0).toUpperCase();
};
</script>

<template>
    <header class="fixed inset-x-0 top-0 z-50 flex h-14 items-center border-b border-slate-100 bg-white px-4 md:px-6">
        <!-- Logo -->
        <Link :href="dashboard().url" class="flex-shrink-0">
            <AppLogoIcon class="size-7 fill-current text-[#C4623A] sm:hidden" />
            <span class="hidden sm:block"><AppLogo /></span>
        </Link>

        <!-- Centre nav tabs -->
        <nav class="mx-auto flex items-center gap-1">
            <Link
                :href="dashboard().url"
                class="relative flex h-14 items-center gap-2 px-4 text-sm font-medium transition-colors"
                :class="$page.url.startsWith('/dashboard')
                    ? 'border-b-2 border-[#C4623A] text-slate-900'
                    : 'border-b-2 border-transparent text-slate-500 hover:text-slate-700'"
            >
                <Newspaper class="h-4 w-4" />
                <span class="hidden sm:inline">My Picks</span>
            </Link>
            <Link
                :href="editPreferences().url"
                class="relative flex h-14 items-center gap-2 px-4 text-sm font-medium transition-colors"
                :class="$page.url.startsWith('/preferences')
                    ? 'border-b-2 border-[#C4623A] text-slate-900'
                    : 'border-b-2 border-transparent text-slate-500 hover:text-slate-700'"
            >
                <SlidersHorizontal class="h-4 w-4" />
                <span class="hidden sm:inline">Preferences</span>
            </Link>
        </nav>

        <!-- Right: avatar + dropdown -->
        <div class="relative flex-shrink-0">
            <button
                type="button"
                class="flex h-8 w-8 items-center justify-center rounded-full bg-[#F5EAE3] text-sm font-semibold text-[#6B4535] transition hover:bg-[#F5EAE3]"
                @click="toggleDropdown"
            >
                {{ userInitial() }}
            </button>

            <!-- Dropdown -->
            <div
                v-if="dropdownOpen"
                class="absolute right-0 top-10 z-50 w-48 overflow-hidden rounded-xl border border-slate-100 bg-white shadow-lg"
            >
                <!-- User email -->
                <div class="border-b border-slate-100 px-4 py-3">
                    <p class="truncate text-xs text-slate-400">{{ page.props.auth?.user?.email }}</p>
                </div>

                <!-- Settings link -->
                <Link
                    :href="editSettings().url"
                    class="flex w-full items-center gap-2 px-4 py-3 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                    @click="closeDropdown"
                >
                    <Settings class="h-4 w-4 text-slate-400" />
                    Settings
                </Link>

                <!-- Divider -->
                <div class="border-t border-slate-100" />

                <!-- Sign out -->
                <button
                    type="button"
                    class="flex w-full items-center gap-2 px-4 py-3 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                    @click="signOut"
                >
                    <LogOut class="h-4 w-4 text-slate-400" />
                    Sign out
                </button>
            </div>

            <!-- Click-outside overlay -->
            <div
                v-if="dropdownOpen"
                class="fixed inset-0 z-40"
                @click="closeDropdown"
            />
        </div>
    </header>
</template>
