<template>
    <div class="min-h-screen bg-slate-900 text-white">
        <header class="border-b border-slate-700 bg-slate-800 px-4 py-4">
            <div class="mx-auto flex max-w-lg items-center gap-3">
                <svg class="h-5 w-5 text-red-500" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="2.5" fill="currentColor"/>
                    <line x1="12" y1="3" x2="12" y2="7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    <line x1="12" y1="17" x2="12" y2="21" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    <line x1="3" y1="12" x2="7" y2="12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    <line x1="17" y1="12" x2="21" y2="12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
                <h1 class="text-xl font-bold tracking-tight"><span class="text-white/90">DEAD</span><span class="text-red-500">CENTER</span></h1>
            </div>
        </header>

        <main class="mx-auto max-w-lg px-4 py-8">
            <div class="mb-6 flex items-center justify-between">
                <h2 class="text-lg font-semibold">Select Match</h2>
                <OnlineIndicator />
            </div>

            <div v-if="matchStore.loading" class="flex justify-center py-12">
                <div class="h-8 w-8 animate-spin rounded-full border-2 border-slate-600 border-t-red-500"></div>
            </div>

            <div v-else-if="matchStore.error" class="rounded-xl border border-red-800 bg-red-900/30 p-4 text-center">
                <p class="text-red-300">{{ matchStore.error }}</p>
                <button @click="matchStore.fetchMatches()" class="mt-3 text-sm font-medium text-red-400 underline">
                    Try Again
                </button>
            </div>

            <div v-else-if="!matchStore.matches.length" class="rounded-xl border border-slate-700 bg-slate-800 p-8 text-center">
                <p class="text-slate-400">No active matches available.</p>
            </div>

            <div v-else class="space-y-3">
                <router-link
                    v-for="match in matchStore.matches"
                    :key="match.id"
                    :to="{ name: 'match-overview', params: { matchId: match.id } }"
                    class="block rounded-xl border border-slate-700 bg-slate-800 p-4 transition-colors hover:border-red-600 hover:bg-slate-700/50 active:bg-slate-700"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="font-semibold text-white">{{ match.name }}</h3>
                            <p class="mt-1 text-sm text-slate-400">{{ match.location }}</p>
                        </div>
                        <span class="rounded-full bg-green-600/20 px-2.5 py-0.5 text-xs font-medium text-green-400">
                            Active
                        </span>
                    </div>
                    <p class="mt-2 text-sm text-slate-500">{{ formatDate(match.date) }}</p>
                </router-link>
            </div>
        </main>
    </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useMatchStore } from '../stores/matchStore';
import OnlineIndicator from '../components/OnlineIndicator.vue';

const matchStore = useMatchStore();

function formatDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('en-ZA', { day: 'numeric', month: 'short', year: 'numeric' });
}

onMounted(() => {
    matchStore.fetchMatches();
});
</script>
