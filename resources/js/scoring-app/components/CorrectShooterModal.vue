<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/70 sm:items-center"
            @click.self="cancel"
        >
            <div class="w-full max-w-md max-h-[92vh] overflow-y-auto rounded-t-2xl bg-slate-800 p-5 shadow-2xl sm:rounded-2xl">
                <div class="mb-4 flex items-start gap-3">
                    <div class="mt-0.5 flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-amber-500/20">
                        <svg class="h-5 w-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13L2.25 21.75l.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-lg font-bold text-white truncate">Correct {{ shooter?.name }}</h3>
                        <p class="text-xs text-slate-400">
                            {{ stageLabel }}<span v-if="shooter?.bib_number"> &middot; Bib #{{ shooter.bib_number }}</span>
                        </p>
                    </div>
                    <button @click="cancel" class="text-slate-500 hover:text-white" :disabled="saving">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Match-completed reopen prompt -->
                <div v-if="lockedByCompletion" class="mb-4 rounded-xl border border-amber-500/40 bg-amber-900/20 p-4">
                    <div class="flex items-start gap-2.5">
                        <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                        </svg>
                        <div class="min-w-0 flex-1 text-sm">
                            <p class="font-semibold text-amber-200">Match is completed</p>
                            <p class="mt-1 text-amber-200/80">
                                Scoring is locked. Re-open the match to apply this correction, then we'll
                                re-complete it for you automatically.
                            </p>
                            <button
                                @click="reopenAndRetry"
                                :disabled="reopening"
                                class="mt-3 w-full rounded-lg bg-amber-600 px-4 py-2 text-sm font-bold text-white hover:bg-amber-700 disabled:opacity-50"
                            >
                                {{ reopening ? 'Working...' : 'Re-open match & apply correction' }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Standard gong grid -->
                <div v-if="!lockedByCompletion && mode === 'standard'" class="space-y-2">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Gongs</p>
                    <div class="space-y-1.5">
                        <div
                            v-for="g in standardGongs"
                            :key="g.id"
                            class="flex items-center gap-2 rounded-lg border border-slate-700 bg-slate-900/40 px-2 py-1.5"
                        >
                            <div class="flex w-14 flex-col items-center">
                                <span class="text-sm font-bold text-white">#{{ g.number }}</span>
                                <span class="text-[10px] text-amber-400">{{ g.multiplier }}x</span>
                            </div>
                            <div class="grid flex-1 grid-cols-3 gap-1">
                                <button
                                    type="button"
                                    @click="setGongState(g.id, true)"
                                    class="rounded-md py-2 text-xs font-bold transition-colors"
                                    :class="gongState[g.id] === true
                                        ? 'bg-green-600 text-white shadow-inner'
                                        : 'bg-slate-800 text-green-400 hover:bg-slate-700'"
                                >
                                    HIT
                                </button>
                                <button
                                    type="button"
                                    @click="setGongState(g.id, false)"
                                    class="rounded-md py-2 text-xs font-bold transition-colors"
                                    :class="gongState[g.id] === false
                                        ? 'bg-red-700 text-white shadow-inner'
                                        : 'bg-slate-800 text-red-400 hover:bg-slate-700'"
                                >
                                    MISS
                                </button>
                                <button
                                    type="button"
                                    @click="setGongState(g.id, null)"
                                    class="rounded-md py-2 text-xs font-bold transition-colors"
                                    :class="gongState[g.id] === null
                                        ? 'bg-slate-600 text-white shadow-inner'
                                        : 'bg-slate-800 text-slate-400 hover:bg-slate-700'"
                                >
                                    &mdash;
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="targetSet?.is_timed_stage" class="mt-4 rounded-lg border border-slate-700 bg-slate-900/40 p-3">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Stage time (seconds)</label>
                        <div class="mt-2 flex items-center gap-2">
                            <input
                                v-model.number="stageTimeInput"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="e.g. 32.45"
                                class="flex-1 rounded-md border border-slate-600 bg-slate-900 px-3 py-2 text-sm text-white focus:border-amber-500 focus:outline-none"
                            />
                            <button
                                type="button"
                                @click="clearStageTime"
                                class="rounded-md border border-slate-600 px-3 py-2 text-xs font-medium text-slate-300 hover:bg-slate-700"
                            >
                                Clear
                            </button>
                        </div>
                    </div>
                </div>

                <!-- PRS shot grid -->
                <div v-if="!lockedByCompletion && mode === 'prs'" class="space-y-2">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Shots</p>
                    <div class="grid grid-cols-1 gap-1.5">
                        <div
                            v-for="n in prsShotNumbers"
                            :key="n"
                            class="flex items-center gap-2 rounded-lg border border-slate-700 bg-slate-900/40 px-2 py-1.5"
                        >
                            <span class="w-10 text-center text-sm font-bold text-slate-300">#{{ n }}</span>
                            <div class="grid flex-1 grid-cols-3 gap-1">
                                <button
                                    type="button"
                                    @click="setPrsResult(n, 'hit')"
                                    class="rounded-md py-2 text-xs font-bold transition-colors"
                                    :class="prsShotState[n] === 'hit'
                                        ? 'bg-green-600 text-white shadow-inner'
                                        : 'bg-slate-800 text-green-400 hover:bg-slate-700'"
                                >
                                    HIT
                                </button>
                                <button
                                    type="button"
                                    @click="setPrsResult(n, 'miss')"
                                    class="rounded-md py-2 text-xs font-bold transition-colors"
                                    :class="prsShotState[n] === 'miss'
                                        ? 'bg-red-700 text-white shadow-inner'
                                        : 'bg-slate-800 text-red-400 hover:bg-slate-700'"
                                >
                                    MISS
                                </button>
                                <button
                                    type="button"
                                    @click="setPrsResult(n, 'not_taken')"
                                    class="rounded-md py-2 text-xs font-bold transition-colors"
                                    :class="prsShotState[n] === 'not_taken'
                                        ? 'bg-slate-600 text-white shadow-inner'
                                        : 'bg-slate-800 text-slate-400 hover:bg-slate-700'"
                                >
                                    &mdash;
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 rounded-lg border border-slate-700 bg-slate-900/40 p-3">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Raw time (seconds, optional)</label>
                        <input
                            v-model.number="prsTimeInput"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="e.g. 41.20"
                            class="mt-2 w-full rounded-md border border-slate-600 bg-slate-900 px-3 py-2 text-sm text-white focus:border-amber-500 focus:outline-none"
                        />
                    </div>
                </div>

                <!-- Reason -->
                <div v-if="!lockedByCompletion" class="mt-4">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400">
                        Reason <span class="text-red-400">*</span>
                    </label>
                    <textarea
                        v-model="reason"
                        rows="2"
                        maxlength="500"
                        placeholder="e.g. Score chair miscounted gong 3"
                        class="mt-2 w-full rounded-md border border-slate-600 bg-slate-900 px-3 py-2 text-sm text-white focus:border-amber-500 focus:outline-none"
                    ></textarea>
                    <p class="mt-1 text-[11px] text-slate-500">Required &middot; logged with this correction.</p>
                </div>

                <!-- Errors / status -->
                <p v-if="errorMessage" class="mt-3 rounded-md bg-red-900/30 px-3 py-2 text-sm text-red-300">
                    {{ errorMessage }}
                </p>
                <p v-if="queuedMessage" class="mt-3 rounded-md bg-amber-900/30 px-3 py-2 text-sm text-amber-300">
                    {{ queuedMessage }}
                </p>

                <!-- Actions -->
                <div v-if="!lockedByCompletion" class="mt-5 flex gap-3">
                    <button
                        type="button"
                        @click="cancel"
                        :disabled="saving"
                        class="flex-1 rounded-lg border border-slate-600 py-2.5 text-sm font-medium text-slate-300 hover:bg-slate-700 disabled:opacity-50"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="save"
                        :disabled="!canSave || saving"
                        class="flex-1 rounded-lg bg-amber-600 py-2.5 text-sm font-bold text-white hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {{ saving ? 'Saving...' : 'Save correction' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import { queueShooterCorrection } from '../lib/offlineDb.js';

const props = defineProps({
    open: { type: Boolean, required: true },
    matchId: { type: Number, required: true },
    shooter: { type: Object, required: true },
    mode: { type: String, default: 'standard' }, // 'standard' | 'prs'
    // Standard
    targetSet: { type: Object, default: null },
    existingScores: { type: Array, default: () => [] },     // [{ gong_id, is_hit }]
    existingStageTime: { type: [Number, null], default: null },
    // PRS
    stage: { type: Object, default: null },
    existingPrsShots: { type: Array, default: () => [] },   // [{ shot_number, result }]
    existingPrsTime: { type: [Number, null], default: null },
});

const emit = defineEmits(['close', 'corrected', 'queued', 'reopen-success']);

// Mutable state
const gongState = ref({});           // gong_id => true|false|null
const stageTimeInput = ref(null);
const prsShotState = ref({});        // shot_number => 'hit'|'miss'|'not_taken'
const prsTimeInput = ref(null);
const reason = ref('');
const saving = ref(false);
const errorMessage = ref('');
const queuedMessage = ref('');
const lockedByCompletion = ref(false);
const reopening = ref(false);

const stageLabel = computed(() => {
    if (props.mode === 'prs') return props.stage?.display_name || props.stage?.label || 'Stage';
    return props.targetSet?.label || 'Stage';
});

const standardGongs = computed(() => props.targetSet?.gongs ?? []);

const prsShotNumbers = computed(() => {
    const max = props.stage?.total_shots || Math.max(0, props.existingPrsShots.length);
    return Array.from({ length: Math.max(max, 1) }, (_, i) => i + 1);
});

const canSave = computed(() => {
    if (saving.value) return false;
    if (reason.value.trim().length < 3) return false;
    if (props.mode === 'standard') {
        return standardGongs.value.length > 0;
    }
    if (props.mode === 'prs') {
        // Need a result per shot (default to "not_taken" if user hasn't picked).
        return prsShotNumbers.value.length > 0;
    }
    return false;
});

function reset() {
    gongState.value = {};
    prsShotState.value = {};
    stageTimeInput.value = props.existingStageTime ?? null;
    prsTimeInput.value = props.existingPrsTime ?? null;
    reason.value = '';
    errorMessage.value = '';
    queuedMessage.value = '';
    lockedByCompletion.value = false;

    if (props.mode === 'standard') {
        for (const g of standardGongs.value) {
            const found = props.existingScores.find(s => s.gong_id === g.id);
            gongState.value[g.id] = found ? (!!found.is_hit) : null;
        }
    } else if (props.mode === 'prs') {
        const max = props.stage?.total_shots || props.existingPrsShots.length;
        for (let i = 1; i <= Math.max(max, 1); i++) {
            const found = props.existingPrsShots.find(s => s.shot_number === i);
            prsShotState.value[i] = found?.result ?? 'not_taken';
        }
    }
}

watch(() => props.open, (isOpen) => { if (isOpen) reset(); });

function setGongState(gongId, value) {
    gongState.value = { ...gongState.value, [gongId]: value };
}

function setPrsResult(shotNumber, result) {
    prsShotState.value = { ...prsShotState.value, [shotNumber]: result };
}

function clearStageTime() {
    stageTimeInput.value = null;
}

function cancel() {
    if (saving.value) return;
    emit('close');
}

function buildPayload() {
    const trimmedReason = reason.value.trim();
    if (props.mode === 'standard') {
        const gongStates = {};
        for (const g of standardGongs.value) {
            gongStates[g.id] = gongState.value[g.id] ?? null;
        }
        const payload = {
            target_set_id: props.targetSet.id,
            gong_states: gongStates,
            reason: trimmedReason,
        };
        if (stageTimeInput.value === null || stageTimeInput.value === '' || Number.isNaN(stageTimeInput.value)) {
            // No time given. If the row previously had one and the user
            // explicitly cleared the field, clear it server-side; if it
            // never had one, omit the key so we don't write a null row.
            if (props.existingStageTime != null) {
                payload.clear_time = true;
            }
        } else {
            payload.time_seconds = Number(stageTimeInput.value);
        }
        return payload;
    }

    const shots = [];
    for (const n of prsShotNumbers.value) {
        shots.push({
            shot_number: n,
            result: prsShotState.value[n] || 'not_taken',
        });
    }
    const payload = {
        stage_id: props.stage.id,
        shots,
        reason: trimmedReason,
    };
    if (prsTimeInput.value !== null && prsTimeInput.value !== '' && !Number.isNaN(prsTimeInput.value)) {
        payload.raw_time_seconds = Number(prsTimeInput.value);
    }
    return payload;
}

async function save() {
    if (!canSave.value) return;
    saving.value = true;
    errorMessage.value = '';
    queuedMessage.value = '';
    const payload = buildPayload();

    if (!navigator.onLine) {
        const stageId = props.mode === 'prs' ? props.stage.id : props.targetSet.id;
        await queueShooterCorrection(props.matchId, props.shooter.id, stageId, payload);
        queuedMessage.value = 'Saved locally. Will sync when you\'re back online.';
        emit('queued', { matchId: props.matchId, shooterId: props.shooter.id, stageId, payload });
        saving.value = false;
        setTimeout(() => emit('close'), 700);
        return;
    }

    try {
        const { data } = await axios.post(
            `/api/matches/${props.matchId}/shooters/${props.shooter.id}/correct`,
            payload,
        );
        emit('corrected', data);
        saving.value = false;
        emit('close');
    } catch (e) {
        if (e.response?.status === 423) {
            lockedByCompletion.value = true;
            saving.value = false;
            return;
        }
        if (e.response?.status === 422 && e.response.data?.errors) {
            const first = Object.values(e.response.data.errors)[0];
            errorMessage.value = Array.isArray(first) ? first[0] : 'Please check the inputs.';
        } else {
            errorMessage.value = e.response?.data?.message
                || 'Correction failed. Try again, or it will be queued if you go offline.';
        }
        saving.value = false;
    }
}

async function reopenAndRetry() {
    if (reopening.value) return;
    reopening.value = true;
    errorMessage.value = '';
    try {
        await axios.post(`/api/matches/${props.matchId}/reopen`);
        emit('reopen-success');
        // Re-issue the correction and silently re-complete the match.
        const payload = buildPayload();
        const { data } = await axios.post(
            `/api/matches/${props.matchId}/shooters/${props.shooter.id}/correct`,
            payload,
        );
        try {
            await axios.post(`/api/matches/${props.matchId}/complete`);
        } catch {
            // If re-completion fails, MD can re-complete manually from the
            // Match Control Center — they'll see the match is still Active.
        }
        emit('corrected', data);
        reopening.value = false;
        emit('close');
    } catch (e) {
        reopening.value = false;
        errorMessage.value = e.response?.data?.message || 'Could not reopen the match. Please reopen it manually and try again.';
    }
}
</script>
