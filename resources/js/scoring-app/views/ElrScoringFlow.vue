<template>
    <div class="flex min-h-screen flex-col bg-app text-primary">
        <!-- Header with match name, stage badge, sync, online -->
        <header class="border-b border-border bg-surface px-4 py-3">
            <div class="mx-auto flex max-w-lg items-center gap-3">
                <router-link :to="{ name: 'match-overview', params: { matchId: props.matchId } }" class="text-muted hover:text-primary">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                </router-link>
                <div class="min-w-0 flex-1">
                    <h1 class="truncate text-sm font-bold">{{ matchStore.currentMatch?.name }}</h1>
                    <div class="flex items-center gap-2 text-[11px]">
                        <span class="rounded px-1.5 py-0.5 text-[10px] font-bold uppercase" :class="currentStage?.stage_type === 'ladder' ? 'bg-emerald-600 text-white' : 'bg-surface-2 text-muted'">
                            {{ currentStage?.stage_type?.toUpperCase() }}
                        </span>
                        <span class="text-muted">{{ currentStage?.label }}</span>
                    </div>
                </div>
                <div class="ml-auto flex items-center gap-2">
                    <SyncBadge :pending="elrStore.pendingCount" :syncing="elrStore.syncing" @sync="elrStore.syncShots()" />
                    <OnlineIndicator />
                </div>
            </div>
        </header>

        <DeviceLockBanner />

        <!-- Loading -->
        <div v-if="!ready" class="flex flex-1 items-center justify-center">
            <div class="h-8 w-8 animate-spin rounded-full border-2 border-border border-t-accent"></div>
        </div>

        <!-- Complete -->
        <div v-else-if="matchComplete" class="flex flex-1 flex-col items-center justify-center gap-4 px-4 text-center">
            <div class="rounded-full bg-emerald-600/20 p-4">
                <svg class="h-12 w-12 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
            </div>
            <h2 class="text-2xl font-bold">ELR Scoring Complete!</h2>
            <div class="flex flex-col gap-3 pt-4">
                <button @click="elrStore.syncShots()" class="rounded-xl bg-emerald-600 px-6 py-3 font-semibold text-white">Sync Scores</button>
                <router-link :to="{ name: 'scoreboard', params: { matchId: props.matchId } }" class="rounded-xl border border-border px-6 py-3 font-semibold text-primary">View Results</router-link>
            </div>
        </div>

        <!-- Scoring interface -->
        <template v-else>
            <!-- Progress bar -->
            <div class="bg-surface px-4 py-2">
                <div class="mx-auto max-w-lg">
                    <div class="flex items-center justify-between text-xs text-muted">
                        <span>Stage {{ currentStageIndex + 1 }}/{{ stages.length }}</span>
                        <span>Shooter {{ currentShooterIndex + 1 }}/{{ shooters.length }}</span>
                        <span>Target {{ currentTargetIndex + 1 }}/{{ currentTargets.length }}</span>
                    </div>
                    <div class="mt-1 h-1.5 rounded-full bg-surface-2">
                        <div class="h-full rounded-full bg-emerald-500 transition-all duration-300" :style="{ width: progressPercent + '%' }"></div>
                    </div>
                </div>
            </div>

            <main class="flex flex-1 flex-col px-4 py-6">
                <div class="mx-auto flex w-full max-w-lg flex-1 flex-col">
                    <!-- Shooter card -->
                    <div class="mb-4 rounded-xl border border-border bg-surface p-4 text-center">
                        <p class="text-sm text-muted">Shooter</p>
                        <p class="text-2xl font-bold">{{ currentShooter?.name }}</p>
                        <p v-if="currentShooter?.bib_number" class="text-xs text-muted">Bib #{{ currentShooter.bib_number }}</p>
                    </div>

                    <!-- Target info -->
                    <div class="mb-4 text-center">
                        <p class="text-sm text-muted">Target</p>
                        <p class="text-3xl font-bold">{{ currentTarget?.name }}</p>
                        <p class="mt-1 text-lg text-emerald-400">{{ currentTarget?.distance_m }}m</p>
                    </div>

                    <!-- Shot info -->
                    <div class="mb-4 rounded-xl border border-border bg-surface p-4 text-center">
                        <p class="text-sm text-muted">Shot {{ currentShotNumber }} of {{ currentTarget?.max_shots }}</p>
                        <p class="mt-1 text-xl font-bold text-emerald-400">{{ pointsForCurrentShot }} pts if hit</p>
                        <div class="mt-2 flex justify-center gap-1">
                            <span v-for="s in currentTarget?.max_shots" :key="s"
                                  class="h-2 w-8 rounded-full"
                                  :class="s < currentShotNumber ? 'bg-muted' : s === currentShotNumber ? 'bg-emerald-500' : 'bg-surface-2'">
                            </span>
                        </div>
                    </div>

                    <!-- Points flash -->
                    <div v-if="lastPointsFlash" class="mb-4 animate-pulse rounded-lg bg-emerald-900/40 px-4 py-2 text-center text-lg font-bold text-emerald-400">
                        +{{ lastPointsFlash }} pts!
                    </div>

                    <!-- Target locked (ladder) -->
                    <div v-if="targetLocked" class="mb-4 rounded-lg border border-amber-700 bg-amber-900/40 px-4 py-3 text-center">
                        <p class="font-bold text-amber-400">Target Locked</p>
                        <p class="text-sm text-amber-300">Previous target must be hit first (ladder mode).</p>
                        <button @click="skipToNextShooterOrStage" class="mt-2 rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white">
                            End Stage for Shooter
                        </button>
                    </div>

                    <!-- HIT / MISS / END buttons -->
                    <div v-if="!targetLocked" class="mt-auto space-y-3">
                        <div class="grid grid-cols-2 gap-4">
                            <button @click="recordShot('hit')"
                                class="flex h-28 flex-col items-center justify-center rounded-2xl bg-emerald-600 text-white shadow-lg transition-all active:scale-95 active:bg-emerald-700">
                                <svg class="mb-1 h-10 w-10" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                <span class="text-2xl font-black">HIT</span>
                            </button>
                            <button @click="recordShot('miss')"
                                class="flex h-28 flex-col items-center justify-center rounded-2xl bg-red-700 text-white shadow-lg transition-all active:scale-95 active:bg-red-800">
                                <svg class="mb-1 h-10 w-10" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                                <span class="text-2xl font-black">MISS</span>
                            </button>
                        </div>
                        <button @click="endStageForShooter"
                            class="w-full rounded-xl border border-border py-3 text-sm font-medium text-muted transition-colors hover:bg-surface">
                            End Stage / Not Taken
                        </button>
                    </div>

                    <!-- Nav -->
                    <div class="mt-3 flex gap-3">
                        <button @click="goBack" :disabled="isFirst"
                            class="flex-1 rounded-xl border border-border py-3 text-sm font-medium transition-colors hover:bg-surface disabled:cursor-not-allowed disabled:opacity-30">
                            &larr; Previous
                        </button>
                    </div>
                </div>
            </main>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useMatchStore } from '../stores/matchStore';
import { useElrScoringStore } from '../stores/elrScoringStore';
import OnlineIndicator from '../components/OnlineIndicator.vue';
import SyncBadge from '../components/SyncBadge.vue';
import DeviceLockBanner from '../components/DeviceLockBanner.vue';

const props = defineProps({
    matchId: { type: Number, required: true },
});

const matchStore = useMatchStore();
const elrStore = useElrScoringStore();
const ready = ref(false);
const matchComplete = ref(false);
const lastPointsFlash = ref(null);
let flashTimeout = null;

const currentStageIndex = ref(0);
const currentShooterIndex = ref(0);
const currentTargetIndex = ref(0);
const currentShotNumber = ref(1);

const stages = computed(() => matchStore.currentMatch?.elr_stages ?? []);
const currentStage = computed(() => stages.value[currentStageIndex.value]);
const currentTargets = computed(() => currentStage.value?.targets ?? []);
const currentTarget = computed(() => currentTargets.value[currentTargetIndex.value]);
const shooters = computed(() => matchStore.allShooters);
const currentShooter = computed(() => shooters.value[currentShooterIndex.value]);

const isFirst = computed(() => currentStageIndex.value === 0 && currentShooterIndex.value === 0 && currentTargetIndex.value === 0 && currentShotNumber.value === 1);

const profile = computed(() => currentStage.value?.profile ?? null);

const pointsForCurrentShot = computed(() => {
    if (!currentTarget.value || !profile.value) return 0;
    const mult = profile.value.multipliers?.[currentShotNumber.value - 1] ?? 0;
    return Math.round(parseFloat(currentTarget.value.base_points) * mult * 100) / 100;
});

const targetLocked = computed(() => {
    if (!currentStage.value || currentStage.value.stage_type !== 'ladder') return false;
    if (currentTargetIndex.value === 0) return false;
    const prevTarget = currentTargets.value[currentTargetIndex.value - 1];
    if (!prevTarget || !prevTarget.must_hit_to_advance) return false;
    const prevShots = elrStore.getShotsForTarget(currentShooter.value?.id, prevTarget.id);
    return !prevShots.some(s => s.result === 'hit');
});

const progressPercent = computed(() => {
    const total = stages.value.length * shooters.value.length * (currentTargets.value.length || 1);
    const current = currentStageIndex.value * shooters.value.length * (currentTargets.value.length || 1)
        + currentShooterIndex.value * (currentTargets.value.length || 1)
        + currentTargetIndex.value;
    return total > 0 ? Math.round((current / total) * 100) : 0;
});

function flashPoints(pts) {
    lastPointsFlash.value = pts;
    clearTimeout(flashTimeout);
    flashTimeout = setTimeout(() => { lastPointsFlash.value = null; }, 1500);
}

async function recordShot(result) {
    if (!currentShooter.value || !currentTarget.value) return;
    let points = 0;
    if (result === 'hit') {
        points = pointsForCurrentShot.value;
    }

    await elrStore.recordShot({
        matchId: props.matchId,
        shooterId: currentShooter.value.id,
        elrTargetId: currentTarget.value.id,
        shotNumber: currentShotNumber.value,
        result,
        pointsAwarded: points,
    });

    if (result === 'hit') {
        flashPoints(points);
        advanceAfterHit();
    } else {
        advanceAfterMiss();
    }
}

function advanceAfterHit() {
    advanceToNextTarget();
}

function advanceAfterMiss() {
    if (currentShotNumber.value < currentTarget.value.max_shots) {
        currentShotNumber.value++;
        return;
    }
    if (currentStage.value.stage_type === 'ladder' && currentTarget.value.must_hit_to_advance) {
        skipToNextShooterOrStage();
    } else {
        advanceToNextTarget();
    }
}

function advanceToNextTarget() {
    currentShotNumber.value = 1;
    if (currentTargetIndex.value < currentTargets.value.length - 1) {
        currentTargetIndex.value++;
        return;
    }
    skipToNextShooterOrStage();
}

function skipToNextShooterOrStage() {
    currentTargetIndex.value = 0;
    currentShotNumber.value = 1;
    if (currentShooterIndex.value < shooters.value.length - 1) {
        currentShooterIndex.value++;
        return;
    }
    currentShooterIndex.value = 0;
    if (currentStageIndex.value < stages.value.length - 1) {
        currentStageIndex.value++;
        return;
    }
    matchComplete.value = true;
}

function endStageForShooter() {
    skipToNextShooterOrStage();
}

function goBack() {
    if (currentShotNumber.value > 1) { currentShotNumber.value--; return; }
    if (currentTargetIndex.value > 0) { currentTargetIndex.value--; currentShotNumber.value = 1; return; }
    if (currentShooterIndex.value > 0) { currentShooterIndex.value--; currentTargetIndex.value = Math.max(0, currentTargets.value.length - 1); currentShotNumber.value = 1; return; }
    if (currentStageIndex.value > 0) { currentStageIndex.value--; currentShooterIndex.value = Math.max(0, shooters.value.length - 1); currentTargetIndex.value = 0; currentShotNumber.value = 1; }
}

let syncInterval;

onMounted(async () => {
    if (!matchStore.currentMatch || matchStore.currentMatch.id !== props.matchId) {
        await matchStore.fetchMatch(props.matchId);
    }
    await elrStore.initForMatch(props.matchId);
    ready.value = true;

    syncInterval = setInterval(() => {
        if (navigator.onLine && elrStore.pendingCount > 0) {
            elrStore.syncShots();
        }
    }, 15000);
});

onUnmounted(() => {
    clearInterval(syncInterval);
    clearTimeout(flashTimeout);
});
</script>
