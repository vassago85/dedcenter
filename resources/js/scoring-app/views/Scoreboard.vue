<template>
    <div class="min-h-screen bg-slate-900 text-white">
        <header class="border-b border-slate-700 bg-slate-800 px-4 py-4">
            <div class="mx-auto flex max-w-2xl items-center gap-3">
                <router-link
                    :to="{ name: 'match-overview', params: { matchId: props.matchId } }"
                    class="text-slate-400 hover:text-white"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </router-link>
                <h1 class="text-lg font-bold">Scoreboard</h1>
                <span v-if="isPrs" class="rounded bg-amber-600 px-1.5 py-0.5 text-[10px] font-bold uppercase">PRS</span>
                <div class="ml-auto flex items-center gap-3">
                    <button
                        @click="fetchScoreboard"
                        :disabled="loading"
                        class="rounded-lg bg-slate-700 px-3 py-1.5 text-xs font-medium transition-colors hover:bg-slate-600"
                    >
                        Refresh
                    </button>
                    <OnlineIndicator />
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-2xl px-4 py-6">
            <div v-if="loading && !leaderboard.length" class="flex justify-center py-12">
                <div class="h-8 w-8 animate-spin rounded-full border-2 border-slate-600 border-t-red-500"></div>
            </div>

            <div v-else-if="error" class="rounded-xl border border-red-800 bg-red-900/30 p-4 text-center">
                <p class="text-red-300">{{ error }}</p>
            </div>

            <div v-else>
                <div v-if="matchName" class="mb-4 text-center">
                    <h2 class="text-xl font-bold">{{ matchName }}</h2>
                </div>

                <div v-if="divisions.length || categories.length" class="mb-3 space-y-1.5">
                    <div v-if="divisions.length" class="flex gap-1.5 overflow-x-auto pb-0.5">
                        <span class="self-center text-[9px] text-slate-600 pr-1">DIV</span>
                        <button @click="setDivisionFilter(null)"
                                class="flex-shrink-0 rounded-full px-3 py-1 text-xs font-medium transition-colors"
                                :class="activeDivision === null ? 'bg-red-600 text-white' : 'bg-slate-700 text-slate-400 hover:bg-slate-600'">
                            All
                        </button>
                        <button v-for="d in divisions" :key="d.id" @click="setDivisionFilter(d.id)"
                                class="flex-shrink-0 rounded-full px-3 py-1 text-xs font-medium transition-colors"
                                :class="activeDivision === d.id ? 'bg-red-600 text-white' : 'bg-slate-700 text-slate-400 hover:bg-slate-600'">
                            {{ d.name }}
                        </button>
                    </div>
                    <div v-if="categories.length" class="flex gap-1.5 overflow-x-auto pb-0.5">
                        <span class="self-center text-[9px] text-slate-600 pr-1">CAT</span>
                        <button @click="setCategoryFilter(null)"
                                class="flex-shrink-0 rounded-full px-2.5 py-0.5 text-[10px] font-medium transition-colors"
                                :class="activeCategory === null ? 'bg-blue-600 text-white' : 'bg-slate-700 text-slate-400 hover:bg-slate-600'">
                            All
                        </button>
                        <button v-for="c in categories" :key="c.id" @click="setCategoryFilter(c.id)"
                                class="flex-shrink-0 rounded-full px-2.5 py-0.5 text-[10px] font-medium transition-colors"
                                :class="activeCategory === c.id ? 'bg-blue-600 text-white' : 'bg-slate-700 text-slate-400 hover:bg-slate-600'">
                            {{ c.name }}
                        </button>
                    </div>
                </div>

                <div v-if="sideBetEnabled" class="mb-3 flex gap-1.5">
                    <button @click="activeTab = 'main'"
                            class="flex-1 rounded-lg px-3 py-2 text-xs font-bold transition-colors"
                            :class="activeTab === 'main' ? 'bg-red-600 text-white' : 'bg-slate-700 text-slate-400 hover:bg-slate-600'">
                        Scoreboard
                    </button>
                    <button @click="activeTab = 'sidebet'"
                            class="flex-1 rounded-lg px-3 py-2 text-xs font-bold transition-colors"
                            :class="activeTab === 'sidebet' ? 'bg-amber-600 text-white' : 'bg-slate-700 text-slate-400 hover:bg-slate-600'">
                        Side Bet
                    </button>
                </div>

                <div v-if="sideBetEnabled && activeTab === 'sidebet'">
                    <div v-if="!sideBet.length" class="rounded-xl border border-slate-700 bg-slate-800 p-8 text-center">
                        <p class="text-slate-400">No side bet scores yet.</p>
                    </div>
                    <div v-else class="overflow-hidden rounded-xl border border-amber-700/50 bg-slate-800">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-700 text-left text-slate-400">
                                    <th class="px-4 py-3 font-medium text-center w-12">#</th>
                                    <th class="px-4 py-3 font-medium">Shooter</th>
                                    <th class="px-4 py-3 font-medium">Squad</th>
                                    <th class="px-4 py-3 font-medium text-center text-amber-400">Small Gong Hits</th>
                                    <th class="px-4 py-3 font-medium">Distances</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700">
                                <tr
                                    v-for="entry in sideBet"
                                    :key="entry.shooter_id"
                                    class="transition-colors hover:bg-slate-700/30"
                                    :class="{
                                        'bg-amber-900/20': entry.rank === 1,
                                        'bg-slate-600/10': entry.rank === 2,
                                        'bg-orange-900/10': entry.rank === 3,
                                    }"
                                >
                                    <td class="px-4 py-3 text-center">
                                        <span
                                            v-if="entry.rank <= 3"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-full text-sm font-bold"
                                            :class="{
                                                'bg-amber-500 text-black': entry.rank === 1,
                                                'bg-slate-400 text-black': entry.rank === 2,
                                                'bg-orange-600 text-white': entry.rank === 3,
                                            }"
                                        >{{ entry.rank }}</span>
                                        <span v-else class="text-slate-400">{{ entry.rank }}</span>
                                    </td>
                                    <td class="px-4 py-3 font-medium text-white">{{ entry.name }}</td>
                                    <td class="px-4 py-3 text-slate-400">{{ entry.squad }}</td>
                                    <td class="px-4 py-3 text-center text-lg font-bold text-amber-400">{{ entry.small_gong_hits }}</td>
                                    <td class="px-4 py-3 text-slate-300">
                                        {{ entry.distances_hit?.length ? entry.distances_hit.map(d => d + 'm').join(', ') : '—' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-3 text-center text-xs text-slate-500">
                        Ranked by smallest gong hits, furthest distance tiebreaker.
                    </p>
                </div>

                <template v-else>
                <div v-if="!leaderboard.length" class="rounded-xl border border-slate-700 bg-slate-800 p-8 text-center">
                    <p class="text-slate-400">No scores recorded yet.</p>
                </div>

                <div v-else class="overflow-hidden rounded-xl border border-slate-700 bg-slate-800">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-700 text-left text-slate-400">
                                <th class="px-4 py-3 font-medium text-center w-12">#</th>
                                <th class="px-4 py-3 font-medium">Shooter</th>
                                <th class="px-4 py-3 font-medium">Squad</th>
                                <th v-if="divisions.length" class="px-4 py-3 font-medium">Division</th>
                                <th class="px-4 py-3 font-medium text-center">Hits</th>
                                <th class="px-4 py-3 font-medium text-center">Misses</th>
                                <th v-if="isPrs" class="px-4 py-3 font-medium text-center">Not Taken</th>
                                <th v-if="isPrs" class="px-4 py-3 font-medium text-right">Time</th>
                                <th class="px-4 py-3 font-medium text-right">Score</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700">
                            <tr
                                v-for="entry in leaderboard"
                                :key="entry.shooter_id"
                                class="transition-colors hover:bg-slate-700/30"
                                :class="{
                                    'bg-amber-900/20': entry.rank === 1,
                                    'bg-slate-600/10': entry.rank === 2,
                                    'bg-orange-900/10': entry.rank === 3,
                                }"
                            >
                                <td class="px-4 py-3 text-center">
                                    <span
                                        v-if="entry.rank <= 3"
                                        class="inline-flex h-7 w-7 items-center justify-center rounded-full text-sm font-bold"
                                        :class="{
                                            'bg-amber-500 text-black': entry.rank === 1,
                                            'bg-slate-400 text-black': entry.rank === 2,
                                            'bg-orange-600 text-white': entry.rank === 3,
                                        }"
                                    >
                                        {{ entry.rank }}
                                    </span>
                                    <span v-else class="text-slate-400">{{ entry.rank }}</span>
                                </td>
                                <td class="px-4 py-3 font-medium text-white">{{ entry.name }}</td>
                                <td class="px-4 py-3 text-slate-400">{{ entry.squad }}</td>
                                <td v-if="divisions.length" class="px-4 py-3 text-slate-400">{{ entry.division || '—' }}</td>
                                <td class="px-4 py-3 text-center text-green-400">{{ entry.hits }}</td>
                                <td class="px-4 py-3 text-center text-red-400">{{ entry.misses }}</td>
                                <td v-if="isPrs" class="px-4 py-3 text-center text-amber-400/60">{{ entry.not_taken ?? 0 }}</td>
                                <td v-if="isPrs" class="px-4 py-3 text-right font-mono text-slate-300">
                                    {{ formatTime(entry.total_time) }}
                                </td>
                                <td class="px-4 py-3 text-right text-lg font-bold text-white">
                                    {{ entry.total_score }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p v-if="isPrs" class="mt-3 text-center text-xs text-slate-500">
                    Ranked by total hits, then tiebreaker stage hits, then tiebreaker stage time.
                </p>
                </template>

                <p v-if="lastUpdated" class="mt-3 text-center text-xs text-slate-500">
                    Last updated: {{ lastUpdated }}
                </p>
            </div>
        </main>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import OnlineIndicator from '../components/OnlineIndicator.vue';

const props = defineProps({
    matchId: { type: Number, required: true },
});

const leaderboard = ref([]);
const sideBet = ref([]);
const sideBetEnabled = ref(false);
const activeTab = ref('main');
const matchName = ref('');
const isPrs = ref(false);
const divisions = ref([]);
const categories = ref([]);
const activeDivision = ref(null);
const activeCategory = ref(null);
const loading = ref(false);
const error = ref(null);
const lastUpdated = ref('');

let refreshInterval;

function formatTime(seconds) {
    if (!seconds) return '—';
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${String(mins).padStart(2, '0')}:${secs.toFixed(2).padStart(5, '0')}`;
}

function setDivisionFilter(id) {
    activeDivision.value = id;
    fetchScoreboard();
}

function setCategoryFilter(id) {
    activeCategory.value = id;
    fetchScoreboard();
}

async function fetchScoreboard() {
    loading.value = true;
    error.value = null;
    try {
        let url = `/api/matches/${props.matchId}/scoreboard`;
        const params = [];
        if (activeDivision.value) params.push(`division=${activeDivision.value}`);
        if (activeCategory.value) params.push(`category=${activeCategory.value}`);
        if (params.length) url += '?' + params.join('&');
        const { data } = await axios.get(url);
        matchName.value = data.match?.name ?? '';
        isPrs.value = data.match?.scoring_type === 'prs';
        divisions.value = data.match?.divisions ?? [];
        categories.value = data.match?.categories ?? [];
        leaderboard.value = data.leaderboard;
        sideBetEnabled.value = !!data.match?.side_bet_enabled;
        sideBet.value = data.side_bet ?? [];
        lastUpdated.value = new Date().toLocaleTimeString('en-ZA');
    } catch (e) {
        error.value = 'Unable to load scoreboard.';
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    fetchScoreboard();
    refreshInterval = setInterval(fetchScoreboard, 10000);
});

onUnmounted(() => {
    clearInterval(refreshInterval);
});
</script>
