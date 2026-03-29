<template>
    <div class="flex min-h-screen flex-col bg-slate-900 text-white">
        <!-- Header -->
        <header class="border-b border-slate-700 bg-slate-800 px-4 py-3">
            <div class="mx-auto flex max-w-lg items-center gap-3">
                <router-link
                    :to="{ name: 'match-overview', params: { matchId: props.matchId } }"
                    class="text-slate-400 hover:text-white"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </router-link>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <span class="rounded bg-amber-600 px-1.5 py-0.5 text-[10px] font-bold uppercase">PRS</span>
                        <h1 class="truncate text-sm font-bold">{{ matchStore.currentMatch?.name }}</h1>
                    </div>
                    <router-link
                        v-if="matchStore.lockedSquadName"
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

        <!-- Match complete -->
        <div v-else-if="matchComplete" class="flex flex-1 flex-col items-center justify-center gap-4 px-4 text-center">
            <div class="rounded-full bg-green-600/20 p-4">
                <svg class="h-12 w-12 text-green-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
            <h2 class="text-2xl font-bold">Scoring Complete!</h2>
            <p class="text-slate-400">All shooters have completed all stages.</p>
            <div class="flex gap-3 pt-4">
                <button
                    @click="scoringStore.syncScores()"
                    class="rounded-xl bg-green-600 px-6 py-3 font-semibold text-white transition-colors hover:bg-green-700"
                >
                    Sync Scores
                </button>
                <router-link
                    :to="{ name: 'scoreboard', params: { matchId: props.matchId } }"
                    class="rounded-xl border border-slate-600 px-6 py-3 font-semibold text-white transition-colors hover:bg-slate-800"
                >
                    View Scoreboard
                </router-link>
            </div>
        </div>

        <!-- PRS Scoring interface -->
        <template v-else>
            <!-- Progress -->
            <div class="bg-slate-800 px-4 py-2">
                <div class="mx-auto max-w-lg">
                    <div class="flex items-center justify-between text-xs text-slate-400">
                        <span>Stage {{ scoringStore.currentTargetSetIndex + 1 }}/{{ targetSets.length }}</span>
                        <span>Shooter {{ scoringStore.currentShooterIndex + 1 }}/{{ shooters.length }}</span>
                    </div>
                    <div class="mt-1 h-1.5 rounded-full bg-slate-700">
                        <div
                            class="h-full rounded-full bg-amber-600 transition-all duration-300"
                            :style="{ width: progressPercent + '%' }"
                        ></div>
                    </div>
                </div>
            </div>

            <!-- Stage info -->
            <div class="border-b border-slate-700 bg-slate-800/50 px-4 py-2">
                <div class="mx-auto max-w-lg flex items-center justify-between text-sm">
                    <span class="font-semibold text-amber-400">{{ currentTargetSet?.label }}</span>
                    <span class="text-slate-400">{{ currentTargetSet?.distance_meters }}m &bull; {{ currentGongs.length }} targets</span>
                </div>
            </div>

            <main class="flex flex-1 flex-col px-4 py-6">
                <div class="mx-auto flex w-full max-w-lg flex-1 flex-col">
                    <!-- Shooter -->
                    <div class="mb-6 rounded-xl border border-slate-700 bg-slate-800 p-4 text-center">
                        <p class="text-sm text-slate-400">Shooter</p>
                        <p class="text-2xl font-bold">{{ currentShooter?.name }}</p>
                        <div class="mt-1 flex items-center justify-center gap-3 text-sm text-slate-400">
                            <span v-if="currentShooter?.bib_number">Bib #{{ currentShooter.bib_number }}</span>
                            <span>{{ currentShooter?.squadName }}</span>
                        </div>
                    </div>

                    <!-- Target grid: three-button scoring per target -->
                    <div class="mb-4">
                        <p class="mb-2 text-sm font-medium text-slate-400">Tap to score each target</p>
                        <div class="space-y-2">
                            <div
                                v-for="gong in currentGongs"
                                :key="gong.id"
                                class="flex items-center gap-2 rounded-xl border border-slate-700 bg-slate-800 px-3 py-2"
                            >
                                <div class="min-w-[3rem] text-center">
                                    <span class="text-sm font-bold text-white">#{{ gong.number }}</span>
                                    <p v-if="gong.label" class="text-[9px] text-slate-500 leading-tight">{{ gong.label }}</p>
                                </div>
                                <div class="flex flex-1 gap-1.5">
                                    <button
                                        @click="setGongState(gong, true)"
                                        class="flex-1 rounded-lg py-2.5 text-xs font-bold uppercase tracking-wide transition-all active:scale-95"
                                        :class="getGongScore(gong) === true
                                            ? 'bg-green-600 text-white ring-2 ring-green-400'
                                            : 'bg-slate-700 text-slate-400 hover:bg-slate-600'"
                                    >
                                        Hit
                                    </button>
                                    <button
                                        @click="setGongState(gong, false)"
                                        class="flex-1 rounded-lg py-2.5 text-xs font-bold uppercase tracking-wide transition-all active:scale-95"
                                        :class="getGongScore(gong) === false
                                            ? 'bg-red-600 text-white ring-2 ring-red-400'
                                            : 'bg-slate-700 text-slate-400 hover:bg-slate-600'"
                                    >
                                        Miss
                                    </button>
                                    <button
                                        @click="setGongState(gong, null)"
                                        class="flex-1 rounded-lg py-2.5 text-[10px] font-bold uppercase tracking-wide transition-all active:scale-95"
                                        :class="getGongScore(gong) === null
                                            ? 'bg-amber-600/80 text-white ring-2 ring-amber-400'
                                            : 'bg-slate-700 text-slate-500 hover:bg-slate-600'"
                                    >
                                        Not Taken
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Score summary for this stage -->
                    <div class="mb-4 flex items-center justify-center gap-4 text-sm">
                        <span class="text-green-400 font-bold">{{ stageHits }} <span class="font-normal">hits</span></span>
                        <span class="text-red-400 font-bold">{{ stageMisses }} <span class="font-normal">misses</span></span>
                        <span class="text-amber-400/70">{{ stageNotTaken }} <span class="font-normal">not taken</span></span>
                    </div>

                    <!-- Timer -->
                    <div class="mb-4 rounded-xl border border-slate-700 bg-slate-800 p-4">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm font-medium text-slate-400">Stage Time</p>
                            <div class="flex items-center gap-2">
                                <button
                                    @click="timerMode = 'app'"
                                    class="rounded px-2 py-0.5 text-[10px] font-bold uppercase transition-colors"
                                    :class="timerMode === 'app' ? 'bg-amber-600 text-white' : 'bg-slate-700 text-slate-400'"
                                >App Timer</button>
                                <button
                                    @click="timerMode = 'manual'"
                                    class="rounded px-2 py-0.5 text-[10px] font-bold uppercase transition-colors"
                                    :class="timerMode === 'manual' ? 'bg-amber-600 text-white' : 'bg-slate-700 text-slate-400'"
                                >Manual</button>
                            </div>
                        </div>

                        <!-- Time display -->
                        <div class="flex items-center justify-center gap-3 my-3">
                            <p class="font-mono text-4xl font-bold tracking-wider" :class="timerRunning ? 'text-amber-400' : (parTimeApplied ? 'text-orange-400' : 'text-white')">
                                {{ formattedTime }}
                            </p>
                        </div>

                        <!-- Par time applied notice -->
                        <p v-if="parTimeApplied" class="text-center text-xs text-orange-400 mb-2">
                            Par time applied (not all targets engaged)
                        </p>

                        <!-- App Timer controls -->
                        <div v-if="timerMode === 'app'" class="grid grid-cols-3 gap-2">
                            <button
                                @click="startTimer"
                                :disabled="timerRunning"
                                class="rounded-lg py-2 text-sm font-medium transition-colors"
                                :class="timerRunning ? 'bg-slate-700 text-slate-500 cursor-not-allowed' : 'bg-green-600 text-white hover:bg-green-700'"
                            >Start</button>
                            <button
                                @click="stopTimer"
                                :disabled="!timerRunning"
                                class="rounded-lg py-2 text-sm font-medium transition-colors"
                                :class="!timerRunning ? 'bg-slate-700 text-slate-500 cursor-not-allowed' : 'bg-red-600 text-white hover:bg-red-700'"
                            >Stop</button>
                            <button
                                @click="resetTimer"
                                class="rounded-lg bg-slate-700 py-2 text-sm font-medium text-white transition-colors hover:bg-slate-600"
                            >Reset</button>
                        </div>

                        <!-- Manual implied-decimal input -->
                        <div v-if="timerMode === 'manual'" class="space-y-2">
                            <label class="text-xs text-slate-500">Type digits from timer (e.g. 105 = 1.05s):</label>
                            <div class="flex gap-2">
                                <input
                                    ref="timeInput"
                                    type="text"
                                    inputmode="numeric"
                                    :value="rawDigits"
                                    @input="onDigitInput"
                                    @keydown="onDigitKeydown"
                                    placeholder="Type digits..."
                                    class="flex-1 rounded-lg border border-slate-600 bg-slate-900 px-4 py-3 text-center font-mono text-2xl text-white placeholder-slate-600 tracking-widest focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                                />
                                <button
                                    @click="clearTimeInput"
                                    class="rounded-lg bg-slate-700 px-4 py-3 text-sm font-medium text-white transition-colors hover:bg-slate-600"
                                >Clear</button>
                            </div>
                        </div>

                        <!-- Par time hint -->
                        <p v-if="currentParTime" class="mt-2 text-center text-[10px] text-slate-500">
                            Par time: {{ formatSecondsDisplay(currentParTime) }}
                        </p>
                    </div>

                    <!-- Complete Stage / Nav -->
                    <div class="mt-auto space-y-3">
                        <button
                            @click="completeStage"
                            class="w-full rounded-xl py-4 text-lg font-bold transition-all bg-amber-600 text-white hover:bg-amber-700 active:scale-[0.98]"
                        >
                            Complete Stage &rarr;
                        </button>
                        <div class="flex gap-3">
                            <button
                                @click="goBack"
                                :disabled="isFirst"
                                class="flex-1 rounded-xl border border-slate-600 py-3 text-sm font-medium transition-colors hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-30"
                            >
                                &larr; Previous Shooter
                            </button>
                            <button
                                @click="skipShooter"
                                class="flex-1 rounded-xl border border-slate-600 py-3 text-sm font-medium transition-colors hover:bg-slate-800"
                            >
                                Skip &rarr;
                            </button>
                        </div>
                    </div>
                </div>
            </main>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useMatchStore } from '../stores/matchStore';
import { useScoringStore } from '../stores/scoringStore';
import OnlineIndicator from '../components/OnlineIndicator.vue';
import SyncBadge from '../components/SyncBadge.vue';

const props = defineProps({
    matchId: { type: Number, required: true },
});

const matchStore = useMatchStore();
const scoringStore = useScoringStore();
const ready = ref(false);
const matchComplete = ref(false);
const timeInput = ref(null);

// Timer state
const timerMode = ref('manual');
const timerSeconds = ref(0);
const timerRunning = ref(false);
const rawDigits = ref('');
const manualTime = ref(null);
const parTimeApplied = ref(false);
let timerInterval = null;

const targetSets = computed(() => matchStore.targetSets);
const currentTargetSet = computed(() => targetSets.value[scoringStore.currentTargetSetIndex]);
const currentGongs = computed(() => currentTargetSet.value?.gongs ?? []);
const shooters = computed(() => matchStore.hasSquadLock ? matchStore.squadShooters : matchStore.allShooters);
const currentShooter = computed(() => shooters.value[scoringStore.currentShooterIndex]);
const currentParTime = computed(() => currentTargetSet.value?.par_time_seconds ?? null);

const isFirst = computed(() => {
    return scoringStore.currentTargetSetIndex === 0 && scoringStore.currentShooterIndex === 0;
});

const totalSteps = computed(() => targetSets.value.length * shooters.value.length);
const currentStep = computed(() => {
    return scoringStore.currentTargetSetIndex * shooters.value.length + scoringStore.currentShooterIndex;
});
const progressPercent = computed(() => {
    if (!totalSteps.value) return 0;
    return Math.round((currentStep.value / totalSteps.value) * 100);
});

/**
 * Convert raw digit string to seconds float.
 * Last 2 digits are centiseconds: "105" -> 1.05, "50" -> 0.50
 */
function digitsToSeconds(digits) {
    if (!digits || digits.length === 0) return 0;
    const padded = digits.padStart(3, '0');
    const whole = padded.slice(0, -2);
    const frac = padded.slice(-2);
    return parseFloat(`${whole || '0'}.${frac}`);
}

/**
 * Convert a seconds float to display string: "1.05" -> "01:01.05" or "0:01.05"
 */
function formatSecondsDisplay(seconds) {
    if (seconds == null || seconds === 0) return '00:00.00';
    const t = parseFloat(seconds);
    const mins = Math.floor(t / 60);
    const secs = t % 60;
    return `${String(mins).padStart(2, '0')}:${secs.toFixed(2).padStart(5, '0')}`;
}

function getGongScore(gong) {
    if (!currentShooter.value) return null;
    const score = scoringStore.getScore(currentShooter.value.id, gong.id);
    return score ? score.isHit : null;
}

const stageHits = computed(() => {
    if (!currentShooter.value) return 0;
    return currentGongs.value.filter(g => getGongScore(g) === true).length;
});

const stageMisses = computed(() => {
    if (!currentShooter.value) return 0;
    return currentGongs.value.filter(g => getGongScore(g) === false).length;
});

const stageNotTaken = computed(() => {
    if (!currentShooter.value) return currentGongs.value.length;
    return currentGongs.value.filter(g => getGongScore(g) === null).length;
});

const effectiveTime = computed(() => {
    if (timerMode.value === 'manual') {
        return manualTime.value ?? 0;
    }
    return manualTime.value !== null ? manualTime.value : timerSeconds.value;
});

const formattedTime = computed(() => {
    return formatSecondsDisplay(effectiveTime.value);
});

function setGongState(gong, state) {
    if (!currentShooter.value) return;
    if (state === null) {
        scoringStore.removeScore(currentShooter.value.id, gong.id);
    } else {
        scoringStore.recordScore(currentShooter.value.id, gong.id, state);
    }
    parTimeApplied.value = false;
}

// Implied-decimal input handlers
function onDigitInput(e) {
    const cleaned = e.target.value.replace(/\D/g, '');
    rawDigits.value = cleaned;
    e.target.value = cleaned;
    manualTime.value = digitsToSeconds(cleaned);
    parTimeApplied.value = false;
}

function onDigitKeydown(e) {
    const allowed = ['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab', 'Enter'];
    if (allowed.includes(e.key)) return;
    if (!/^\d$/.test(e.key)) {
        e.preventDefault();
    }
}

function clearTimeInput() {
    rawDigits.value = '';
    manualTime.value = null;
    parTimeApplied.value = false;
}

// App timer controls
function startTimer() {
    if (timerRunning.value) return;
    timerRunning.value = true;
    manualTime.value = null;
    parTimeApplied.value = false;
    const startTime = performance.now() - timerSeconds.value * 1000;
    timerInterval = setInterval(() => {
        timerSeconds.value = (performance.now() - startTime) / 1000;
    }, 10);
}

function stopTimer() {
    if (!timerRunning.value) return;
    timerRunning.value = false;
    clearInterval(timerInterval);
    timerInterval = null;
    manualTime.value = parseFloat(timerSeconds.value.toFixed(2));
}

function resetTimer() {
    timerRunning.value = false;
    clearInterval(timerInterval);
    timerInterval = null;
    timerSeconds.value = 0;
    manualTime.value = null;
    rawDigits.value = '';
    parTimeApplied.value = false;
}

function secondsToRawDigits(seconds) {
    if (!seconds || seconds <= 0) return '';
    const cs = Math.round(seconds * 100);
    return String(cs);
}

async function completeStage() {
    if (!currentShooter.value || !currentTargetSet.value) return;

    let timeValue = effectiveTime.value;

    const allHit = stageHits.value === currentGongs.value.length;
    if (!allHit && currentParTime.value && currentParTime.value > 0) {
        timeValue = parseFloat(currentParTime.value);
        parTimeApplied.value = true;
    }

    if (timeValue > 0) {
        await scoringStore.recordStageTime(
            currentShooter.value.id,
            currentTargetSet.value.id,
            parseFloat(timeValue.toFixed(2))
        );
    }

    resetTimer();
    advance();
}

function advance() {
    if (scoringStore.prsAdvanceToNextShooter(shooters.value.length)) {
        loadExistingStageState();
        return;
    }
    if (scoringStore.prsAdvanceToNextStage(targetSets.value.length)) {
        loadExistingStageState();
        return;
    }
    matchComplete.value = true;
}

function skipShooter() {
    resetTimer();
    advance();
}

function goBack() {
    if (scoringStore.currentShooterIndex > 0) {
        scoringStore.currentShooterIndex--;
    } else if (scoringStore.currentTargetSetIndex > 0) {
        scoringStore.currentTargetSetIndex--;
        scoringStore.currentShooterIndex = shooters.value.length - 1;
    }
    resetTimer();
    loadExistingStageState();
}

function loadExistingStageState() {
    if (!currentShooter.value || !currentTargetSet.value) return;
    const existing = scoringStore.getStageTime(currentShooter.value.id, currentTargetSet.value.id);
    if (existing) {
        manualTime.value = existing.timeSeconds;
        timerSeconds.value = existing.timeSeconds;
        rawDigits.value = secondsToRawDigits(existing.timeSeconds);
    }
}

watch([() => scoringStore.currentShooterIndex, () => scoringStore.currentTargetSetIndex], () => {
    loadExistingStageState();
});

let syncInterval;

onMounted(async () => {
    if (!matchStore.currentMatch || matchStore.currentMatch.id !== props.matchId) {
        await matchStore.fetchMatch(props.matchId);
    }
    const scores = matchStore.currentMatch?.scores ?? [];
    const stageTimes = matchStore.currentMatch?.stage_times ?? [];
    await scoringStore.initForMatch(props.matchId, scores, stageTimes);
    ready.value = true;

    syncInterval = setInterval(() => {
        if (navigator.onLine && scoringStore.pendingCount > 0) {
            scoringStore.syncScores();
        }
    }, 15000);
});

onUnmounted(() => {
    clearInterval(syncInterval);
    clearInterval(timerInterval);
});
</script>
