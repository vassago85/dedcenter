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
                <h1 class="text-xl font-bold tracking-tight">
                    <span class="text-white/90">DEAD</span><span class="text-red-500">CENTER</span>
                </h1>
                <div class="ml-auto flex items-center gap-2">
                    <DeviceRoleChip />
                    <OnlineIndicator />
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-lg px-4 py-8">
            <div class="space-y-3">
                <router-link
                    :to="{ name: 'match-select' }"
                    class="flex w-full items-center justify-center gap-3 rounded-2xl bg-red-600 py-5 text-lg font-bold text-white shadow-lg transition-colors hover:bg-red-700 active:bg-red-800 active:scale-[0.98]"
                >
                    <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                    </svg>
                    Start Scoring
                </router-link>

                <button
                    v-if="continueMatchId"
                    @click="continueMatch"
                    class="flex w-full items-center justify-center gap-3 rounded-2xl border border-slate-600 bg-slate-800 py-4 font-semibold text-white transition-colors hover:bg-slate-700 active:scale-[0.98]"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8.689c0-.864.933-1.406 1.683-.977l7.108 4.061a1.125 1.125 0 0 1 0 1.954l-7.108 4.061A1.125 1.125 0 0 1 3 16.811V8.69ZM12.75 8.689c0-.864.933-1.406 1.683-.977l7.108 4.061a1.125 1.125 0 0 1 0 1.954l-7.108 4.061a1.125 1.125 0 0 1-1.683-.977V8.69Z" />
                    </svg>
                    Continue Match
                </button>

                <div class="grid grid-cols-2 gap-3">
                    <router-link
                        :to="{ name: 'season-list' }"
                        class="flex items-center justify-center gap-2 rounded-xl border border-slate-600 bg-slate-800 py-3.5 text-sm font-semibold text-slate-300 transition-colors hover:bg-slate-700"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M18.75 4.236c.982.143 1.954.317 2.916.52A6.003 6.003 0 0 1 16.27 9.728M18.75 4.236V4.5c0 2.108-.966 3.99-2.48 5.228m0 0a6.02 6.02 0 0 1-7.54 0" />
                        </svg>
                        Seasons
                    </router-link>
                    <button
                        v-if="recentMatches.length"
                        @click="goToScoreboard"
                        class="flex items-center justify-center gap-2 rounded-xl border border-slate-600 bg-slate-800 py-3.5 text-sm font-semibold text-slate-300 transition-colors hover:bg-slate-700"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5" />
                        </svg>
                        Results
                    </button>
                </div>
            </div>

            <div v-if="recentMatches.length" class="mt-8">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-400">Recent Matches</h2>
                <div class="space-y-2">
                    <router-link
                        v-for="match in recentMatches"
                        :key="match.id"
                        :to="{ name: 'match-overview', params: { matchId: match.id } }"
                        class="flex items-center gap-3 rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 transition-colors hover:bg-slate-700/80 active:scale-[0.99]"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">{{ match.name }}</p>
                            <p class="text-xs text-slate-400">
                                {{ formatDate(match.date) }}
                                <span v-if="match.location"> &middot; {{ match.location }}</span>
                            </p>
                        </div>
                        <span
                            v-if="match.scoring_type && match.scoring_type !== 'standard'"
                            class="rounded bg-red-600/15 px-2 py-0.5 text-[10px] font-bold uppercase text-red-400"
                        >
                            {{ match.scoring_type }}
                        </span>
                        <svg class="h-4 w-4 flex-shrink-0 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                        </svg>
                    </router-link>
                </div>
            </div>
        </main>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useMatchStore } from '../stores/matchStore';
import { db } from '../db/index';
import OnlineIndicator from '../components/OnlineIndicator.vue';
import DeviceRoleChip from '../components/DeviceRoleChip.vue';

const LAST_MATCH_KEY = 'dc_last_match_id';
const SQUAD_LOCK_KEY = 'dc_locked_squad';
const STAGE_LOCK_KEY = 'dc_locked_stage';

const router = useRouter();
const matchStore = useMatchStore();
const recentMatches = ref([]);

const continueMatchId = computed(() => {
    try {
        const squad = localStorage.getItem(SQUAD_LOCK_KEY);
        if (squad) return JSON.parse(squad).matchId;
        const stage = localStorage.getItem(STAGE_LOCK_KEY);
        if (stage) return JSON.parse(stage).matchId;
    } catch { /* ignore */ }
    const last = localStorage.getItem(LAST_MATCH_KEY);
    return last ? Number(last) : null;
});

function continueMatch() {
    if (continueMatchId.value) {
        router.push({ name: 'match-overview', params: { matchId: continueMatchId.value } });
    }
}

function goToScoreboard() {
    const id = recentMatches.value[0]?.id;
    if (id) {
        router.push({ name: 'scoreboard', params: { matchId: id } });
    }
}

function formatDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('en-ZA', { day: 'numeric', month: 'short', year: 'numeric' });
}

onMounted(async () => {
    try {
        const cached = await db.matches.toArray();
        recentMatches.value = cached
            .sort((a, b) => (b.date ?? '').localeCompare(a.date ?? ''))
            .slice(0, 5);
    } catch { /* no cache */ }

    const hasLock = !!localStorage.getItem(SQUAD_LOCK_KEY) || !!localStorage.getItem(STAGE_LOCK_KEY);
    if (hasLock && continueMatchId.value) {
        router.replace({ name: 'match-overview', params: { matchId: continueMatchId.value } });
    }
});
</script>
