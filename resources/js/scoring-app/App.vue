<template>
    <div v-if="userStore.loading && !userStore.loaded" class="flex min-h-screen items-center justify-center bg-slate-900">
        <div class="flex flex-col items-center gap-3">
            <svg class="h-8 w-8 animate-spin text-red-500" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            <span class="text-sm text-slate-400">Loading...</span>
        </div>
    </div>
    <router-view v-else />

    <!--
      Theme toggle. Fixed, so every scoring view shows it in the
      same spot — bottom right so it stays well clear of the match
      header buttons on every screen. Tapping flips dark ⇄ light
      and the choice persists per device.
    -->
    <button
        type="button"
        class="fixed bottom-4 right-4 z-50 flex h-11 w-11 items-center justify-center rounded-full border border-slate-700 bg-slate-800 text-slate-200 shadow-lg transition-colors hover:border-sky-500 hover:text-white active:scale-95"
        :aria-label="currentTheme === 'light' ? 'Switch to dark mode' : 'Switch to light mode'"
        :title="currentTheme === 'light' ? 'Switch to dark mode' : 'Switch to light mode'"
        @click="toggleTheme"
    >
        <!-- Sun (shown in dark mode — tap to go light) -->
        <svg v-if="currentTheme === 'dark'" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="4"/>
            <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>
        </svg>
        <!-- Moon (shown in light mode — tap to go dark) -->
        <svg v-else class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
        </svg>
    </button>
</template>

<script setup>
import { useUserStore } from './stores/userStore';
import { useTheme } from './composables/useTheme';

const userStore = useUserStore();
userStore.fetchUser();

const { currentTheme, toggleTheme } = useTheme();
</script>
