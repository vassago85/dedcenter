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
                <div
                    v-for="match in matchStore.matches"
                    :key="match.id"
                    class="rounded-xl border bg-slate-800 transition-colors"
                    :class="matchStore.isMatchCached(match.id)
                        ? 'border-green-700/50'
                        : 'border-slate-700'"
                >
                    <router-link
                        :to="{ name: 'match-overview', params: { matchId: match.id } }"
                        class="block p-4 transition-colors hover:bg-slate-700/50 active:bg-slate-700 rounded-t-xl"
                    >
                        <div class="flex items-start justify-between">
                            <div class="min-w-0 flex-1">
                                <h3 class="font-semibold text-white">{{ match.name }}</h3>
                                <p class="mt-1 text-sm text-slate-400">{{ match.location }}</p>
                            </div>
                            <span
                                v-if="match.status === 'completed'"
                                class="ml-2 shrink-0 rounded-full bg-slate-600/30 px-2.5 py-0.5 text-xs font-medium text-slate-300"
                                title="Match already scored"
                            >
                                Completed
                            </span>
                            <span
                                v-else-if="match.status === 'ready'"
                                class="ml-2 shrink-0 rounded-full bg-emerald-600/20 px-2.5 py-0.5 text-xs font-medium text-emerald-400"
                            >
                                Ready
                            </span>
                            <span
                                v-else
                                class="ml-2 shrink-0 rounded-full bg-green-600/20 px-2.5 py-0.5 text-xs font-medium text-green-400"
                            >
                                Active
                            </span>
                        </div>
                        <p class="mt-2 text-sm text-slate-500">{{ formatDate(match.date) }}</p>
                    </router-link>

                    <div class="flex items-center justify-between border-t border-slate-700/50 px-4 py-2.5">
                        <div v-if="matchStore.isMatchCached(match.id)" class="flex items-center gap-1.5">
                            <svg class="h-4 w-4 text-green-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="text-xs font-medium text-green-400">Offline Ready</span>
                        </div>
                        <div v-else-if="matchStore.cachingMatchId === match.id" class="flex items-center gap-1.5">
                            <div class="h-4 w-4 animate-spin rounded-full border-2 border-slate-600 border-t-amber-400"></div>
                            <span class="text-xs font-medium text-amber-400">Caching...</span>
                        </div>
                        <div v-else-if="cacheError === match.id" class="flex items-center gap-1.5">
                            <svg class="h-4 w-4 text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                            </svg>
                            <span class="text-xs font-medium text-red-400">Failed</span>
                        </div>
                        <div v-else class="flex items-center gap-1.5 text-slate-500">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z" />
                            </svg>
                            <span class="text-xs">Not cached</span>
                        </div>

                        <button
                            v-if="matchStore.isMatchCached(match.id)"
                            @click.prevent="clearCache(match.id)"
                            class="flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-medium text-slate-400 transition-colors hover:bg-slate-700 hover:text-red-400"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                            </svg>
                            Clear
                        </button>
                        <button
                            v-else
                            @click.prevent="downloadMatch(match.id)"
                            :disabled="matchStore.cachingMatchId !== null"
                            class="flex items-center gap-1 rounded-lg bg-slate-700 px-2.5 py-1.5 text-xs font-medium text-white transition-colors hover:bg-red-600 disabled:opacity-40 disabled:cursor-not-allowed"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                            Download
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useMatchStore } from '../stores/matchStore';
import OnlineIndicator from '../components/OnlineIndicator.vue';

const matchStore = useMatchStore();
const cacheError = ref(null);

function formatDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('en-ZA', { day: 'numeric', month: 'short', year: 'numeric' });
}

async function downloadMatch(matchId) {
    cacheError.value = null;
    try {
        await matchStore.cacheMatchForOffline(matchId);
    } catch {
        cacheError.value = matchId;
    }
}

async function clearCache(matchId) {
    await matchStore.clearMatchCache(matchId);
}

onMounted(async () => {
    await matchStore.fetchMatches();
    await matchStore.checkCachedMatches();
});
</script>
