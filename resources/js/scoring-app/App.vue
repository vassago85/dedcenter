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
    <template v-else>
        <router-view />
        <!--
            Global "back to DeadCenter" escape hatch. Renders as a floating
            pill in the top-left corner so it never fights with a per-view
            header. Hidden on the Android standalone APK — see
            `ExitScoringAppLink.vue`.
        -->
        <ExitScoringAppLink />
    </template>
</template>

<script setup>
import { useUserStore } from './stores/userStore';
import ExitScoringAppLink from './components/ExitScoringAppLink.vue';

const userStore = useUserStore();
userStore.fetchUser();
</script>
