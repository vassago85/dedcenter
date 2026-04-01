<template>
    <div class="flex min-h-screen flex-col bg-slate-900 text-white">
        <!-- Global Header -->
        <header class="border-b border-slate-700 bg-slate-800 px-4 py-3">
            <div class="mx-auto flex max-w-2xl items-center gap-3">
                <button @click="goBack" class="p-2 text-slate-400 hover:text-white">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </button>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <span class="rounded bg-amber-600 px-2 py-0.5 text-xs font-bold uppercase">PRS</span>
                        <h1 class="truncate text-base font-bold">{{ matchStore.currentMatch?.name }}</h1>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <SyncBadge :pending="prsStore.pendingCount" :syncing="prsStore.syncing" @sync="prsStore.syncPendingResults()" />
                    <OnlineIndicator />
                </div>
            </div>
        </header>

        <DeviceLockBanner max-width-class="max-w-2xl" />

        <!-- Auth expired warning -->
        <div v-if="prsStore.authExpired" class="border-b border-amber-800 bg-amber-900/40 px-4 py-2">
            <div class="mx-auto flex max-w-2xl items-center gap-2 text-sm text-amber-300">
                <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
                <span>Session expired. Scores saved locally. Log in again to sync.</span>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="!ready" class="flex flex-1 items-center justify-center">
            <div class="h-10 w-10 animate-spin rounded-full border-4 border-slate-600 border-t-amber-500"></div>
        </div>

        <!-- SCREEN: Match Home -->
        <div v-else-if="prsStore.currentScreen === 'match-home'" class="flex flex-1 flex-col">
            <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col items-center justify-center gap-6 px-4 text-center">
                <h2 class="text-3xl font-bold">{{ matchStore.currentMatch?.name }}</h2>
                <p class="text-lg text-slate-400">{{ matchStore.currentMatch?.date }} &bull; {{ matchStore.currentMatch?.location }}</p>

                <div v-if="deviceLockMode !== 'open'" class="flex items-center gap-2 rounded-lg bg-slate-800 px-4 py-2 text-sm">
                    <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                    <span v-if="deviceLockMode === 'locked_to_stage'" class="text-slate-300">Locked to Stage: {{ lockedStageName }}</span>
                    <span v-else-if="deviceLockMode === 'locked_to_squad'" class="text-slate-300">Locked to Squad: {{ lockedSquadName }}</span>
                </div>

                <button @click="startScoring" class="mt-4 w-full max-w-sm rounded-2xl bg-red-600 px-8 py-5 text-xl font-bold text-white transition-all hover:bg-red-700 active:scale-[0.98]">
                    Start Scoring
                </button>

                <router-link
                    :to="{ name: 'match-overview', params: { matchId: props.matchId } }"
                    class="mt-2 text-sm text-slate-500 hover:text-slate-300 transition-colors"
                >
                    &larr; Match Overview
                </router-link>
            </div>
        </div>

        <!-- SCREEN: Squad Select (first step — PRS is squad-first) -->
        <div v-else-if="prsStore.currentScreen === 'squad-select'" class="flex flex-1 flex-col px-4 py-6">
            <div class="mx-auto w-full max-w-2xl">
                <h2 class="mb-2 text-xl font-bold">Select Squad</h2>
                <p class="mb-4 text-sm text-slate-400">Choose the squad you are scoring</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <button
                        v-for="squad in squads"
                        :key="squad.id"
                        @click="selectSquad(squad)"
                        class="flex flex-col gap-1 rounded-xl border border-slate-700 bg-slate-800 p-5 text-left transition-all hover:border-amber-600 hover:bg-slate-700/80 active:scale-[0.98]"
                    >
                        <span class="text-lg font-bold">{{ squad.name }}</span>
                        <span class="text-sm text-slate-400">{{ squad.shooters.length }} shooters</span>
                        <div class="mt-1 text-xs text-slate-500">{{ squadOverallProgress(squad.id) }}</div>
                    </button>
                </div>
            </div>
        </div>

        <!-- SCREEN: Stage Select (second step — after squad is chosen) -->
        <div v-else-if="prsStore.currentScreen === 'stage-select'" class="flex flex-1 flex-col px-4 py-6">
            <div class="mx-auto w-full max-w-2xl">
                <h2 class="mb-2 text-xl font-bold">Select Stage</h2>
                <p v-if="selectedSquadObj" class="mb-4 text-sm text-slate-400">{{ selectedSquadObj.name }}</p>
                <div class="space-y-3">
                    <button
                        v-for="ts in targetSets"
                        :key="ts.id"
                        @click="selectStage(ts)"
                        class="flex w-full items-center justify-between rounded-xl border border-slate-700 bg-slate-800 p-5 text-left transition-all hover:border-amber-600 active:scale-[0.98]"
                    >
                        <div>
                            <span class="text-lg font-bold">{{ ts.display_name || ts.label }}</span>
                            <div class="mt-1 flex items-center gap-2">
                                <span class="text-sm text-slate-400">{{ ts.total_shots || ts.gongs?.length || '?' }} shots</span>
                                <span v-if="ts.is_timed_stage" class="rounded bg-red-600 px-1.5 py-0.5 text-[10px] font-bold uppercase">Timed</span>
                                <span v-if="ts.is_tiebreaker" class="rounded bg-orange-600 px-1.5 py-0.5 text-[10px] font-bold uppercase">Tiebreaker</span>
                            </div>
                        </div>
                        <div class="text-right text-sm text-slate-500">
                            {{ stageSquadProgress(ts.id) }}
                        </div>
                    </button>
                </div>
            </div>
        </div>

        <!-- SCREEN: Shooter List -->
        <div v-else-if="prsStore.currentScreen === 'shooter-list'" class="flex flex-1 flex-col px-4 py-4">
            <div class="mx-auto w-full max-w-2xl">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold">{{ selectedSquadObj?.name }}</h2>
                        <p class="text-sm text-slate-400">{{ selectedStageObj?.display_name || selectedStageObj?.label }}</p>
                    </div>
                    <div class="flex gap-2">
                        <button v-if="deviceLockMode !== 'locked_to_stage'" @click="prsStore.navigateTo('stage-select'); savePrsProgress()" class="rounded-lg border border-slate-600 px-3 py-2 text-xs font-medium hover:bg-slate-800">Change Stage</button>
                        <button v-if="deviceLockMode === 'open'" @click="prsStore.navigateTo('squad-select'); savePrsProgress()" class="rounded-lg border border-slate-600 px-3 py-2 text-xs font-medium hover:bg-slate-800">Change Squad</button>
                    </div>
                </div>

                <!-- All shooters completed banner -->
                <div v-if="allShootersDoneAtStage" class="mb-4 rounded-xl border border-green-700/50 bg-green-900/20 p-4 text-center">
                    <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-green-600/20">
                        <svg class="h-6 w-6 text-green-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                    </div>
                    <p class="font-bold text-green-400">All shooters scored at this stage!</p>
                    <button
                        v-if="deviceLockMode !== 'locked_to_stage'"
                        @click="prsStore.navigateTo('stage-select'); savePrsProgress()"
                        class="mt-3 w-full rounded-xl bg-red-600 py-3 font-semibold text-white transition-colors hover:bg-red-700"
                    >
                        Choose Next Stage
                    </button>
                </div>

                <div class="space-y-2">
                    <button
                        v-for="shooter in currentShooters"
                        :key="shooter.id"
                        @click="openScoring(shooter)"
                        class="flex w-full items-center justify-between rounded-xl border border-slate-700 bg-slate-800 p-4 text-left transition-all hover:border-amber-600 active:scale-[0.98]"
                    >
                        <div class="flex items-center gap-3">
                            <div>
                                <span class="text-lg font-bold">{{ shooter.name }}</span>
                                <span v-if="shooter.bib_number" class="ml-2 text-sm text-slate-500">#{{ shooter.bib_number }}</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span v-if="getShooterCompletion(shooter.id)" class="text-sm font-bold text-green-400">
                                {{ getShooterCompletion(shooter.id).hits }}/{{ selectedStageObj?.total_shots || selectedStageObj?.gongs?.length }} hits
                            </span>
                            <span
                                class="rounded-full px-3 py-1 text-xs font-bold uppercase"
                                :class="getStatusClass(shooter.id)"
                            >
                                {{ getStatusLabel(shooter.id) }}
                            </span>
                        </div>
                    </button>
                </div>
            </div>
        </div>

        <!-- SCREEN: Scoring -->
        <template v-else-if="prsStore.currentScreen === 'scoring'">
            <div class="flex flex-1 flex-col">
                <!-- Scoring Header -->
                <div class="border-b border-slate-700 bg-slate-800/50 px-4 py-3">
                    <div class="mx-auto max-w-2xl">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-2xl font-bold">{{ selectedShooterObj?.name }}</p>
                                <p class="text-sm text-slate-400">
                                    {{ matchStore.currentMatch?.name }}
                                    &bull; {{ selectedStageObj?.display_name || selectedStageObj?.label }}
                                    &bull; {{ selectedSquadObj?.name }}
                                    &bull; {{ prsStore.shots.length }} Shots
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                <span v-if="selectedStageObj?.is_timed_stage" class="rounded bg-red-600 px-2 py-1 text-[10px] font-bold uppercase">Timed</span>
                                <span v-if="selectedStageObj?.is_tiebreaker" class="rounded bg-orange-600 px-2 py-1 text-[10px] font-bold uppercase">Tiebreaker</span>
                                <span v-if="deviceLockMode !== 'open'" class="rounded bg-slate-600 px-2 py-1 text-[10px] font-bold uppercase">Locked</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Scoring Body -->
                <div class="flex-1 overflow-y-auto px-4 py-4">
                    <div class="mx-auto max-w-2xl space-y-4">
                        <!-- Score Summary -->
                        <div class="flex items-center justify-center gap-6 text-base">
                            <span class="font-bold text-green-400">{{ prsStore.hits }} <span class="font-normal text-sm">hits</span></span>
                            <span class="font-bold text-red-400">{{ prsStore.misses }} <span class="font-normal text-sm">misses</span></span>
                            <span class="font-bold text-slate-500">{{ prsStore.notTaken }} <span class="font-normal text-sm">not taken</span></span>
                        </div>

                        <!-- Shot Table -->
                        <div class="rounded-xl border border-slate-700 bg-slate-800">
                            <div class="max-h-[50vh] overflow-y-auto">
                                <div
                                    v-for="(shot, idx) in prsStore.shots"
                                    :key="shot.shot_number"
                                    @click="prsStore.goToShot(idx)"
                                    class="flex items-center border-b border-slate-700/50 px-4 py-2.5 transition-colors cursor-pointer"
                                    :class="idx === prsStore.currentShotIndex ? 'bg-amber-600/10 border-l-4 border-l-amber-500' : 'border-l-4 border-l-transparent hover:bg-slate-700/30'"
                                >
                                    <span class="w-16 text-sm font-bold text-slate-400">Shot {{ shot.shot_number }}</span>
                                    <span
                                        class="ml-auto rounded px-3 py-1 text-sm font-bold uppercase"
                                        :class="{
                                            'bg-green-600/20 text-green-400': shot.result === 'hit',
                                            'bg-red-600/20 text-red-400': shot.result === 'miss',
                                            'bg-slate-700/50 text-slate-500': shot.result === 'not_taken',
                                        }"
                                    >
                                        {{ shot.result === 'not_taken' ? 'Not Taken' : shot.result }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Target Info (collapsible, read-only) -->
                        <details v-if="selectedStageObj?.stage_targets?.length" class="rounded-xl border border-slate-700 bg-slate-800">
                            <summary class="cursor-pointer px-4 py-3 text-sm font-medium text-slate-400">Target Info</summary>
                            <div class="border-t border-slate-700 px-4 py-2">
                                <div v-for="t in selectedStageObj.stage_targets" :key="t.id" class="flex items-center justify-between py-1.5 text-sm">
                                    <span class="text-white">{{ t.target_name || `Target ${t.sequence_number}` }}</span>
                                    <span class="text-slate-400">
                                        <template v-if="t.distance_meters">{{ t.distance_meters }}m</template>
                                        <template v-if="t.target_size_mm"> &bull; {{ t.target_size_mm }}mm</template>
                                        <template v-if="t.target_size_mrad"> ({{ t.target_size_mrad }} mrad)</template>
                                    </span>
                                </div>
                            </div>
                        </details>
                    </div>
                </div>

                <!-- Fixed Bottom Actions -->
                <div class="border-t border-slate-700 bg-slate-800 px-4 py-3">
                    <div class="mx-auto max-w-2xl space-y-3">
                        <!-- Hit / Miss — primary action, top of fixed area for speed -->
                        <div class="grid grid-cols-2 gap-3">
                            <button @click="recordHit" class="rounded-2xl bg-green-600 py-5 text-xl font-bold text-white transition-all hover:bg-green-700 active:scale-[0.97]">
                                HIT
                            </button>
                            <button @click="recordMiss" class="rounded-2xl bg-red-600 py-5 text-xl font-bold text-white transition-all hover:bg-red-700 active:scale-[0.97]">
                                MISS
                            </button>
                        </div>
                        <!-- Undo -->
                        <button @click="undoShot" class="w-full rounded-xl border border-slate-600 py-3 text-sm font-bold text-slate-300 transition-colors hover:bg-slate-700 active:scale-[0.98]">
                            Undo Last Shot
                        </button>
                        <!-- Complete Stage -->
                        <button @click="handleCompleteStage" class="w-full rounded-2xl bg-red-600 py-4 text-lg font-bold text-white transition-all hover:bg-red-700 active:scale-[0.98]">
                            COMPLETE STAGE
                        </button>
                        <p v-if="completeError" class="text-center text-sm text-red-400">{{ completeError }}</p>

                        <!-- Timer — always visible, required/optional based on stage type -->
                        <div class="rounded-xl border bg-slate-900 p-3" :class="stageRequiresTime ? 'border-amber-600/30' : 'border-slate-700'">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-xs font-medium text-slate-400">
                                    Stage Time <span :class="stageRequiresTime ? 'text-red-400' : 'text-slate-500'">({{ stageRequiresTime ? 'Required' : 'Optional' }})</span>
                                </p>
                                <div class="flex gap-1">
                                    <button @click="prsStore.timerMode = 'app'" class="rounded px-2 py-0.5 text-[10px] font-bold uppercase" :class="prsStore.timerMode === 'app' ? 'bg-amber-600 text-white' : 'bg-slate-700 text-slate-400'">Timer</button>
                                    <button @click="prsStore.timerMode = 'manual'" class="rounded px-2 py-0.5 text-[10px] font-bold uppercase" :class="prsStore.timerMode === 'manual' ? 'bg-amber-600 text-white' : 'bg-slate-700 text-slate-400'">Manual</button>
                                </div>
                            </div>
                            <div v-if="prsStore.timerMode === 'app'" class="flex items-center gap-3">
                                <p class="flex-1 text-center font-mono text-3xl font-bold tracking-wider" :class="prsStore.isTimerRunning ? 'text-amber-400' : 'text-white'">
                                    {{ formattedTime }}
                                </p>
                                <div class="flex gap-1.5">
                                    <button @click="startTimer" :disabled="prsStore.isTimerRunning" class="rounded-lg px-3 py-2 text-xs font-bold transition-colors" :class="prsStore.isTimerRunning ? 'bg-slate-700 text-slate-500' : 'bg-green-600 text-white hover:bg-green-700'">Start</button>
                                    <button @click="stopTimer" :disabled="!prsStore.isTimerRunning" class="rounded-lg px-3 py-2 text-xs font-bold transition-colors" :class="!prsStore.isTimerRunning ? 'bg-slate-700 text-slate-500' : 'bg-red-600 text-white hover:bg-red-700'">Stop</button>
                                    <button @click="resetTimer" class="rounded-lg bg-slate-700 px-3 py-2 text-xs font-bold text-white hover:bg-slate-600">Reset</button>
                                </div>
                            </div>
                            <div v-else class="flex items-center gap-2">
                                <input
                                    ref="timeInput"
                                    type="text"
                                    inputmode="numeric"
                                    :value="prsStore.rawDigits"
                                    @input="onDigitInput"
                                    @keydown="onDigitKeydown"
                                    placeholder="Digits e.g. 6532 = 65.32s"
                                    class="flex-1 rounded-lg border border-slate-600 bg-slate-900 px-3 py-2 text-center font-mono text-xl text-white placeholder-slate-600 tracking-widest focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                                />
                                <span v-if="prsStore.rawTimeSeconds" class="font-mono text-sm text-slate-400 whitespace-nowrap">{{ formattedTime }}</span>
                                <button v-if="prsStore.rawDigits" @click="clearTimeInput" class="rounded bg-slate-700 px-2 py-1.5 text-xs font-bold text-slate-400 hover:bg-slate-600">Clear</button>
                            </div>
                            <p v-if="selectedStageObj?.par_time_seconds" class="mt-1.5 text-center text-[10px] text-slate-500">
                                Par: {{ formatTime(selectedStageObj.par_time_seconds) }}
                            </p>
                        </div>

                        <!-- Low time warning -->
                        <div v-if="showLowTimeWarning" class="rounded-lg border border-amber-500/50 bg-amber-900/30 px-3 py-2 text-center">
                            <p class="text-sm font-medium text-amber-400">Time is under 15 seconds. Is this correct?</p>
                            <div class="mt-2 flex justify-center gap-3">
                                <button @click="confirmLowTime" class="rounded-lg bg-amber-600 px-4 py-1.5 text-xs font-bold text-white hover:bg-amber-700">Yes, it's correct</button>
                                <button @click="cancelLowTime" class="rounded-lg bg-slate-700 px-4 py-1.5 text-xs font-bold text-white hover:bg-slate-600">No, fix it</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <ScoringSponsorship />
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useMatchStore } from '../stores/matchStore';
import { usePrsScoringStore } from '../stores/prsScoringStore';
import OnlineIndicator from '../components/OnlineIndicator.vue';
import SyncBadge from '../components/SyncBadge.vue';
import DeviceLockBanner from '../components/DeviceLockBanner.vue';
import ScoringSponsorship from '../components/ScoringSponsorship.vue';

const props = defineProps({
    matchId: { type: Number, required: true },
});

const router = useRouter();

const matchStore = useMatchStore();
const prsStore = usePrsScoringStore();
const ready = ref(false);
const completeError = ref('');
const timeInput = ref(null);
const showLowTimeWarning = ref(false);
const lowTimeConfirmed = ref(false);

let timerInterval = null;
let syncInterval = null;

const squads = computed(() => matchStore.currentMatch?.squads ?? []);
const targetSets = computed(() => matchStore.currentMatch?.target_sets ?? []);
const deviceLockMode = computed(() => matchStore.currentMatch?.device_lock_mode ?? 'open');

const lockedStageName = computed(() => {
    const lock = readStageLock(props.matchId);
    if (!lock) return '';
    const ts = targetSets.value.find(s => s.id === lock.stageId);
    return ts?.display_name || ts?.label || `Stage ${lock.stageId}`;
});

const lockedSquadName = computed(() => {
    if (matchStore.lockedSquadName) return matchStore.lockedSquadName;
    return '';
});

const selectedSquadObj = computed(() => squads.value.find(s => s.id === prsStore.selectedSquadId));
const selectedStageObj = computed(() => targetSets.value.find(s => s.id === prsStore.selectedStageId));
const selectedShooterObj = computed(() => {
    if (!selectedSquadObj.value) return null;
    return selectedSquadObj.value.shooters.find(s => s.id === prsStore.selectedShooterId);
});

const currentShooters = computed(() => {
    if (!selectedSquadObj.value) return [];
    return selectedSquadObj.value.shooters.filter(s => s.status === 'active');
});

const stageRequiresTime = computed(() => {
    return selectedStageObj.value?.is_timed_stage || selectedStageObj.value?.is_tiebreaker;
});

const allShootersDoneAtStage = computed(() => {
    if (!selectedSquadObj.value || !prsStore.selectedStageId) return false;
    const active = currentShooters.value;
    if (active.length === 0) return false;
    return active.every(s => prsStore.stageCompletions.has(`${s.id}-${prsStore.selectedStageId}`));
});

const PRS_STATE_KEY = 'dc_prs_state';

function savePrsProgress() {
    try {
        const state = {
            matchId: props.matchId,
            screen: prsStore.currentScreen,
            stageId: prsStore.selectedStageId,
            squadId: prsStore.selectedSquadId,
        };
        localStorage.setItem(PRS_STATE_KEY, JSON.stringify(state));
    } catch { /* ignore */ }
}

function restorePrsProgress() {
    try {
        const raw = localStorage.getItem(PRS_STATE_KEY);
        if (!raw) return false;
        const state = JSON.parse(raw);
        if (state.matchId !== props.matchId) return false;
        const validScreens = ['squad-select', 'stage-select', 'shooter-list'];
        if (!validScreens.includes(state.screen)) return false;
        if (state.squadId) prsStore.selectSquad(state.squadId);
        if (state.stageId) prsStore.selectStage(state.stageId);
        prsStore.navigateTo(state.screen);
        return true;
    } catch { return false; }
}

function clearPrsProgress() {
    try { localStorage.removeItem(PRS_STATE_KEY); } catch { /* ignore */ }
}

function squadOverallProgress(squadId) {
    const squad = squads.value.find(s => s.id === squadId);
    if (!squad) return '';
    const active = (squad.shooters ?? []).filter(s => s.status === 'active');
    if (active.length === 0) return '';
    let totalCompleted = 0;
    const totalPossible = active.length * targetSets.value.length;
    for (const ts of targetSets.value) {
        totalCompleted += active.filter(s => prsStore.stageCompletions.has(`${s.id}-${ts.id}`)).length;
    }
    return `${totalCompleted}/${totalPossible} scored`;
}

function readStageLock(matchId) {
    try {
        const raw = localStorage.getItem('dc_locked_stage');
        if (!raw) return null;
        const lock = JSON.parse(raw);
        return lock.matchId === matchId ? lock : null;
    } catch { return null; }
}

// PRS flow: Squad first → Stage → Shooter → Score
function startScoring() {
    const lock = deviceLockMode.value;
    if (lock === 'locked_to_squad' && matchStore.lockedSquadId) {
        prsStore.selectSquad(matchStore.lockedSquadId);
        const stageLock = readStageLock(props.matchId);
        if (stageLock) {
            prsStore.selectStage(stageLock.stageId);
            prsStore.navigateTo('shooter-list');
        } else {
            prsStore.navigateTo('stage-select');
        }
    } else if (lock === 'locked_to_stage') {
        const stageLock = readStageLock(props.matchId);
        if (stageLock && matchStore.lockedSquadId) {
            prsStore.selectSquad(matchStore.lockedSquadId);
            prsStore.selectStage(stageLock.stageId);
            prsStore.navigateTo('shooter-list');
        } else if (stageLock) {
            prsStore.selectStage(stageLock.stageId);
            prsStore.navigateTo('squad-select');
        } else {
            prsStore.navigateTo('squad-select');
        }
    } else {
        prsStore.navigateTo('squad-select');
    }
    savePrsProgress();
}

function selectSquad(squad) {
    prsStore.selectSquad(squad.id);
    const stageLock = readStageLock(props.matchId);
    if (deviceLockMode.value === 'locked_to_stage' && stageLock) {
        prsStore.selectStage(stageLock.stageId);
        prsStore.navigateTo('shooter-list');
    } else {
        prsStore.navigateTo('stage-select');
    }
    savePrsProgress();
}

function selectStage(ts) {
    prsStore.selectStage(ts.id);
    prsStore.navigateTo('shooter-list');
    savePrsProgress();
}

function openScoring(shooter) {
    prsStore.selectShooter(shooter.id);
    const totalShots = selectedStageObj.value?.total_shots || selectedStageObj.value?.gongs?.length || 10;
    prsStore.initStage(totalShots);
    prsStore.resetTimer();
    completeError.value = '';
    showLowTimeWarning.value = false;
    lowTimeConfirmed.value = false;
    prsStore.navigateTo('scoring');
}

function goBack() {
    const s = prsStore.currentScreen;
    if (s === 'scoring') prsStore.navigateTo('shooter-list');
    else if (s === 'shooter-list') prsStore.navigateTo('stage-select');
    else if (s === 'stage-select') prsStore.navigateTo('squad-select');
    else if (s === 'squad-select') prsStore.navigateTo('match-home');
    else if (s === 'match-home') {
        router.push({ name: 'match-overview', params: { matchId: props.matchId } });
        return;
    }
    savePrsProgress();
}

function recordHit() { prsStore.recordShot('hit'); }
function recordMiss() { prsStore.recordShot('miss'); }
function undoShot() { prsStore.undoLastShot(); }

async function handleCompleteStage() {
    completeError.value = '';

    const time = prsStore.effectiveTime;
    if (stageRequiresTime.value && time > 0 && time < 15 && !lowTimeConfirmed.value) {
        showLowTimeWarning.value = true;
        return;
    }

    showLowTimeWarning.value = false;
    lowTimeConfirmed.value = false;

    const result = await prsStore.completeStage(
        props.matchId,
        prsStore.selectedStageId,
        prsStore.selectedShooterId,
        prsStore.selectedSquadId,
        selectedStageObj.value,
    );
    if (!result.success) {
        completeError.value = result.error;
        return;
    }
    stopTimerInternal();
    prsStore.navigateTo('shooter-list');
    savePrsProgress();
}

function confirmLowTime() {
    lowTimeConfirmed.value = true;
    showLowTimeWarning.value = false;
    handleCompleteStage();
}

function cancelLowTime() {
    showLowTimeWarning.value = false;
    lowTimeConfirmed.value = false;
}

function getShooterCompletion(shooterId) {
    return prsStore.stageCompletions.get(`${shooterId}-${prsStore.selectedStageId}`) ?? null;
}

function getStatusLabel(shooterId) {
    const c = getShooterCompletion(shooterId);
    return c ? 'Completed' : 'Not Started';
}

function getStatusClass(shooterId) {
    const c = getShooterCompletion(shooterId);
    return c ? 'bg-green-600/20 text-green-400' : 'bg-slate-700/50 text-slate-500';
}

function stageSquadProgress(stageId) {
    if (!prsStore.selectedSquadId) return '';
    const squad = squads.value.find(s => s.id === prsStore.selectedSquadId);
    if (!squad) return '';
    const active = squad.shooters.filter(s => s.status === 'active');
    const completed = active.filter(s => prsStore.stageCompletions.has(`${s.id}-${stageId}`));
    return `${completed.length}/${active.length} scored`;
}

// Timer
function startTimer() {
    if (prsStore.isTimerRunning) return;
    prsStore.isTimerRunning = true;
    prsStore.rawTimeSeconds = null;
    const startTime = performance.now() - prsStore.timerElapsed * 1000;
    timerInterval = setInterval(() => {
        prsStore.timerElapsed = (performance.now() - startTime) / 1000;
    }, 10);
}

function stopTimer() {
    if (!prsStore.isTimerRunning) return;
    stopTimerInternal();
    prsStore.rawTimeSeconds = parseFloat(prsStore.timerElapsed.toFixed(2));
}

function stopTimerInternal() {
    prsStore.isTimerRunning = false;
    if (timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
    }
}

function resetTimer() {
    stopTimerInternal();
    prsStore.resetTimer();
}

function digitsToSeconds(digits) {
    if (!digits || digits.length === 0) return 0;
    const padded = digits.padStart(3, '0');
    const whole = padded.slice(0, -2);
    const frac = padded.slice(-2);
    return parseFloat(`${whole || '0'}.${frac}`);
}

function formatTime(seconds) {
    if (seconds == null || seconds === 0) return '00:00.00';
    const t = parseFloat(seconds);
    const mins = Math.floor(t / 60);
    const secs = t % 60;
    return `${String(mins).padStart(2, '0')}:${secs.toFixed(2).padStart(5, '0')}`;
}

const formattedTime = computed(() => formatTime(prsStore.effectiveTime));

function onDigitInput(e) {
    const cleaned = e.target.value.replace(/\D/g, '');
    prsStore.rawDigits = cleaned;
    e.target.value = cleaned;
    prsStore.rawTimeSeconds = digitsToSeconds(cleaned);
}

function onDigitKeydown(e) {
    const allowed = ['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab', 'Enter'];
    if (allowed.includes(e.key)) return;
    if (!/^\d$/.test(e.key)) e.preventDefault();
}

function clearTimeInput() {
    prsStore.rawDigits = '';
    prsStore.rawTimeSeconds = null;
}

onMounted(async () => {
    if (!matchStore.currentMatch || matchStore.currentMatch.id !== props.matchId) {
        await matchStore.fetchMatch(props.matchId);
    }
    const prsResults = matchStore.currentMatch?.prs_stage_results ?? [];
    await prsStore.initForMatch(props.matchId, prsResults);
    ready.value = true;

    if (prsStore.currentScreen === 'match-home') {
        restorePrsProgress();
    }

    syncInterval = setInterval(async () => {
        if (!navigator.onLine) return;
        if (prsStore.pendingCount > 0) {
            await prsStore.syncPendingResults();
        }
        try {
            await matchStore.fetchMatch(props.matchId);
            const freshResults = matchStore.currentMatch?.prs_stage_results ?? [];
            await prsStore.refreshCompletions(props.matchId, freshResults);
        } catch { /* offline or transient failure */ }
    }, 15000);
});

onUnmounted(() => {
    if (syncInterval) clearInterval(syncInterval);
    if (timerInterval) clearInterval(timerInterval);
});
</script>
