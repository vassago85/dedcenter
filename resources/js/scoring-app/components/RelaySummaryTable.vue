<template>
    <div class="space-y-3">
        <!-- Toolbar: read-mode "Correct scores" toggle -->
        <div class="flex items-center justify-between">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Round scores</p>
            <button
                v-if="!editing"
                type="button"
                @click="enterEdit"
                class="inline-flex items-center gap-1.5 rounded-lg border border-amber-600/50 bg-amber-600/10 px-3 py-1.5 text-xs font-bold text-amber-300 transition-colors hover:bg-amber-600/20"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13L2.25 21.75l.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                </svg>
                Correct scores
            </button>
            <span
                v-else
                class="rounded-full bg-amber-600/20 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-amber-300"
            >
                Editing &mdash; tap a cell
            </span>
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-700 bg-slate-800" :class="{ 'ring-2 ring-amber-500/40': editing }">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700 text-slate-400">
                        <th class="px-3 py-2.5 text-left font-medium">Shooter</th>
                        <th
                            v-for="gong in gongs"
                            :key="'gh-' + gong.id"
                            class="px-2 py-2.5 text-center font-medium whitespace-nowrap"
                        >
                            <div>#{{ gong.number }}</div>
                            <div class="text-[10px] text-amber-400">{{ gong.multiplier }}x</div>
                        </th>
                        <th class="px-3 py-2.5 text-center font-medium text-green-400">Hits</th>
                        <th class="px-3 py-2.5 text-center font-medium text-red-400">Miss</th>
                        <th class="px-3 py-2.5 text-right font-bold">Score</th>
                        <th class="px-2 py-2.5 text-right font-medium text-slate-500" aria-label="Fix"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/50">
                    <tr
                        v-for="row in rows"
                        :key="'row-' + row.shooter.id"
                        class="transition-colors hover:bg-slate-700/30"
                    >
                        <td class="px-3 py-2 font-medium">
                            {{ row.shooter.name }}
                            <span v-if="row.shooter.bib_number" class="text-xs text-slate-500 ml-1">#{{ row.shooter.bib_number }}</span>
                        </td>

                        <!-- Read mode cell -->
                        <template v-if="!editing">
                            <td
                                v-for="(g, gi) in row.gongResults"
                                :key="'g-' + row.shooter.id + '-' + gi"
                                class="px-2 py-2 text-center"
                            >
                                <div v-if="g.result === 'hit'" class="flex flex-col items-center">
                                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-green-600/30 text-xs font-bold text-green-400">&#10003;</span>
                                    <span class="text-[10px] text-green-400/80 tabular-nums">+{{ g.points }}</span>
                                </div>
                                <span v-else-if="g.result === 'miss'" class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-red-600/30 text-xs font-bold text-red-400">&#10007;</span>
                                <span v-else class="text-slate-600">&mdash;</span>
                            </td>
                        </template>

                        <!-- Edit mode cell: tap to cycle HIT -> MISS -> blank -->
                        <template v-else>
                            <td
                                v-for="(g, gi) in row.gongResults"
                                :key="'ge-' + row.shooter.id + '-' + gi"
                                class="px-1.5 py-1.5 text-center"
                            >
                                <button
                                    type="button"
                                    @click="cycleCell(row.shooter.id, row.gongs[gi].id)"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-sm font-bold transition-colors"
                                    :class="cellButtonClass(row.shooter.id, row.gongs[gi].id, g.result)"
                                    :aria-label="'Toggle gong ' + row.gongs[gi].number + ' for ' + row.shooter.name"
                                >
                                    <span v-if="g.result === 'hit'">&#10003;</span>
                                    <span v-else-if="g.result === 'miss'">&#10007;</span>
                                    <span v-else>&mdash;</span>
                                </button>
                            </td>
                        </template>

                        <td class="px-3 py-2 text-center tabular-nums text-green-400 font-medium">{{ row.hits }}</td>
                        <td class="px-3 py-2 text-center tabular-nums text-red-400 font-medium">{{ row.misses }}</td>
                        <td class="px-3 py-2 text-right tabular-nums font-bold text-amber-400">{{ row.total }}</td>
                        <td class="px-2 py-2 text-right">
                            <button
                                v-if="!editing"
                                type="button"
                                @click="$emit('correct-shooter', row.shooter)"
                                class="rounded-md p-1.5 text-slate-500 hover:bg-slate-700 hover:text-amber-400"
                                :title="'Correct ' + row.shooter.name"
                                :aria-label="'Correct ' + row.shooter.name"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13L2.25 21.75l.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                </svg>
                            </button>
                            <span
                                v-else-if="isShooterDirty(row.shooter.id)"
                                class="inline-block h-2 w-2 rounded-full bg-amber-400"
                                :title="'Edited'"
                                aria-label="Edited"
                            ></span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Edit-mode footer: reason + save/cancel -->
        <div v-if="editing" class="rounded-xl border border-amber-600/40 bg-amber-900/10 p-3 space-y-3">
            <p class="text-xs text-amber-200/80">
                Tap any cell to change it: <span class="font-semibold text-green-400">&#10003;</span> &rarr;
                <span class="font-semibold text-red-400">&#10007;</span> &rarr;
                <span class="font-semibold text-slate-400">&mdash;</span>.
                {{ dirtyShooterIds.length }} shooter<span v-if="dirtyShooterIds.length !== 1">s</span> changed.
            </p>

            <div>
                <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                    Reason <span class="text-red-400">*</span>
                </label>
                <textarea
                    v-model="reason"
                    rows="2"
                    maxlength="500"
                    placeholder="e.g. Score chair miscounted gong 3 on Relay 1"
                    class="mt-1.5 w-full rounded-md border border-slate-600 bg-slate-900 px-3 py-2 text-sm text-white focus:border-amber-500 focus:outline-none"
                ></textarea>
                <p class="mt-1 text-[11px] text-slate-500">Required &middot; logged with every correction.</p>
            </div>

            <p v-if="errorMessage" class="rounded-md bg-red-900/30 px-3 py-2 text-sm text-red-300">{{ errorMessage }}</p>
            <p v-if="queuedMessage" class="rounded-md bg-amber-900/30 px-3 py-2 text-sm text-amber-300">{{ queuedMessage }}</p>

            <div class="flex gap-3">
                <button
                    type="button"
                    @click="cancelEdit"
                    :disabled="saving"
                    class="flex-1 rounded-lg border border-slate-600 py-2.5 text-sm font-medium text-slate-300 hover:bg-slate-700 disabled:opacity-50"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    @click="save"
                    :disabled="!canSave"
                    class="flex-1 rounded-lg bg-amber-600 py-2.5 text-sm font-bold text-white hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {{ saving ? 'Saving...' : saveLabel }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';
import { useScoringStore } from '../stores/scoringStore';
import { queueShooterCorrection } from '../lib/offlineDb.js';

const props = defineProps({
    matchId: { type: Number, required: true },
    targetSet: { type: Object, default: null },
    shooters: { type: Array, default: () => [] },
});

const emit = defineEmits(['correct-shooter', 'corrected']);

const scoringStore = useScoringStore();

const editing = ref(false);
// draft[`${shooterId}-${gongId}`] = true | false | null
const draft = ref({});
const reason = ref('');
const saving = ref(false);
const errorMessage = ref('');
const queuedMessage = ref('');

const gongs = computed(() => props.targetSet?.gongs ?? []);
const distMult = computed(() => parseFloat(props.targetSet?.distance_multiplier) || 1);

function key(shooterId, gongId) {
    return `${shooterId}-${gongId}`;
}

// Original (server/store) state for a cell: true | false | null.
function originalState(shooterId, gongId) {
    const s = scoringStore.getScore(shooterId, gongId);
    if (s == null || s.isHit === undefined || s.isHit === null) return null;
    return !!s.isHit;
}

// Effective state for display: draft when editing, else store.
function cellState(shooterId, gongId) {
    if (editing.value) {
        const k = key(shooterId, gongId);
        return k in draft.value ? draft.value[k] : originalState(shooterId, gongId);
    }
    return originalState(shooterId, gongId);
}

const rows = computed(() => {
    const gs = gongs.value;
    return (props.shooters ?? []).map((shooter) => {
        let hits = 0;
        let misses = 0;
        let total = 0;
        const gongResults = gs.map((gong) => {
            const state = cellState(shooter.id, gong.id);
            const points = Math.round(distMult.value * (parseFloat(gong.multiplier) || 1) * 100) / 100;
            if (state === true) { hits++; total += points; return { result: 'hit', points }; }
            if (state === false) { misses++; return { result: 'miss', points: 0 }; }
            return { result: null, points: 0 };
        });
        return { shooter, gongs: gs, gongResults, hits, misses, total: Math.round(total * 100) / 100 };
    });
});

function enterEdit() {
    draft.value = {};
    reason.value = '';
    errorMessage.value = '';
    queuedMessage.value = '';
    editing.value = true;
}

function cancelEdit() {
    if (saving.value) return;
    editing.value = false;
    draft.value = {};
    reason.value = '';
    errorMessage.value = '';
    queuedMessage.value = '';
}

// HIT (true) -> MISS (false) -> blank (null) -> HIT
function cycleCell(shooterId, gongId) {
    const k = key(shooterId, gongId);
    const current = k in draft.value ? draft.value[k] : originalState(shooterId, gongId);
    let next;
    if (current === true) next = false;
    else if (current === false) next = null;
    else next = true;
    draft.value = { ...draft.value, [k]: next };
}

function isCellDirty(shooterId, gongId) {
    const k = key(shooterId, gongId);
    if (!(k in draft.value)) return false;
    return draft.value[k] !== originalState(shooterId, gongId);
}

function isShooterDirty(shooterId) {
    return gongs.value.some((g) => isCellDirty(shooterId, g.id));
}

const dirtyShooterIds = computed(() => {
    return (props.shooters ?? [])
        .map((s) => s.id)
        .filter((id) => isShooterDirty(id));
});

function cellButtonClass(shooterId, gongId, result) {
    const dirty = isCellDirty(shooterId, gongId);
    const ring = dirty ? ' ring-2 ring-amber-400' : '';
    if (result === 'hit') return 'bg-green-600/30 text-green-300 hover:bg-green-600/50' + ring;
    if (result === 'miss') return 'bg-red-600/30 text-red-300 hover:bg-red-600/50' + ring;
    return 'bg-slate-700/60 text-slate-400 hover:bg-slate-600' + ring;
}

const canSave = computed(() => {
    if (saving.value) return false;
    if (dirtyShooterIds.value.length === 0) return false;
    return reason.value.trim().length >= 3;
});

const saveLabel = computed(() => {
    const n = dirtyShooterIds.value.length;
    if (n <= 0) return 'Save corrections';
    return `Save (${n} shooter${n === 1 ? '' : 's'})`;
});

function buildGongStates(shooterId) {
    const states = {};
    for (const g of gongs.value) {
        states[g.id] = cellState(shooterId, g.id);
    }
    return states;
}

async function applyOfflineLocally(shooterId) {
    // Reflect the correction in the local store immediately so the grid
    // stays accurate while the queued correction waits to sync.
    for (const g of gongs.value) {
        if (!isCellDirty(shooterId, g.id)) continue;
        const state = cellState(shooterId, g.id);
        if (state === null) {
            await scoringStore.removeScore(shooterId, g.id);
        } else {
            await scoringStore.recordScore(shooterId, g.id, state);
        }
    }
}

async function save() {
    if (!canSave.value) return;
    saving.value = true;
    errorMessage.value = '';
    queuedMessage.value = '';

    const trimmedReason = reason.value.trim();
    const ids = [...dirtyShooterIds.value];
    const online = navigator.onLine;
    let queuedCount = 0;
    const failures = [];

    for (const shooterId of ids) {
        const payload = {
            target_set_id: props.targetSet.id,
            gong_states: buildGongStates(shooterId),
            reason: trimmedReason,
        };

        if (!online) {
            await queueShooterCorrection(props.matchId, shooterId, props.targetSet.id, payload);
            await applyOfflineLocally(shooterId);
            queuedCount++;
            continue;
        }

        try {
            await axios.post(`/api/matches/${props.matchId}/shooters/${shooterId}/correct`, payload);
        } catch (e) {
            if (e.response?.status === 423) {
                errorMessage.value = 'Match is completed. Re-open it from Match Control to correct scores.';
                saving.value = false;
                return;
            }
            // Fall back to the offline queue so the correction isn't lost.
            await queueShooterCorrection(props.matchId, shooterId, props.targetSet.id, payload);
            await applyOfflineLocally(shooterId);
            failures.push(shooterId);
        }
    }

    saving.value = false;

    if (!online || failures.length) {
        const n = queuedCount + failures.length;
        queuedMessage.value = `${n} correction${n === 1 ? '' : 's'} saved locally. Will sync when online.`;
        editing.value = false;
        draft.value = {};
        reason.value = '';
        emit('corrected');
        return;
    }

    editing.value = false;
    draft.value = {};
    reason.value = '';
    emit('corrected');
}
</script>
