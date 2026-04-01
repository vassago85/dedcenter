<template>
    <div class="flex min-h-screen flex-col bg-app text-primary">
        <!-- Header -->
        <header class="border-b border-border bg-surface px-4 py-3">
            <div class="mx-auto flex max-w-lg items-center gap-3">
                <button @click="handleHeaderBack" class="text-muted hover:text-primary">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                </button>
                <div class="min-w-0 flex-1">
                    <h1 class="truncate text-sm font-bold">{{ matchStore.currentMatch?.name }}</h1>
                    <div class="flex items-center gap-2 text-[11px]">
                        <template v-if="currentView === 'scoring' && currentStage">
                            <span class="rounded px-1.5 py-0.5 text-[10px] font-bold uppercase" :class="currentStage?.stage_type === 'ladder' ? 'bg-emerald-600 text-white' : 'bg-surface-2 text-muted'">
                                {{ currentStage?.stage_type?.toUpperCase() }}
                            </span>
                            <span class="text-muted">{{ currentStage?.label }}</span>
                            <span v-if="isMultiSquad && currentSquad" class="text-emerald-400">&bull; {{ currentSquad.name }}</span>
                        </template>
                        <template v-else-if="(currentView === 'squad-select' || currentView === 'squad-picker') && currentStage">
                            <span class="text-emerald-400">{{ currentStage?.label }}</span>
                        </template>
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

        <!-- STAGE SELECT -->
        <div v-else-if="currentView === 'stage-select'" class="flex flex-1 flex-col px-4 py-6">
            <div class="mx-auto w-full max-w-lg space-y-4">
                <div class="text-center">
                    <h2 class="text-xl font-bold">Select Stage</h2>
                    <p class="text-sm text-muted">Choose which stage to score</p>
                </div>
                <div class="space-y-3">
                    <button
                        v-for="(stage, idx) in stages"
                        :key="stage.id"
                        @click="selectStage(idx)"
                        class="flex w-full items-center justify-between rounded-xl border border-border bg-surface p-5 text-left transition-all hover:border-emerald-600 active:scale-[0.98]"
                    >
                        <div>
                            <span class="text-lg font-bold">{{ stage.label }}</span>
                            <div class="mt-1 flex items-center gap-2">
                                <span class="rounded px-1.5 py-0.5 text-[10px] font-bold uppercase" :class="stage.stage_type === 'ladder' ? 'bg-emerald-600 text-white' : 'bg-surface-2 text-muted'">
                                    {{ stage.stage_type }}
                                </span>
                                <span class="text-sm text-muted">{{ stage.targets?.length ?? 0 }} targets</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="text-xs font-bold" :class="elrStageProgress(idx).allDone ? 'text-green-400' : elrStageProgress(idx).someDone ? 'text-amber-400' : 'text-muted'">
                                {{ elrStageProgress(idx).label }}
                            </span>
                            <div v-if="elrStageProgress(idx).allDone" class="mt-1">
                                <svg class="ml-auto h-5 w-5 text-green-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </div>
                        </div>
                    </button>
                </div>
            </div>
        </div>

        <!-- SQUAD SELECT / SQUAD PICKER -->
        <div v-else-if="(currentView === 'squad-select' || currentView === 'squad-picker') && isMultiSquad" class="flex flex-1 flex-col px-4 py-6">
            <div class="mx-auto w-full max-w-lg space-y-4">
                <div class="text-center">
                    <p class="text-sm font-medium uppercase tracking-widest text-muted">
                        {{ currentView === 'squad-picker' ? 'Next Relay' : 'Select Relay' }}
                    </p>
                    <h2 class="mt-1 text-xl font-bold">{{ currentStage?.label }}</h2>
                    <p class="text-sm text-muted">Select a squad to score at this stage</p>
                </div>

                <!-- Recommended squad -->
                <div v-if="elrRecommendedSquad && !elrAllSquadsDoneAtStage" class="rounded-xl border-2 border-emerald-600 bg-emerald-600/10 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium uppercase text-emerald-400">Recommended</p>
                            <p class="text-lg font-bold">{{ elrRecommendedSquad.name }}</p>
                            <p class="text-xs text-muted">{{ activeShootersInSquad(elrRecommendedSquad) }} active shooters</p>
                        </div>
                        <button
                            @click="pickSquad(elrRecommendedSquadIndex)"
                            class="rounded-xl bg-emerald-600 px-6 py-3 text-sm font-bold text-white transition-colors hover:bg-emerald-700 active:scale-95"
                        >
                            Score Next
                        </button>
                    </div>
                </div>

                <!-- All squads scored -->
                <div v-if="elrAllSquadsDoneAtStage" class="rounded-xl border border-green-700/50 bg-green-900/20 p-4 text-center">
                    <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-green-600/20">
                        <svg class="h-6 w-6 text-green-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                    </div>
                    <p class="font-bold text-green-400">All squads scored at {{ currentStage?.label }}!</p>
                    <button
                        @click="goToStageSelect"
                        class="mt-3 w-full rounded-xl bg-emerald-600 py-3 font-semibold text-white transition-colors hover:bg-emerald-700"
                    >
                        Choose Another Stage
                    </button>
                </div>

                <!-- Full squad list -->
                <div class="rounded-xl border border-border bg-surface overflow-hidden">
                    <div class="border-b border-border px-4 py-2.5">
                        <p class="text-xs font-semibold uppercase tracking-wider text-muted">All Squads</p>
                    </div>
                    <div class="divide-y divide-border/50">
                        <button
                            v-for="item in elrSquadPickerItems"
                            :key="item.squad.id"
                            @click="pickSquad(item.index)"
                            class="flex w-full items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-surface-2/50 active:scale-[0.99]"
                            :class="{
                                'bg-emerald-600/5 border-l-4 border-l-emerald-500': item.isRecommended,
                                'border-l-4 border-l-transparent': !item.isRecommended,
                            }"
                        >
                            <div
                                class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg text-xs font-bold"
                                :class="{
                                    'bg-green-600/20 text-green-400': item.allDone,
                                    'bg-amber-600/20 text-amber-400': item.someDone && !item.allDone,
                                    'bg-surface-2 text-muted': !item.someDone,
                                }"
                            >
                                <template v-if="item.allDone">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </template>
                                <template v-else>{{ item.index + 1 }}</template>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium">
                                    {{ item.squad.name }}
                                    <span v-if="item.isRecommended && !item.allDone" class="ml-1.5 rounded bg-emerald-600/20 px-1.5 py-0.5 text-[10px] font-bold uppercase text-emerald-400">Next</span>
                                </p>
                                <p class="text-xs text-muted">{{ item.activeCount }} shooters</p>
                            </div>
                            <div class="text-right">
                                <span class="text-xs font-bold" :class="item.allDone ? 'text-green-400' : item.someDone ? 'text-amber-400' : 'text-muted'">
                                    {{ item.allDone ? 'Done' : item.someDone ? 'Partial' : 'Pending' }}
                                </span>
                            </div>
                            <svg class="h-4 w-4 flex-shrink-0 text-muted" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </button>
                    </div>
                </div>

                <button
                    @click="goToStageSelect"
                    class="block w-full rounded-xl border border-border py-3 text-center text-sm font-semibold text-muted transition-colors hover:bg-surface hover:text-primary"
                >
                    Change Stage
                </button>
            </div>
        </div>

        <!-- Squad summary -->
        <div v-else-if="currentView === 'squad-summary'" class="flex flex-1 flex-col px-4 py-6">
            <div class="mx-auto w-full max-w-lg space-y-4">
                <div class="text-center">
                    <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-green-600/20">
                        <svg class="h-8 w-8 text-green-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold">{{ currentSquad?.name }} &mdash; {{ currentStage?.label }} Complete</h2>
                </div>
                <div class="flex flex-col gap-3 pt-2">
                    <button @click="elrStore.syncShots()" class="w-full rounded-xl bg-green-600 py-3 font-semibold text-white transition-colors hover:bg-green-700">
                        Sync Scores
                    </button>
                    <button @click="dismissSquadSummary" class="w-full rounded-xl bg-emerald-600 py-3 font-semibold text-white transition-colors hover:bg-emerald-700">
                        Continue
                    </button>
                </div>
            </div>
        </div>

        <!-- Match complete -->
        <div v-else-if="currentView === 'complete'" class="flex flex-1 flex-col items-center justify-center gap-4 px-4 text-center">
            <div class="rounded-full bg-emerald-600/20 p-4">
                <svg class="h-12 w-12 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
            </div>
            <h2 class="text-2xl font-bold">ELR Scoring Complete!</h2>
            <div class="flex flex-col gap-3 pt-4">
                <button @click="elrStore.syncShots()" class="rounded-xl bg-emerald-600 px-6 py-3 font-semibold text-white">Sync Scores</button>
                <button @click="goToStageSelect" class="rounded-xl border border-border px-6 py-3 font-semibold text-primary">Back to Stage Select</button>
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
                        <span v-if="isMultiSquad">Squad {{ currentSquadIndex + 1 }}/{{ sortedSquads.length }}</span>
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
                        <p v-if="isMultiSquad && currentSquad" class="text-xs text-muted">{{ currentSquad.name }}</p>
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
                        <button @click="skipToNextShooterOrSquad" class="mt-2 rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white">
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

        <ScoringSponsorship />
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useMatchStore } from '../stores/matchStore';
import { useElrScoringStore } from '../stores/elrScoringStore';
import OnlineIndicator from '../components/OnlineIndicator.vue';
import SyncBadge from '../components/SyncBadge.vue';
import DeviceLockBanner from '../components/DeviceLockBanner.vue';
import ScoringSponsorship from '../components/ScoringSponsorship.vue';

const props = defineProps({
    matchId: { type: Number, required: true },
});

const router = useRouter();
const matchStore = useMatchStore();
const elrStore = useElrScoringStore();
const ready = ref(false);
const currentView = ref('stage-select');
const lastPointsFlash = ref(null);
let flashTimeout = null;

const currentStageIndex = ref(0);
const currentSquadIndex = ref(0);
const currentShooterIndex = ref(0);
const currentTargetIndex = ref(0);
const currentShotNumber = ref(1);

const ELR_STATE_KEY = 'dc_elr_state';

// ── Multi-squad detection ──
const sortedSquads = computed(() =>
    [...(matchStore.currentMatch?.squads ?? [])].sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0))
);
const isMultiSquad = computed(() => !matchStore.hasSquadLock && sortedSquads.value.length > 1);
const currentSquad = computed(() => isMultiSquad.value ? sortedSquads.value[currentSquadIndex.value] : null);

// ── Stages / targets ──
const stages = computed(() => matchStore.currentMatch?.elr_stages ?? []);
const currentStage = computed(() => stages.value[currentStageIndex.value]);
const currentTargets = computed(() => currentStage.value?.targets ?? []);
const currentTarget = computed(() => currentTargets.value[currentTargetIndex.value]);
const profile = computed(() => currentStage.value?.profile ?? null);

// ── Shooters: squad-aware ──
const shooters = computed(() => {
    if (isMultiSquad.value && currentSquad.value) {
        return (currentSquad.value.shooters ?? [])
            .filter(s => s.status === 'active')
            .map(s => ({ ...s, squadName: currentSquad.value.name }));
    }
    if (matchStore.hasSquadLock) return matchStore.squadShooters;
    return matchStore.allShooters;
});
const currentShooter = computed(() => shooters.value[currentShooterIndex.value]);

const isFirst = computed(() =>
    currentStageIndex.value === 0 &&
    currentSquadIndex.value === 0 &&
    currentShooterIndex.value === 0 &&
    currentTargetIndex.value === 0 &&
    currentShotNumber.value === 1
);

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
    const total = stages.value.length * (isMultiSquad.value ? sortedSquads.value.length : 1) * shooters.value.length * (currentTargets.value.length || 1);
    if (total === 0) return 0;
    let step = currentStageIndex.value * (isMultiSquad.value ? sortedSquads.value.length : 1) * shooters.value.length * (currentTargets.value.length || 1);
    if (isMultiSquad.value) {
        step += currentSquadIndex.value * shooters.value.length * (currentTargets.value.length || 1);
    }
    step += currentShooterIndex.value * (currentTargets.value.length || 1);
    step += currentTargetIndex.value;
    return Math.round((step / total) * 100);
});

// ── Persistence ──
function saveElrProgress() {
    try {
        const state = {
            matchId: props.matchId,
            view: currentView.value,
            stageIdx: currentStageIndex.value,
            squadIdx: currentSquadIndex.value,
            shooterIdx: currentShooterIndex.value,
            targetIdx: currentTargetIndex.value,
            shotNum: currentShotNumber.value,
        };
        localStorage.setItem(ELR_STATE_KEY, JSON.stringify(state));
    } catch { /* ignore */ }
}

function restoreElrProgress() {
    try {
        const raw = localStorage.getItem(ELR_STATE_KEY);
        if (!raw) return false;
        const state = JSON.parse(raw);
        if (state.matchId !== props.matchId) return false;

        const validViews = ['stage-select', 'squad-select', 'squad-picker', 'scoring', 'squad-summary', 'complete'];
        currentView.value = validViews.includes(state.view) ? state.view : 'stage-select';
        currentStageIndex.value = Math.min(state.stageIdx ?? 0, stages.value.length - 1);
        currentSquadIndex.value = Math.min(state.squadIdx ?? 0, Math.max(0, sortedSquads.value.length - 1));
        currentShooterIndex.value = state.shooterIdx ?? 0;
        currentTargetIndex.value = state.targetIdx ?? 0;
        currentShotNumber.value = state.shotNum ?? 1;
        return true;
    } catch { return false; }
}

function clearElrProgress() {
    try { localStorage.removeItem(ELR_STATE_KEY); } catch { /* ignore */ }
}

// ── Stage progress for stage-select ──
function elrStageProgress(stageIdx) {
    if (!isMultiSquad.value) {
        const done = isSquadDoneAtStage(null, stageIdx);
        return { allDone: done, someDone: done, label: done ? 'Scored' : 'Pending' };
    }
    const totalSquads = sortedSquads.value.length;
    let scoredSquads = 0;
    for (const sq of sortedSquads.value) {
        if (isSquadDoneAtStage(sq, stageIdx)) scoredSquads++;
    }
    return {
        allDone: totalSquads > 0 && scoredSquads === totalSquads,
        someDone: scoredSquads > 0,
        label: `${scoredSquads}/${totalSquads} squads`,
    };
}

// ── Squad picker logic ──
function activeShootersInSquad(squad) {
    return (squad?.shooters ?? []).filter(s => s.status === 'active').length;
}

function isSquadDoneAtStage(squad, stageIdx) {
    const stage = stages.value[stageIdx];
    if (!stage) return false;
    const targets = stage.targets ?? [];
    const active = squad
        ? (squad.shooters ?? []).filter(s => s.status === 'active')
        : matchStore.allShooters;
    if (active.length === 0) return true;
    return active.every(shooter =>
        targets.every(target => elrStore.getShotsForTarget(shooter.id, target.id).length > 0)
    );
}

const elrRecommendedSquad = computed(() => {
    if (!isMultiSquad.value) return null;
    const stageIdx = currentStageIndex.value;
    const concurrentRelays = matchStore.currentMatch?.concurrent_relays ?? 2;
    const currentIdx = currentSquadIndex.value;
    const squads = sortedSquads.value;
    const stride = Math.max(1, concurrentRelays);

    for (let offset = stride; offset < squads.length; offset += stride) {
        const idx = (currentIdx + offset) % squads.length;
        if (!isSquadDoneAtStage(squads[idx], stageIdx)) return squads[idx];
    }
    for (let i = 0; i < squads.length; i++) {
        if (i === currentIdx) continue;
        if (!isSquadDoneAtStage(squads[i], stageIdx)) return squads[i];
    }
    return null;
});

const elrRecommendedSquadIndex = computed(() => {
    if (!elrRecommendedSquad.value) return -1;
    return sortedSquads.value.findIndex(s => s.id === elrRecommendedSquad.value.id);
});

const elrAllSquadsDoneAtStage = computed(() => {
    if (!isMultiSquad.value) return false;
    return sortedSquads.value.every(s => isSquadDoneAtStage(s, currentStageIndex.value));
});

const elrSquadPickerItems = computed(() => {
    if (!isMultiSquad.value) return [];
    const stageIdx = currentStageIndex.value;
    const recId = elrRecommendedSquad.value?.id;
    return sortedSquads.value.map((squad, idx) => {
        const done = isSquadDoneAtStage(squad, stageIdx);
        const active = activeShootersInSquad(squad);
        const partial = !done && (squad.shooters ?? []).filter(s => s.status === 'active').some(shooter => {
            const stage = stages.value[stageIdx];
            return (stage?.targets ?? []).some(target => elrStore.getShotsForTarget(shooter.id, target.id).length > 0);
        });
        return {
            squad,
            index: idx,
            activeCount: active,
            allDone: done,
            someDone: partial,
            isRecommended: squad.id === recId,
        };
    });
});

// ── Navigation ──
function selectStage(idx) {
    currentStageIndex.value = idx;
    currentSquadIndex.value = 0;
    currentShooterIndex.value = 0;
    currentTargetIndex.value = 0;
    currentShotNumber.value = 1;
    if (isMultiSquad.value) {
        currentView.value = 'squad-select';
    } else {
        currentView.value = 'scoring';
    }
    saveElrProgress();
}

function pickSquad(squadIndex) {
    currentSquadIndex.value = squadIndex;
    currentShooterIndex.value = 0;
    currentTargetIndex.value = 0;
    currentShotNumber.value = 1;
    currentView.value = 'scoring';
    saveElrProgress();
}

function goToStageSelect() {
    currentView.value = 'stage-select';
    saveElrProgress();
}

function handleHeaderBack() {
    if (currentView.value === 'scoring') {
        if (isMultiSquad.value) {
            currentView.value = 'squad-select';
        } else {
            currentView.value = 'stage-select';
        }
        saveElrProgress();
    } else if (currentView.value === 'squad-select' || currentView.value === 'squad-picker') {
        currentView.value = 'stage-select';
        saveElrProgress();
    } else if (currentView.value === 'stage-select') {
        router.push({ name: 'match-overview', params: { matchId: props.matchId } });
    } else {
        router.push({ name: 'match-overview', params: { matchId: props.matchId } });
    }
}

// ── Scoring actions ──
function flashPoints(pts) {
    lastPointsFlash.value = pts;
    clearTimeout(flashTimeout);
    flashTimeout = setTimeout(() => { lastPointsFlash.value = null; }, 1500);
}

async function recordShot(result) {
    if (!currentShooter.value || !currentTarget.value) return;
    let points = 0;
    if (result === 'hit') points = pointsForCurrentShot.value;

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
        saveElrProgress();
        return;
    }
    if (currentStage.value.stage_type === 'ladder' && currentTarget.value.must_hit_to_advance) {
        skipToNextShooterOrSquad();
    } else {
        advanceToNextTarget();
    }
}

function advanceToNextTarget() {
    currentShotNumber.value = 1;
    if (currentTargetIndex.value < currentTargets.value.length - 1) {
        currentTargetIndex.value++;
        saveElrProgress();
        return;
    }
    skipToNextShooterOrSquad();
}

function skipToNextShooterOrSquad() {
    currentTargetIndex.value = 0;
    currentShotNumber.value = 1;
    if (currentShooterIndex.value < shooters.value.length - 1) {
        currentShooterIndex.value++;
        saveElrProgress();
        return;
    }
    currentShooterIndex.value = 0;

    if (isMultiSquad.value) {
        currentView.value = 'squad-summary';
        saveElrProgress();
        return;
    }

    if (currentStageIndex.value < stages.value.length - 1) {
        currentStageIndex.value++;
        saveElrProgress();
        return;
    }
    currentView.value = 'complete';
    clearElrProgress();
}

function endStageForShooter() {
    skipToNextShooterOrSquad();
}

function dismissSquadSummary() {
    if (isMultiSquad.value) {
        currentView.value = 'squad-picker';
        saveElrProgress();
        return;
    }
    if (currentStageIndex.value < stages.value.length - 1) {
        currentStageIndex.value++;
        currentView.value = 'scoring';
        saveElrProgress();
        return;
    }
    currentView.value = 'complete';
    clearElrProgress();
}

function goBack() {
    if (currentShotNumber.value > 1) { currentShotNumber.value--; saveElrProgress(); return; }
    if (currentTargetIndex.value > 0) { currentTargetIndex.value--; currentShotNumber.value = 1; saveElrProgress(); return; }
    if (currentShooterIndex.value > 0) { currentShooterIndex.value--; currentTargetIndex.value = Math.max(0, currentTargets.value.length - 1); currentShotNumber.value = 1; saveElrProgress(); return; }
    if (isMultiSquad.value && currentSquadIndex.value > 0) {
        currentSquadIndex.value--;
        const prevActive = (sortedSquads.value[currentSquadIndex.value]?.shooters ?? []).filter(s => s.status === 'active');
        currentShooterIndex.value = Math.max(0, prevActive.length - 1);
        currentTargetIndex.value = 0;
        currentShotNumber.value = 1;
        saveElrProgress();
        return;
    }
    if (currentStageIndex.value > 0) {
        currentStageIndex.value--;
        if (isMultiSquad.value) {
            currentSquadIndex.value = sortedSquads.value.length - 1;
            const prevActive = (sortedSquads.value[currentSquadIndex.value]?.shooters ?? []).filter(s => s.status === 'active');
            currentShooterIndex.value = Math.max(0, prevActive.length - 1);
        } else {
            currentShooterIndex.value = Math.max(0, shooters.value.length - 1);
        }
        currentTargetIndex.value = 0;
        currentShotNumber.value = 1;
        saveElrProgress();
    }
}

let syncInterval;

onMounted(async () => {
    if (!matchStore.currentMatch || matchStore.currentMatch.id !== props.matchId) {
        await matchStore.fetchMatch(props.matchId);
    }
    await elrStore.initForMatch(props.matchId);
    ready.value = true;

    if (!restoreElrProgress()) {
        currentView.value = (isMultiSquad.value || stages.value.length > 1)
            ? 'stage-select'
            : 'scoring';
    }

    syncInterval = setInterval(async () => {
        if (!navigator.onLine) return;
        if (elrStore.pendingCount > 0) {
            await elrStore.syncShots();
        }
        try {
            await matchStore.fetchMatch(props.matchId);
            await elrStore.refreshShots(props.matchId);
        } catch { /* offline or transient failure */ }
    }, 15000);
});

onUnmounted(() => {
    clearInterval(syncInterval);
    clearTimeout(flashTimeout);
});
</script>
