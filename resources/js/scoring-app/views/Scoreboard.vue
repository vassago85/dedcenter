<template>
    <div class="min-h-screen bg-app text-primary">
        <header class="border-b border-border bg-surface px-4 py-4">
            <div class="mx-auto flex max-w-4xl items-center gap-3">
                <router-link
                    :to="{ name: 'match-overview', params: { matchId: props.matchId } }"
                    class="text-muted hover:text-primary"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </router-link>
                <h1 class="text-lg font-bold">Results</h1>
                <span v-if="isPrs" class="rounded bg-amber-600 px-1.5 py-0.5 text-[10px] font-bold uppercase">PRS</span>
                <span v-if="isElr" class="rounded bg-sky-600 px-1.5 py-0.5 text-[10px] font-bold uppercase">ELR</span>
                <div class="ml-auto flex items-center gap-3">
                    <span v-if="autoRefresh" class="flex items-center gap-1 text-[10px] text-muted">
                        <span class="h-1.5 w-1.5 rounded-full bg-green-500 animate-pulse"></span>
                        LIVE
                    </span>
                    <button
                        @click="fetchData"
                        :disabled="loading"
                        class="rounded-lg bg-surface-2 px-3 py-1.5 text-xs font-medium transition-colors hover:bg-border"
                    >
                        Refresh
                    </button>
                    <OnlineIndicator />
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-4xl px-4 py-6">
            <div v-if="loading && !standings.length" class="flex justify-center py-12">
                <div class="h-8 w-8 animate-spin rounded-full border-2 border-border border-t-accent"></div>
            </div>

            <div v-else-if="error" class="rounded-xl border border-red-800 bg-red-900/30 p-4 text-center">
                <p class="text-red-300">{{ error }}</p>
            </div>

            <div v-else>
                <div v-if="matchName" class="mb-4 text-center">
                    <h2 class="text-xl font-bold">{{ matchName }}</h2>
                    <p v-if="matchDate" class="text-sm text-muted">{{ matchDate }}</p>
                </div>

                <!-- View toggle -->
                <div class="mb-4 flex gap-1.5">
                    <button
                        @click="viewMode = 'summary'"
                        class="flex-1 rounded-lg px-3 py-2 text-xs font-bold transition-colors"
                        :class="viewMode === 'summary' ? 'bg-accent text-white' : 'bg-surface text-muted hover:bg-surface-2'"
                    >
                        Leaderboard
                    </button>
                    <button
                        @click="viewMode = 'detailed'"
                        class="flex-1 rounded-lg px-3 py-2 text-xs font-bold transition-colors"
                        :class="viewMode === 'detailed' ? 'bg-accent text-white' : 'bg-surface text-muted hover:bg-surface-2'"
                    >
                        Detailed Breakdown
                    </button>
                    <button
                        v-if="sideBetEnabled && !isElr"
                        @click="viewMode = 'sidebet'"
                        class="flex-1 rounded-lg px-3 py-2 text-xs font-bold transition-colors"
                        :class="viewMode === 'sidebet' ? 'bg-amber-600 text-white' : 'bg-surface text-muted hover:bg-surface-2'"
                    >
                        Side Bet
                    </button>
                </div>

                <!-- =================== SUMMARY LEADERBOARD =================== -->
                <template v-if="viewMode === 'summary' && !isElr">
                    <div v-if="!standings.length" class="rounded-xl border border-border bg-surface p-8 text-center">
                        <p class="text-muted">No scores recorded yet.</p>
                    </div>

                    <div v-else class="overflow-x-auto rounded-xl border border-border bg-surface">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-border text-left text-muted">
                                    <th class="px-3 py-3 text-center w-10">#</th>
                                    <th class="px-3 py-3">Shooter</th>
                                    <th class="px-3 py-3">Relay</th>
                                    <th
                                        v-for="ts in targetSets"
                                        :key="'hdr-' + ts.id"
                                        class="px-3 py-3 text-center whitespace-nowrap"
                                    >
                                        {{ ts.distance_meters }}m
                                    </th>
                                    <th class="px-3 py-3 text-center">Hits</th>
                                    <th class="px-3 py-3 text-center">Miss</th>
                                    <th class="px-3 py-3 text-center font-bold">Total</th>
                                    <th class="px-3 py-3 text-center">Hit %</th>
                                    <th class="px-3 py-3 text-right font-bold">Rel %</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr
                                    v-for="(entry, idx) in standings"
                                    :key="entry.id"
                                    class="transition-colors hover:bg-surface-2"
                                    :class="rankRowClass(idx + 1)"
                                >
                                    <td class="px-3 py-3 text-center">
                                        <span
                                            v-if="idx < 3"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-full text-sm font-bold"
                                            :class="medalClass(idx + 1)"
                                        >{{ idx + 1 }}</span>
                                        <span v-else class="text-muted">{{ idx + 1 }}</span>
                                    </td>
                                    <td class="px-3 py-3 font-medium">{{ entry.name }}</td>
                                    <td class="px-3 py-3 text-muted">{{ entry.squad_name }}</td>
                                    <td
                                        v-for="ts in targetSets"
                                        :key="'cell-' + entry.id + '-' + ts.id"
                                        class="px-3 py-3 text-center tabular-nums"
                                    >
                                        <span v-if="entry.distances[ts.id]" :class="entry.distances[ts.id].subtotal > 0 ? 'text-green-400' : 'text-muted'">
                                            {{ entry.distances[ts.id].subtotal }}
                                        </span>
                                        <span v-else class="text-muted">&mdash;</span>
                                    </td>
                                    <td class="px-3 py-3 text-center text-green-400 tabular-nums">{{ entry.total_hits }}</td>
                                    <td class="px-3 py-3 text-center text-red-400 tabular-nums">{{ entry.total_misses }}</td>
                                    <td class="px-3 py-3 text-center text-lg font-bold tabular-nums">{{ entry.total_score }}</td>
                                    <td class="px-3 py-3 text-center tabular-nums text-muted">{{ entry.hit_rate != null ? entry.hit_rate + '%' : '—' }}</td>
                                    <td class="px-3 py-3 text-right tabular-nums font-bold text-amber-400">{{ entry.relative_score != null ? entry.relative_score + '%' : '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>

                <!-- =================== ELR SUMMARY LEADERBOARD =================== -->
                <template v-else-if="viewMode === 'summary' && isElr">
                    <div v-if="!standings.length" class="rounded-xl border border-border bg-surface p-8 text-center">
                        <p class="text-muted">No scores recorded yet.</p>
                    </div>

                    <div v-else class="overflow-x-auto rounded-xl border border-border bg-surface">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-border text-left text-muted">
                                    <th class="px-3 py-3 text-center w-10">#</th>
                                    <th class="px-3 py-3">Shooter</th>
                                    <th class="px-3 py-3">Relay</th>
                                    <th class="px-3 py-3 text-center">Points</th>
                                    <th class="px-3 py-3 text-center">Hits</th>
                                    <th class="px-3 py-3 text-center">1st Rd</th>
                                    <th class="px-3 py-3 text-center">Furthest (m)</th>
                                    <th class="px-3 py-3 text-right font-bold">Norm %</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr
                                    v-for="(entry, idx) in standings"
                                    :key="'elr-sum-' + idx"
                                    class="transition-colors hover:bg-surface-2"
                                    :class="rankRowClass(entry.rank)"
                                >
                                    <td class="px-3 py-3 text-center">
                                        <span
                                            v-if="entry.rank <= 3"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-full text-sm font-bold"
                                            :class="medalClass(entry.rank)"
                                        >{{ entry.rank }}</span>
                                        <span v-else class="text-muted">{{ entry.rank }}</span>
                                    </td>
                                    <td class="px-3 py-3 font-medium">{{ entry.name }}</td>
                                    <td class="px-3 py-3 text-muted">{{ entry.squad_name }}</td>
                                    <td class="px-3 py-3 text-center tabular-nums font-bold">{{ entry.total_points }}</td>
                                    <td class="px-3 py-3 text-center text-green-400 tabular-nums">{{ entry.total_hits }}</td>
                                    <td class="px-3 py-3 text-center tabular-nums">{{ entry.first_round_hits }}</td>
                                    <td class="px-3 py-3 text-center tabular-nums">{{ entry.furthest_hit_m ?? '&mdash;' }}</td>
                                    <td class="px-3 py-3 text-right text-lg font-bold tabular-nums">
                                        {{ entry.normalized_score != null ? entry.normalized_score.toFixed(1) + '%' : '&mdash;' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>

                <!-- =================== DETAILED BREAKDOWN =================== -->
                <template v-else-if="viewMode === 'detailed' && !isElr">
                    <div v-if="!standings.length" class="rounded-xl border border-border bg-surface p-8 text-center">
                        <p class="text-muted">No scores recorded yet.</p>
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="(entry, idx) in standings"
                            :key="'detail-' + entry.id"
                            class="rounded-xl border border-border bg-surface overflow-hidden"
                        >
                            <!-- Shooter header (tappable to expand) -->
                            <button
                                @click="toggleExpand(entry.id)"
                                class="flex w-full items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-surface-2"
                            >
                                <span
                                    v-if="idx < 3"
                                    class="inline-flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full text-sm font-bold"
                                    :class="medalClass(idx + 1)"
                                >{{ idx + 1 }}</span>
                                <span v-else class="flex h-7 w-7 flex-shrink-0 items-center justify-center text-sm text-muted">{{ idx + 1 }}</span>

                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-semibold">{{ entry.name }}</p>
                                    <p class="text-xs text-muted">{{ entry.squad_name }} &middot; {{ entry.total_hits }} hits &middot; {{ entry.total_misses }} misses</p>
                                </div>

                                <span class="text-xl font-bold tabular-nums">{{ entry.total_score }}</span>

                                <svg
                                    class="h-5 w-5 flex-shrink-0 text-muted transition-transform"
                                    :class="{ 'rotate-180': expandedIds.has(entry.id) }"
                                    fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </button>

                            <!-- Expanded detail -->
                            <div v-if="expandedIds.has(entry.id)" class="border-t border-border">
                                <div
                                    v-for="ts in targetSets"
                                    :key="'dist-' + entry.id + '-' + ts.id"
                                    class="border-b border-border/50 last:border-b-0"
                                >
                                    <div class="flex items-center justify-between bg-surface-2/50 px-4 py-2">
                                        <span class="text-sm font-semibold">{{ ts.label }} ({{ ts.distance_meters }}m)</span>
                                        <div class="flex items-center gap-3 text-xs">
                                            <span class="text-green-400">{{ entry.distances[ts.id]?.hits ?? 0 }} hits</span>
                                            <span class="text-red-400">{{ entry.distances[ts.id]?.misses ?? 0 }} miss</span>
                                            <span class="font-bold">{{ entry.distances[ts.id]?.subtotal ?? 0 }} pts</span>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-px bg-border/30 sm:grid-cols-3 md:grid-cols-4">
                                        <div
                                            v-for="gong in (entry.distances[ts.id]?.gongs ?? [])"
                                            :key="'gong-' + gong.gong_id"
                                            class="flex items-center justify-between bg-app px-3 py-2 text-xs"
                                        >
                                            <span class="text-muted">
                                                Gong #{{ gong.gong_number }}
                                                <span v-if="gong.gong_label" class="text-secondary">({{ gong.gong_label }})</span>
                                            </span>
                                            <span v-if="gong.is_hit === true" class="font-bold text-green-400">HIT +{{ gong.points ?? gong.multiplier }}</span>
                                            <span v-else-if="gong.is_hit === false" class="font-bold text-red-400">MISS</span>
                                            <span v-else class="text-muted/50">&mdash;</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- =================== ELR DETAILED BREAKDOWN =================== -->
                <template v-else-if="viewMode === 'detailed' && isElr">
                    <div v-if="!standings.length" class="rounded-xl border border-border bg-surface p-8 text-center">
                        <p class="text-muted">No scores recorded yet.</p>
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="(entry, idx) in standings"
                            :key="'elr-detail-' + idx"
                            class="rounded-xl border border-border bg-surface overflow-hidden"
                        >
                            <button
                                @click="toggleExpand('elr-' + idx)"
                                class="flex w-full items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-surface-2"
                            >
                                <span
                                    v-if="entry.rank <= 3"
                                    class="inline-flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full text-sm font-bold"
                                    :class="medalClass(entry.rank)"
                                >{{ entry.rank }}</span>
                                <span v-else class="flex h-7 w-7 flex-shrink-0 items-center justify-center text-sm text-muted">{{ entry.rank }}</span>

                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-semibold">{{ entry.name }}</p>
                                    <p class="text-xs text-muted">{{ entry.squad_name }} &middot; {{ entry.total_hits }} hits &middot; {{ entry.total_points }} pts</p>
                                </div>

                                <div class="text-right">
                                    <span class="text-xl font-bold tabular-nums">
                                        {{ entry.normalized_score != null ? entry.normalized_score.toFixed(1) + '%' : '&mdash;' }}
                                    </span>
                                </div>

                                <svg
                                    class="h-5 w-5 flex-shrink-0 text-muted transition-transform"
                                    :class="{ 'rotate-180': expandedIds.has('elr-' + idx) }"
                                    fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </button>

                            <div v-if="expandedIds.has('elr-' + idx)" class="border-t border-border">
                                <div
                                    v-for="stage in (entry.stages ?? [])"
                                    :key="'elr-stage-' + entry.rank + '-' + stage.stage_id"
                                    class="border-b border-border/50 last:border-b-0"
                                >
                                    <div class="flex items-center justify-between bg-surface-2/50 px-4 py-2">
                                        <span class="text-sm font-semibold">{{ stage.label }}</span>
                                        <div class="flex items-center gap-3 text-xs">
                                            <span class="uppercase text-muted">{{ stage.stage_type }}</span>
                                            <span class="font-bold">{{ stage.points }} pts</span>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 gap-px bg-border/30 sm:grid-cols-2 md:grid-cols-3">
                                        <div
                                            v-for="(target, tIdx) in (stage.targets ?? [])"
                                            :key="'elr-t-' + stage.stage_id + '-' + tIdx"
                                            class="bg-app px-3 py-2 text-xs"
                                        >
                                            <div class="flex items-center justify-between">
                                                <span class="text-muted">
                                                    {{ target.distance_m ? target.distance_m + 'm' : 'Target ' + (tIdx + 1) }}
                                                    <span v-if="target.name" class="text-secondary">({{ target.name }})</span>
                                                </span>
                                                <span v-if="elrTargetHit(target)" class="font-bold text-green-400">HIT +{{ elrTargetPoints(target) }}</span>
                                                <span v-else-if="target.shots?.length" class="font-bold text-red-400">MISS</span>
                                                <span v-else class="text-muted/50">&mdash;</span>
                                            </div>
                                            <div v-if="target.shots?.length > 1" class="mt-1 flex gap-1.5">
                                                <span
                                                    v-for="shot in target.shots"
                                                    :key="shot.shot_number"
                                                    class="rounded px-1.5 py-0.5 text-[10px] font-bold uppercase"
                                                    :class="shot.result === 'hit' ? 'bg-green-600/20 text-green-400' : 'bg-red-600/20 text-red-400'"
                                                >
                                                    Rd{{ shot.shot_number }}: {{ shot.result }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- =================== SIDE BET =================== -->
                <template v-else-if="viewMode === 'sidebet'">
                    <div v-if="!sideBet.length" class="rounded-xl border border-border bg-surface p-8 text-center">
                        <p class="text-muted">No side bet scores yet.</p>
                    </div>
                    <div v-else class="overflow-hidden rounded-xl border border-amber-700/50 bg-surface">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-border text-left text-muted">
                                    <th class="px-4 py-3 text-center w-12">#</th>
                                    <th class="px-4 py-3">Shooter</th>
                                    <th class="px-4 py-3">Relay</th>
                                    <th class="px-4 py-3 text-center text-amber-400">Small Gong Hits</th>
                                    <th class="px-4 py-3">Distances</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr
                                    v-for="entry in sideBet"
                                    :key="entry.shooter_id"
                                    class="transition-colors hover:bg-surface-2"
                                    :class="rankRowClass(entry.rank)"
                                >
                                    <td class="px-4 py-3 text-center">
                                        <span
                                            v-if="entry.rank <= 3"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-full text-sm font-bold"
                                            :class="medalClass(entry.rank)"
                                        >{{ entry.rank }}</span>
                                        <span v-else class="text-muted">{{ entry.rank }}</span>
                                    </td>
                                    <td class="px-4 py-3 font-medium">{{ entry.name }}</td>
                                    <td class="px-4 py-3 text-muted">{{ entry.squad }}</td>
                                    <td class="px-4 py-3 text-center text-lg font-bold text-amber-400">{{ entry.small_gong_hits }}</td>
                                    <td class="px-4 py-3 text-secondary">
                                        {{ entry.distances_hit?.length ? entry.distances_hit.map(d => d + 'm').join(', ') : '—' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>

                <p v-if="lastUpdated" class="mt-4 text-center text-xs text-muted">
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

const standings = ref([]);
const targetSets = ref([]);
const sideBet = ref([]);
const sideBetEnabled = ref(false);
const matchName = ref('');
const matchDate = ref('');
const isPrs = ref(false);
const isElr = ref(false);
const elrStages = ref([]);
const viewMode = ref('summary');
const loading = ref(false);
const error = ref(null);
const lastUpdated = ref('');
const autoRefresh = ref(true);
const expandedIds = ref(new Set());

let refreshInterval;

function toggleExpand(id) {
    if (expandedIds.value.has(id)) {
        expandedIds.value.delete(id);
    } else {
        expandedIds.value.add(id);
    }
}

function medalClass(rank) {
    if (rank === 1) return 'bg-amber-500 text-black';
    if (rank === 2) return 'bg-slate-400 text-black';
    if (rank === 3) return 'bg-orange-600 text-white';
    return '';
}

function rankRowClass(rank) {
    if (rank === 1) return 'bg-amber-900/15';
    if (rank === 2) return 'bg-slate-600/10';
    if (rank === 3) return 'bg-orange-900/10';
    return '';
}

function elrTargetHit(target) {
    return target.shots?.some(s => s.result === 'hit') ?? false;
}

function elrTargetPoints(target) {
    return target.shots?.reduce((sum, s) => sum + (s.result === 'hit' ? (s.points ?? 0) : 0), 0) ?? 0;
}

async function fetchData() {
    loading.value = true;
    error.value = null;
    try {
        const { data } = await axios.get(`/api/matches/${props.matchId}/scoreboard?detailed=1`);
        matchName.value = data.match?.name ?? '';
        matchDate.value = data.match?.date ?? '';
        isPrs.value = data.match?.scoring_type === 'prs';
        isElr.value = data.match?.scoring_type === 'elr';

        if (isElr.value) {
            standings.value = data.standings ?? [];
            elrStages.value = data.stages ?? [];
        } else {
            targetSets.value = data.target_sets ?? [];
            standings.value = data.standings ?? [];

            if (data.match?.side_bet_enabled) {
                sideBetEnabled.value = true;
                const sbRes = await axios.get(`/api/matches/${props.matchId}/scoreboard`);
                sideBet.value = sbRes.data.side_bet ?? [];
            }
        }

        lastUpdated.value = new Date().toLocaleTimeString('en-ZA');
    } catch (e) {
        error.value = 'Unable to load scoreboard.';
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    fetchData();
    refreshInterval = setInterval(fetchData, 12000);
});

onUnmounted(() => {
    clearInterval(refreshInterval);
});
</script>
