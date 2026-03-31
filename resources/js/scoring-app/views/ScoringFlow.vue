<template>
    <div class="flex min-h-screen flex-col bg-slate-900 text-white">
        <!-- Header -->
        <header class="border-b border-slate-700 bg-slate-800 px-4 py-3">
            <div class="mx-auto flex max-w-lg items-center gap-3">
                <router-link
                    :to="backRoute"
                    class="text-slate-400 hover:text-white"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </router-link>
                <div class="min-w-0 flex-1">
                    <h1 class="truncate text-sm font-bold">{{ matchStore.currentMatch?.name }}</h1>
                    <p v-if="isScoped" class="text-[11px] text-amber-400">
                        Relay {{ scopedRelayIndex }} &mdash; {{ scopedDistanceLabel }}
                    </p>
                    <router-link
                        v-else-if="matchStore.lockedSquadName"
                        :to="{ name: 'squad-select', params: { matchId: props.matchId } }"
                        class="flex items-center gap-1 text-[11px] text-amber-400 hover:text-amber-300"
                    >
                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                        {{ matchStore.lockedSquadName }}
                    </router-link>
                </div>
                <div class="ml-auto flex items-center gap-2">
                    <SyncBadge :pending="scoringStore.pendingCount" :syncing="scoringStore.syncing" @sync="scoringStore.syncScores()" />
                    <OnlineIndicator />
                </div>
            </div>
        </header>

        <!-- Auth expired warning -->
        <div v-if="scoringStore.authExpired" class="border-b border-amber-800 bg-amber-900/40 px-4 py-2">
            <div class="mx-auto flex max-w-lg items-center gap-2 text-sm text-amber-300">
                <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
                <span>Session expired. Scores are saved locally. Please log in again to sync.</span>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="!ready" class="flex flex-1 items-center justify-center">
            <div class="h-8 w-8 animate-spin rounded-full border-2 border-slate-600 border-t-red-500"></div>
        </div>

        <!-- Relay summary -->
        <div v-else-if="showRelaySummary" class="flex flex-1 flex-col px-4 py-6">
            <div class="mx-auto w-full max-w-lg space-y-4">
                <div class="text-center">
                    <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-green-600/20">
                        <svg class="h-8 w-8 text-green-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold">{{ scopedDistanceLabel }} &mdash; Relay {{ scopedRelayIndex }} Complete</h2>
                    <p class="text-sm text-slate-400">{{ scopedSquad?.name ?? currentTargetSet?.label }}</p>
                </div>

                <div class="overflow-x-auto rounded-xl border border-slate-700 bg-slate-800">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-700 text-slate-400">
                                <th class="px-3 py-2.5 text-left font-medium">Shooter</th>
                                <th
                                    v-for="gong in (currentTargetSet?.gongs ?? [])"
                                    :key="'gh-' + gong.id"
                                    class="px-2 py-2.5 text-center font-medium whitespace-nowrap"
                                >
                                    <div>#{{ gong.number }}</div>
                                    <div class="text-[10px] text-amber-400">{{ gong.multiplier }}x</div>
                                </th>
                                <th class="px-3 py-2.5 text-center font-medium text-green-400">Hits</th>
                                <th class="px-3 py-2.5 text-center font-medium text-red-400">Miss</th>
                                <th class="px-3 py-2.5 text-right font-bold">Score</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/50">
                            <tr
                                v-for="row in relaySummary"
                                :key="'sr-' + row.shooter.id"
                                class="transition-colors hover:bg-slate-700/30"
                            >
                                <td class="px-3 py-2 font-medium">
                                    {{ row.shooter.name }}
                                    <span v-if="row.shooter.bib_number" class="text-xs text-slate-500 ml-1">#{{ row.shooter.bib_number }}</span>
                                </td>
                                <td
                                    v-for="(g, gi) in row.gongResults"
                                    :key="'sg-' + row.shooter.id + '-' + gi"
                                    class="px-2 py-2 text-center"
                                >
                                    <div v-if="g.result === 'hit'" class="flex flex-col items-center">
                                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-green-600/30 text-xs font-bold text-green-400">&#10003;</span>
                                        <span class="text-[10px] text-green-400/80 tabular-nums">+{{ g.points }}</span>
                                    </div>
                                    <span v-else-if="g.result === 'miss'" class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-red-600/30 text-xs font-bold text-red-400">&#10007;</span>
                                    <span v-else class="text-slate-600">&mdash;</span>
                                </td>
                                <td class="px-3 py-2 text-center tabular-nums text-green-400 font-medium">{{ row.hits }}</td>
                                <td class="px-3 py-2 text-center tabular-nums text-red-400 font-medium">{{ row.misses }}</td>
                                <td class="px-3 py-2 text-right tabular-nums font-bold text-amber-400">{{ row.total }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-col gap-3 pt-2">
                    <button
                        @click="scoringStore.syncScores()"
                        class="w-full rounded-xl bg-green-600 py-3 font-semibold text-white transition-colors hover:bg-green-700"
                    >
                        Sync Scores
                    </button>
                    <button
                        v-if="isScoped && nextSquadSuggestion"
                        @click="goToNextSquad"
                        class="w-full rounded-xl bg-red-600 py-3 font-semibold text-white transition-colors hover:bg-red-700"
                    >
                        Score Next &rarr; {{ nextSquadSuggestion.name }} at {{ scopedDistanceLabel }}
                    </button>
                    <router-link
                        v-if="isScoped"
                        :to="{ name: 'scoring-matrix', params: { matchId: props.matchId } }"
                        class="block w-full rounded-xl border border-slate-600 py-3 text-center font-semibold text-white transition-colors hover:bg-slate-800"
                    >
                        Back to Matrix
                    </router-link>
                    <button
                        v-else
                        @click="dismissSummary"
                        class="w-full rounded-xl bg-red-600 py-3 font-semibold text-white transition-colors hover:bg-red-700"
                    >
                        Continue
                    </button>
                </div>
            </div>
        </div>

        <!-- Gong transition interstitial -->
        <div v-else-if="showGongTransition" class="flex flex-1 flex-col items-center justify-center gap-6 px-4 text-center">
            <p class="text-sm font-medium uppercase tracking-widest text-slate-400">Next Gong</p>

            <div class="rounded-2xl border border-slate-700 bg-slate-800 px-8 py-8 shadow-lg">
                <p class="text-5xl font-black">#{{ currentGong?.number }}</p>
                <p v-if="currentGong?.label" class="mt-2 text-lg text-slate-300">{{ currentGong.label }}</p>
                <p class="mt-2 text-lg font-semibold text-amber-400">{{ effectiveMultiplier }}x points</p>
            </div>

            <div class="rounded-xl border border-slate-700 bg-slate-800/50 px-5 py-3">
                <p class="text-sm text-slate-400">{{ currentTargetSet?.label }}</p>
                <p class="text-lg font-bold">{{ currentTargetSet?.distance_meters }}m</p>
            </div>

            <p class="text-xs text-slate-500">Gong {{ scoringStore.currentGongIndex + 1 }} of {{ currentGongs.length }}</p>

            <button
                @click="dismissGongTransition"
                class="w-full max-w-xs rounded-xl bg-red-600 py-4 text-lg font-bold text-white shadow-lg transition-colors hover:bg-red-700 active:scale-95 active:bg-red-800"
            >
                Continue Scoring
            </button>
        </div>

        <!-- Match complete -->
        <div v-else-if="matchComplete" class="flex flex-1 flex-col items-center justify-center gap-4 px-4 text-center">
            <div class="rounded-full bg-green-600/20 p-4">
                <svg class="h-12 w-12 text-green-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
            <h2 class="text-2xl font-bold">{{ isScoped ? 'Stage Complete!' : 'Scoring Complete!' }}</h2>
            <p class="text-slate-400">
                {{ isScoped ? `Relay ${scopedRelayIndex} at ${scopedDistanceLabel} scored.` : 'All shooters have engaged all targets.' }}
            </p>
            <div class="flex flex-col gap-3 pt-4">
                <button
                    @click="scoringStore.syncScores()"
                    class="rounded-xl bg-green-600 px-6 py-3 font-semibold text-white transition-colors hover:bg-green-700"
                >
                    Sync Scores
                </button>
                <button
                    v-if="isScoped && nextSquadSuggestion"
                    @click="goToNextSquad"
                    class="rounded-xl bg-red-600 px-6 py-3 font-semibold text-white transition-colors hover:bg-red-700"
                >
                    Score Next &rarr; {{ nextSquadSuggestion.name }} at {{ scopedDistanceLabel }}
                </button>
                <router-link
                    v-if="isScoped"
                    :to="{ name: 'scoring-matrix', params: { matchId: props.matchId } }"
                    class="rounded-xl border border-slate-600 px-6 py-3 text-center font-semibold text-white transition-colors hover:bg-slate-800"
                >
                    Back to Matrix
                </router-link>
                <router-link
                    :to="{ name: 'scoreboard', params: { matchId: props.matchId } }"
                    class="rounded-xl border border-slate-600 px-6 py-3 text-center font-semibold text-white transition-colors hover:bg-slate-800"
                >
                    View Scoreboard
                </router-link>
            </div>
        </div>

        <!-- Scoring interface -->
        <template v-else>
            <!-- Progress bar -->
            <div class="bg-slate-800 px-4 py-2">
                <div class="mx-auto max-w-lg">
                    <div class="flex items-center justify-between text-xs text-slate-400">
                        <span v-if="!isScoped">Set {{ scoringStore.currentTargetSetIndex + 1 }}/{{ targetSets.length }}</span>
                        <span>Gong {{ scoringStore.currentGongIndex + 1 }}/{{ currentGongs.length }}</span>
                        <span>Shooter {{ scoringStore.currentShooterIndex + 1 }}/{{ shooters.length }}</span>
                    </div>
                    <div class="mt-1 h-1.5 rounded-full bg-slate-700">
                        <div
                            class="h-full rounded-full bg-red-600 transition-all duration-300"
                            :style="{ width: progressPercent + '%' }"
                        ></div>
                    </div>
                </div>
            </div>

            <!-- Target set info -->
            <div class="border-b border-slate-700 bg-slate-800/50 px-4 py-2">
                <div class="mx-auto max-w-lg flex items-center justify-between text-sm">
                    <span class="font-semibold text-amber-400">{{ currentTargetSet?.label }}</span>
                    <span class="text-slate-400">{{ currentTargetSet?.distance_meters }}m</span>
                </div>
            </div>

            <!-- Main scoring area -->
            <main class="flex flex-1 flex-col px-4 py-6">
                <div class="mx-auto flex w-full max-w-lg flex-1 flex-col">
                    <!-- Current gong -->
                    <div class="mb-4 text-center">
                        <p class="text-sm text-slate-400">Gong</p>
                        <p class="text-3xl font-bold">
                            #{{ currentGong?.number }}
                            <span v-if="currentGong?.label" class="text-lg text-slate-400">{{ currentGong.label }}</span>
                        </p>
                        <p class="mt-1 text-sm text-amber-400">{{ effectiveMultiplier }}x points</p>
                    </div>

                    <!-- Current shooter -->
                    <div class="mb-8 rounded-xl border border-slate-700 bg-slate-800 p-4 text-center">
                        <p class="text-sm text-slate-400">Shooter</p>
                        <p class="text-2xl font-bold">{{ currentShooter?.name }}</p>
                        <div class="mt-1 flex items-center justify-center gap-3 text-sm text-slate-400">
                            <span v-if="currentShooter?.bib_number">Bib #{{ currentShooter.bib_number }}</span>
                            <span>{{ currentShooter?.squadName }}</span>
                        </div>
                    </div>

                    <!-- Existing score indicator -->
                    <div
                        v-if="existingScore !== null"
                        class="mb-4 rounded-lg px-4 py-2 text-center text-sm font-medium"
                        :class="existingScore.isHit ? 'bg-green-900/40 text-green-400' : 'bg-red-900/40 text-red-400'"
                    >
                        Previously scored: {{ existingScore.isHit ? 'HIT' : 'MISS' }}
                    </div>

                    <!-- Hit / Miss buttons -->
                    <div class="mt-auto grid grid-cols-2 gap-4">
                        <button
                            @click="recordScore(true)"
                            class="flex h-32 flex-col items-center justify-center rounded-2xl bg-green-600 text-white shadow-lg transition-all active:scale-95 active:bg-green-700"
                            :class="{ 'ring-4 ring-green-400': existingScore?.isHit === true }"
                        >
                            <svg class="mb-1 h-10 w-10" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                            <span class="text-2xl font-black">HIT</span>
                        </button>
                        <button
                            @click="recordScore(false)"
                            class="flex h-32 flex-col items-center justify-center rounded-2xl bg-red-700 text-white shadow-lg transition-all active:scale-95 active:bg-red-800"
                            :class="{ 'ring-4 ring-red-400': existingScore?.isHit === false }"
                        >
                            <svg class="mb-1 h-10 w-10" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                            <span class="text-2xl font-black">MISS</span>
                        </button>
                    </div>

                    <!-- Nav buttons -->
                    <div class="mt-4 flex gap-3">
                        <button
                            @click="goBack"
                            :disabled="isFirst"
                            class="flex-1 rounded-xl border border-slate-600 py-3 text-sm font-medium transition-colors hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-30"
                        >
                            &larr; Previous
                        </button>
                        <button
                            @click="goForward"
                            class="flex-1 rounded-xl border border-slate-600 py-3 text-sm font-medium transition-colors hover:bg-slate-800"
                        >
                            Next &rarr;
                        </button>
                    </div>
                </div>
            </main>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useMatchStore } from '../stores/matchStore';
import { useScoringStore } from '../stores/scoringStore';
import OnlineIndicator from '../components/OnlineIndicator.vue';
import SyncBadge from '../components/SyncBadge.vue';

const props = defineProps({
    matchId: { type: Number, required: true },
    squadId: { type: Number, default: null },
    targetSetId: { type: Number, default: null },
});

const route = useRoute();
const router = useRouter();
const matchStore = useMatchStore();
const scoringStore = useScoringStore();
const ready = ref(false);
const matchComplete = ref(false);
const showRelaySummary = ref(false);
const showGongTransition = ref(false);

const isScoped = computed(() => route.name === 'scoped-scoring' && props.squadId && props.targetSetId);

const scopedSquad = computed(() => {
    if (!isScoped.value) return null;
    return matchStore.squads.find(s => s.id === props.squadId);
});

const scopedRelayIndex = computed(() => {
    if (!isScoped.value) return 0;
    const idx = matchStore.squads.findIndex(s => s.id === props.squadId);
    return idx >= 0 ? idx + 1 : '?';
});

const scopedTargetSet = computed(() => {
    if (!isScoped.value) return null;
    return matchStore.targetSets.find(ts => ts.id === props.targetSetId);
});

const scopedDistanceLabel = computed(() => {
    return scopedTargetSet.value ? `${scopedTargetSet.value.distance_meters}m` : '';
});

const backRoute = computed(() => {
    if (isScoped.value) {
        return { name: 'scoring-matrix', params: { matchId: props.matchId } };
    }
    return { name: 'match-overview', params: { matchId: props.matchId } };
});

const targetSets = computed(() => {
    if (isScoped.value && scopedTargetSet.value) {
        return [scopedTargetSet.value];
    }
    return matchStore.targetSets;
});
const currentTargetSet = computed(() => targetSets.value[scoringStore.currentTargetSetIndex]);
const currentGongs = computed(() => currentTargetSet.value?.gongs ?? []);
const currentGong = computed(() => currentGongs.value[scoringStore.currentGongIndex]);
const distanceMultiplier = computed(() => parseFloat(currentTargetSet.value?.distance_multiplier) || 1);
const effectiveMultiplier = computed(() => {
    const dm = distanceMultiplier.value;
    const gm = parseFloat(currentGong.value?.multiplier) || 1;
    return Math.round(dm * gm * 100) / 100;
});

const shooters = computed(() => {
    if (isScoped.value && scopedSquad.value) {
        return scopedSquad.value.shooters
            .filter(s => s.status === 'active')
            .map(s => ({ ...s, squadName: scopedSquad.value.name }));
    }
    return matchStore.hasSquadLock ? matchStore.squadShooters : matchStore.allShooters;
});
const currentShooter = computed(() => shooters.value[scoringStore.currentShooterIndex]);

const existingScore = computed(() => {
    if (!currentShooter.value || !currentGong.value) return null;
    return scoringStore.getScore(currentShooter.value.id, currentGong.value.id);
});

const totalSteps = computed(() => {
    return targetSets.value.reduce((sum, ts) => sum + ts.gongs.length * shooters.value.length, 0);
});

const currentStep = computed(() => {
    let step = 0;
    for (let t = 0; t < scoringStore.currentTargetSetIndex; t++) {
        step += targetSets.value[t].gongs.length * shooters.value.length;
    }
    step += scoringStore.currentGongIndex * shooters.value.length;
    step += scoringStore.currentShooterIndex;
    return step;
});

const progressPercent = computed(() => {
    if (!totalSteps.value) return 0;
    return Math.round((currentStep.value / totalSteps.value) * 100);
});

const isFirst = computed(() => {
    return scoringStore.currentTargetSetIndex === 0 &&
        scoringStore.currentGongIndex === 0 &&
        scoringStore.currentShooterIndex === 0;
});

const relaySummary = computed(() => {
    if (!currentTargetSet.value || !shooters.value.length) return [];
    const gongs = currentTargetSet.value.gongs ?? [];
    const distMult = parseFloat(currentTargetSet.value.distance_multiplier) || 1;
    return shooters.value.map(shooter => {
        let hits = 0;
        let misses = 0;
        let total = 0;
        const gongResults = gongs.map(gong => {
            const score = scoringStore.getScore(shooter.id, gong.id);
            const points = Math.round(distMult * (parseFloat(gong.multiplier) || 1) * 100) / 100;
            if (score?.isHit === true) { hits++; total += points; return { result: 'hit', points }; }
            if (score?.isHit === false) { misses++; return { result: 'miss', points: 0 }; }
            return { result: null, points: 0 };
        });
        return { shooter, gongResults, hits, misses, total: Math.round(total * 100) / 100 };
    });
});

const nextSquadSuggestion = computed(() => {
    if (!isScoped.value || !props.targetSetId) return null;
    const squads = matchStore.squads;
    const matrix = matchStore.completionMatrix;
    const concurrentRelays = matchStore.currentMatch?.concurrent_relays ?? 2;
    const currentIdx = squads.findIndex(s => s.id === props.squadId);
    if (currentIdx < 0) return null;

    const stride = Math.max(1, concurrentRelays);
    const nextIdx = currentIdx + stride;

    for (let offset = stride; offset < squads.length; offset += stride) {
        const idx = (currentIdx + offset) % squads.length;
        const squad = squads[idx];
        const status = matrix[squad.id]?.[props.targetSetId]?.status;
        if (status !== 'scored') {
            return squad;
        }
    }
    return null;
});

function goToNextSquad() {
    if (!nextSquadSuggestion.value) return;
    router.push({
        name: 'roll-call',
        params: {
            matchId: props.matchId,
            squadId: nextSquadSuggestion.value.id,
            targetSetId: props.targetSetId,
        },
    });
}

async function recordScore(isHit) {
    if (!currentShooter.value || !currentGong.value) return;
    await scoringStore.recordScore(currentShooter.value.id, currentGong.value.id, isHit);
    advance();
}

function advance() {
    const s = scoringStore;
    if (s.advanceToNextShooter(shooters.value.length)) return;
    if (s.advanceToNextGong(currentGongs.value.length, shooters.value.length)) {
        showGongTransition.value = true;
        return;
    }
    if (isScoped.value) {
        showRelaySummary.value = true;
        return;
    }
    if (s.advanceToNextTargetSet(targetSets.value.length)) return;
    matchComplete.value = true;
}

function dismissGongTransition() {
    showGongTransition.value = false;
}

function dismissSummary() {
    showRelaySummary.value = false;
    if (scoringStore.advanceToNextTargetSet(targetSets.value.length)) return;
    matchComplete.value = true;
}

function goForward() {
    advance();
}

function goBack() {
    const s = scoringStore;
    if (s.currentShooterIndex > 0) {
        s.currentShooterIndex--;
        return;
    }
    if (s.currentGongIndex > 0) {
        s.currentGongIndex--;
        s.currentShooterIndex = shooters.value.length - 1;
        return;
    }
    if (s.currentTargetSetIndex > 0) {
        s.currentTargetSetIndex--;
        const prevGongs = targetSets.value[s.currentTargetSetIndex].gongs;
        s.currentGongIndex = prevGongs.length - 1;
        s.currentShooterIndex = shooters.value.length - 1;
    }
}

let syncInterval;

onMounted(async () => {
    if (!matchStore.currentMatch || matchStore.currentMatch.id !== props.matchId) {
        await matchStore.fetchMatch(props.matchId);
    }
    const scores = matchStore.currentMatch?.scores ?? [];
    await scoringStore.initForMatch(props.matchId, scores);
    ready.value = true;

    syncInterval = setInterval(() => {
        if (navigator.onLine && scoringStore.pendingCount > 0) {
            scoringStore.syncScores();
        }
    }, 15000);
});

onUnmounted(() => {
    clearInterval(syncInterval);
});
</script>
