<template>
    <div class="min-h-screen bg-slate-900 text-white">
        <header class="border-b border-slate-700 bg-slate-800 px-4 py-4">
            <div class="mx-auto flex max-w-lg items-center gap-3">
                <router-link :to="{ name: 'match-select' }" class="text-slate-400 hover:text-white">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </router-link>
                <h1 class="text-lg font-bold truncate">{{ matchStore.currentMatch?.name ?? 'Loading...' }}</h1>
                <div class="ml-auto"><OnlineIndicator /></div>
            </div>
        </header>

        <main class="mx-auto max-w-lg px-4 py-6">
            <div v-if="matchStore.loading" class="flex justify-center py-12">
                <div class="h-8 w-8 animate-spin rounded-full border-2 border-slate-600 border-t-red-500"></div>
            </div>

            <template v-else-if="matchStore.currentMatch">
                <div class="space-y-4">
                    <!-- Match info -->
                    <div class="rounded-xl border border-slate-700 bg-slate-800 p-4">
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <span class="text-slate-400">Date</span>
                                <p class="font-medium">{{ formatDate(matchStore.currentMatch.date) }}</p>
                            </div>
                            <div>
                                <span class="text-slate-400">Location</span>
                                <p class="font-medium">{{ matchStore.currentMatch.location || '—' }}</p>
                            </div>
                            <div>
                                <span class="text-slate-400">Stages</span>
                                <p class="font-medium">{{ matchStore.targetSets.length }}</p>
                            </div>
                            <div>
                                <span class="text-slate-400">Shooters</span>
                                <p class="font-medium">{{ matchStore.allShooters.length }}</p>
                            </div>
                            <div v-if="matchStore.currentMatch.scoring_type === 'prs'" class="col-span-2">
                                <span class="text-slate-400">Scoring</span>
                                <p class="font-medium"><span class="rounded bg-amber-600 px-1.5 py-0.5 text-xs font-bold uppercase">PRS</span> Hit/Miss + Stage Times</p>
                            </div>
                        </div>
                    </div>

                    <!-- Target sets summary -->
                    <div class="rounded-xl border border-slate-700 bg-slate-800 p-4">
                        <h3 class="mb-3 text-sm font-semibold text-slate-400 uppercase tracking-wider">Target Sets</h3>
                        <div class="space-y-2">
                            <div
                                v-for="ts in matchStore.targetSets"
                                :key="ts.id"
                                class="flex items-center justify-between rounded-lg bg-slate-700/40 px-3 py-2 text-sm"
                            >
                                <span class="font-medium">{{ ts.label }}</span>
                                <span class="text-slate-400">{{ ts.distance_meters }}m &middot; {{ ts.gongs.length }} gongs</span>
                            </div>
                        </div>
                    </div>

                    <!-- Squads summary -->
                    <div class="rounded-xl border border-slate-700 bg-slate-800 p-4">
                        <h3 class="mb-3 text-sm font-semibold text-slate-400 uppercase tracking-wider">Squads</h3>
                        <div class="space-y-2">
                            <div
                                v-for="squad in matchStore.squads"
                                :key="squad.id"
                                class="flex items-center justify-between rounded-lg bg-slate-700/40 px-3 py-2 text-sm"
                            >
                                <span class="font-medium">{{ squad.name }}</span>
                                <span class="text-slate-400">{{ squad.shooters.length }} shooters</span>
                            </div>
                        </div>
                    </div>

                    <!-- Action buttons -->
                    <div class="grid grid-cols-1 gap-3 pt-2">
                        <!-- Scoring Matrix (primary for round-robin) -->
                        <router-link
                            :to="{ name: 'scoring-matrix', params: { matchId: props.matchId } }"
                            class="flex items-center justify-center gap-2 rounded-xl bg-red-600 py-4 text-lg font-bold text-white shadow-lg transition-colors hover:bg-red-700 active:bg-red-800"
                        >
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0 1 12 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h-7.5c-.621 0-1.125.504-1.125 1.125m8.625-1.125c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125M12 10.875v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 10.875c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125M10.875 12h-7.5m8.625 0h7.5m-8.625 0c.621 0 1.125.504 1.125 1.125" />
                            </svg>
                            Scoring Matrix
                        </router-link>

                        <template v-if="matchStore.hasSquadLock">
                            <router-link
                                :to="{ name: 'scoring', params: { matchId: props.matchId } }"
                                class="flex items-center justify-center gap-2 rounded-xl border border-slate-600 bg-slate-800 py-3 font-semibold text-white transition-colors hover:bg-slate-700"
                            >
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                                </svg>
                                Continue Scoring &mdash; {{ matchStore.lockedSquadName }}
                            </router-link>
                            <router-link
                                :to="{ name: 'squad-select', params: { matchId: props.matchId } }"
                                class="flex items-center justify-center gap-2 rounded-xl border border-amber-700 bg-amber-900/20 py-3 font-semibold text-amber-300 transition-colors hover:bg-amber-900/40"
                            >
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                                </svg>
                                Change Squad
                            </router-link>
                        </template>
                        <template v-else>
                            <router-link
                                :to="{ name: 'squad-select', params: { matchId: props.matchId } }"
                                class="flex items-center justify-center gap-2 rounded-xl border border-slate-600 bg-slate-800 py-3 font-semibold text-white transition-colors hover:bg-slate-700"
                            >
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                                </svg>
                                Start Scoring
                            </router-link>
                        </template>
                        <router-link
                            :to="{ name: 'scoreboard', params: { matchId: props.matchId } }"
                            class="flex items-center justify-center gap-2 rounded-xl border border-slate-600 bg-slate-800 py-3 font-semibold text-white transition-colors hover:bg-slate-700"
                        >
                            View Scoreboard
                        </router-link>
                    </div>
                </div>
            </template>
        </main>
    </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useMatchStore } from '../stores/matchStore';
import OnlineIndicator from '../components/OnlineIndicator.vue';

const props = defineProps({
    matchId: { type: Number, required: true },
});

const matchStore = useMatchStore();

function formatDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('en-ZA', { day: 'numeric', month: 'short', year: 'numeric' });
}

onMounted(() => {
    matchStore.fetchMatch(props.matchId);
});
</script>
