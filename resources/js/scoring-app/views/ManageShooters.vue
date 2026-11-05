<template>
    <div class="flex min-h-screen flex-col bg-slate-900 text-white">
        <!-- Header -->
        <header class="border-b border-slate-700 bg-slate-800 px-4 py-4">
            <div class="mx-auto flex max-w-2xl items-center gap-3">
                <router-link
                    :to="{ name: 'match-overview', params: { matchId: matchId } }"
                    class="text-slate-400 hover:text-white"
                    aria-label="Back to match hub"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </router-link>
                <div class="min-w-0 flex-1">
                    <h1 class="truncate text-lg font-bold">Manage Shooters</h1>
                    <p class="text-xs text-slate-400 truncate">{{ matchStore.currentMatch?.name ?? 'Loading…' }}</p>
                </div>
                <OnlineIndicator />
            </div>
        </header>

        <!-- Loading -->
        <div v-if="matchStore.loading && !matchStore.currentMatch" class="flex flex-1 items-center justify-center">
            <div class="h-8 w-8 animate-spin rounded-full border-2 border-slate-600 border-t-red-500"></div>
        </div>

        <!-- Not authorised -->
        <div
            v-else-if="matchStore.currentMatch && !matchStore.canManageSquadding"
            class="mx-auto w-full max-w-2xl px-4 py-6"
        >
            <div class="rounded-xl border border-amber-700/50 bg-amber-900/20 p-5 text-center">
                <h2 class="text-base font-bold text-amber-300">Not available</h2>
                <p class="mt-2 text-sm text-amber-200/80">
                    Only match directors and range officers can add or move shooters.
                </p>
                <router-link
                    :to="{ name: 'match-overview', params: { matchId: matchId } }"
                    class="mt-4 inline-block rounded-lg border border-amber-500 px-4 py-2 text-sm font-semibold text-amber-200 hover:bg-amber-900/40"
                >Back to match</router-link>
            </div>
        </div>

        <main
            v-else-if="matchStore.currentMatch"
            class="mx-auto w-full max-w-2xl flex-1 px-4 py-5 space-y-4"
        >
            <!-- Toast / error strip -->
            <div
                v-if="feedback"
                class="rounded-xl border px-4 py-2.5 text-sm"
                :class="feedback.type === 'error'
                    ? 'border-red-700 bg-red-900/30 text-red-300'
                    : 'border-emerald-700 bg-emerald-900/30 text-emerald-300'"
                role="status"
            >
                {{ feedback.message }}
            </div>

            <!-- Add Walk-in panel -->
            <section class="rounded-xl border border-slate-700 bg-slate-800">
                <button
                    type="button"
                    class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left"
                    @click="addOpen = !addOpen"
                >
                    <div class="flex items-center gap-2">
                        <svg class="h-5 w-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-300">Add walk-in shooter</h2>
                    </div>
                    <svg
                        class="h-4 w-4 text-slate-400 transition-transform"
                        :class="{ 'rotate-180': addOpen }"
                        fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                    </svg>
                </button>

                <form
                    v-if="addOpen"
                    class="border-t border-slate-700/60 px-4 py-4 space-y-3"
                    @submit.prevent="submitWalkin"
                >
                    <div class="space-y-1.5">
                        <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                            Full name <span class="text-red-400">*</span>
                        </label>
                        <input
                            v-model="walkin.name"
                            type="text"
                            maxlength="255"
                            autocomplete="off"
                            class="w-full rounded-lg border border-slate-600 bg-slate-900 px-3 py-2.5 text-sm text-white focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                            placeholder="e.g. Jane van der Merwe"
                            required
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400">Bib #</label>
                            <input
                                v-model="walkin.bib_number"
                                type="text"
                                maxlength="50"
                                autocomplete="off"
                                class="w-full rounded-lg border border-slate-600 bg-slate-900 px-3 py-2.5 text-sm text-white focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                                placeholder="optional"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400">Caliber</label>
                            <input
                                v-model="walkin.caliber"
                                type="text"
                                maxlength="64"
                                autocomplete="off"
                                class="w-full rounded-lg border border-slate-600 bg-slate-900 px-3 py-2.5 text-sm text-white focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                                placeholder="e.g. 6.5 CM"
                            />
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                            Squad <span class="text-red-400">*</span>
                        </label>
                        <select
                            v-model.number="walkin.squad_id"
                            class="w-full rounded-lg border border-slate-600 bg-slate-900 px-3 py-2.5 text-sm text-white focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                            required
                        >
                            <option :value="null">Select a squad…</option>
                            <option
                                v-for="squad in matchStore.squads"
                                :key="squad.id"
                                :value="squad.id"
                                :disabled="squadIsFull(squad)"
                            >
                                {{ squad.name }} ({{ activeCount(squad) }}{{ squadCapHint(squad) }})
                            </option>
                        </select>
                    </div>

                    <div v-if="divisions.length" class="space-y-1.5">
                        <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                            Division <span class="text-red-400">*</span>
                        </label>
                        <select
                            v-model.number="walkin.division_id"
                            class="w-full rounded-lg border border-slate-600 bg-slate-900 px-3 py-2.5 text-sm text-white focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                            required
                        >
                            <option :value="null">Select a division…</option>
                            <option
                                v-for="div in divisions"
                                :key="div.id"
                                :value="div.id"
                            >{{ div.name }}</option>
                        </select>
                    </div>

                    <button
                        type="submit"
                        :disabled="!canSubmitWalkin || adding"
                        class="w-full rounded-lg bg-emerald-600 py-2.5 text-sm font-bold text-white transition-colors hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >{{ adding ? 'Adding…' : 'Add shooter' }}</button>
                </form>
            </section>

            <!-- Squads + shooters -->
            <section class="space-y-3">
                <div
                    v-for="squad in sortedSquads"
                    :key="squad.id"
                    class="rounded-xl border border-slate-700 bg-slate-800"
                >
                    <header class="flex items-center justify-between border-b border-slate-700/60 px-4 py-3">
                        <div>
                            <h3 class="text-sm font-bold">{{ squad.name }}</h3>
                            <p class="text-[11px] text-slate-400">
                                {{ activeCount(squad) }} active
                                <span v-if="withdrawnCount(squad)"> &middot; {{ withdrawnCount(squad) }} withdrawn</span>
                                <span v-if="dqCount(squad)"> &middot; {{ dqCount(squad) }} DQ</span>
                                <span v-if="squad.max_capacity"> &middot; cap {{ squad.max_capacity }}</span>
                            </p>
                        </div>
                        <span
                            v-if="squadIsFull(squad)"
                            class="rounded-full border border-red-700/50 bg-red-900/20 px-2 py-0.5 text-[10px] font-bold uppercase text-red-300"
                        >Full</span>
                    </header>

                    <ul v-if="sortedShooters(squad).length" class="divide-y divide-slate-700/50">
                        <li
                            v-for="(sh, idx) in sortedShooters(squad)"
                            :key="sh.id"
                            class="px-3 py-2.5"
                            :class="{ 'opacity-50': sh.status === 'withdrawn', 'bg-red-900/10': sh.status === 'dq' }"
                        >
                        <div class="space-y-2">
                            <!-- Row 1: rank + name + bib. Gets its own line so long names never shrink to a single letter. -->
                            <div class="flex items-start gap-2">
                                <span class="w-6 shrink-0 pt-0.5 text-center text-[11px] font-bold tabular-nums text-slate-500">{{ idx + 1 }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium break-words">
                                        {{ baseName(sh) }}
                                        <span v-if="sh.status === 'dq'" class="ml-1 rounded bg-red-600/30 px-1.5 py-0.5 text-[9px] font-bold text-red-300">DQ</span>
                                        <span v-else-if="sh.status === 'withdrawn'" class="ml-1 rounded bg-slate-700 px-1.5 py-0.5 text-[9px] font-bold text-slate-300">OUT</span>
                                    </p>
                                    <p v-if="sh.bib_number || sh.division || caliberOf(sh) || sh.team" class="text-[11px] text-slate-500 break-words">
                                        <span v-if="sh.bib_number">Bib #{{ sh.bib_number }}</span>
                                        <span v-if="sh.bib_number && (sh.division || caliberOf(sh) || sh.team)"> &middot; </span>
                                        <span v-if="sh.division">{{ sh.division }}</span>
                                        <span v-if="sh.division && (caliberOf(sh) || sh.team)"> &middot; </span>
                                        <span v-if="caliberOf(sh)">{{ caliberOf(sh) }}</span>
                                        <span v-if="caliberOf(sh) && sh.team"> &middot; </span>
                                        <span v-if="sh.team">{{ sh.team }}</span>
                                    </p>
                                </div>
                            </div>

                            <!-- Row 2: actions. Wraps on narrow screens so nothing gets squashed. -->
                            <div class="flex flex-wrap items-center gap-2 pl-8">
                                <div class="flex items-center">
                                    <button
                                        type="button"
                                        class="rounded-md p-1.5 text-slate-400 hover:bg-slate-700 hover:text-white disabled:cursor-not-allowed disabled:opacity-30"
                                        :disabled="idx === 0 || busyShooterId === sh.id"
                                        @click="nudge(sh, 'up')"
                                        aria-label="Move up"
                                    >
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5" />
                                        </svg>
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-md p-1.5 text-slate-400 hover:bg-slate-700 hover:text-white disabled:cursor-not-allowed disabled:opacity-30"
                                        :disabled="idx === sortedShooters(squad).length - 1 || busyShooterId === sh.id"
                                        @click="nudge(sh, 'down')"
                                        aria-label="Move down"
                                    >
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                        </svg>
                                    </button>
                                </div>

                                <select
                                    class="min-w-0 rounded-md border border-slate-600 bg-slate-900 px-1.5 py-1 text-[11px] font-medium text-white focus:border-amber-500 focus:outline-none"
                                    :disabled="busyShooterId === sh.id"
                                    :value="''"
                                    @change="onMoveSelect($event, sh)"
                                    :aria-label="'Move ' + sh.name + ' to another squad'"
                                >
                                    <option value="" disabled>Move…</option>
                                    <option
                                        v-for="target in otherSquads(squad)"
                                        :key="target.id"
                                        :value="target.id"
                                        :disabled="squadIsFull(target)"
                                    >
                                        {{ target.name }}{{ squadIsFull(target) ? ' (full)' : '' }}
                                    </option>
                                </select>

                                <button
                                    v-if="sh.status !== 'dq'"
                                    type="button"
                                    class="rounded-md px-2 py-1 text-[11px] font-bold transition-colors"
                                    :class="sh.status === 'withdrawn'
                                        ? 'bg-emerald-600/20 text-emerald-300 hover:bg-emerald-600/30'
                                        : 'bg-slate-700 text-slate-300 hover:bg-slate-600'"
                                    :disabled="busyShooterId === sh.id"
                                    @click="toggleStatus(sh)"
                                >{{ sh.status === 'withdrawn' ? 'Restore' : 'Withdraw' }}</button>

                                <button
                                    type="button"
                                    class="rounded-md bg-amber-600/20 px-2 py-1 text-[11px] font-bold text-amber-200 hover:bg-amber-600/30"
                                    @click="toggleEdit(sh, squad)"
                                >{{ editingId === sh.id ? 'Close' : 'Edit' }}</button>
                            </div>
                        </div>

                        <form
                            v-if="editingId === sh.id"
                            class="mt-3 grid grid-cols-2 gap-2 border-t border-slate-700/60 pt-3"
                            @submit.prevent="saveDetails(sh, squad)"
                        >
                            <label class="col-span-2 space-y-1">
                                <span class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400">Cartridge</span>
                                <input
                                    v-model="draft.caliber"
                                    type="text"
                                    maxlength="64"
                                    class="w-full rounded-lg border border-slate-600 bg-slate-900 px-3 py-2 text-sm text-white focus:border-amber-500 focus:outline-none"
                                    placeholder="e.g. 6.5 CM"
                                />
                            </label>
                            <label v-if="divisions.length" class="space-y-1">
                                <span class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400">Major / Minor</span>
                                <select
                                    v-model="draft.division_id"
                                    class="w-full rounded-lg border border-slate-600 bg-slate-900 px-2 py-2 text-sm text-white focus:border-amber-500 focus:outline-none"
                                >
                                    <option :value="null">No division</option>
                                    <option v-for="div in divisions" :key="div.id" :value="div.id">{{ div.name }}</option>
                                </select>
                            </label>
                            <label v-if="teams.length" class="space-y-1">
                                <span class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400">Team</span>
                                <select
                                    v-model="draft.team"
                                    class="w-full rounded-lg border border-slate-600 bg-slate-900 px-2 py-2 text-sm text-white focus:border-amber-500 focus:outline-none"
                                >
                                    <option value="">No team</option>
                                    <option v-for="team in teams" :key="team.id" :value="team.name">{{ team.name }}</option>
                                </select>
                            </label>
                            <label class="col-span-2 space-y-1">
                                <span class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400">Squad</span>
                                <select
                                    v-model.number="draft.squad_id"
                                    class="w-full rounded-lg border border-slate-600 bg-slate-900 px-2 py-2 text-sm text-white focus:border-amber-500 focus:outline-none"
                                >
                                    <option
                                        v-for="target in matchStore.squads"
                                        :key="target.id"
                                        :value="target.id"
                                        :disabled="target.id !== squad.id && squadIsFull(target)"
                                    >{{ target.name }}{{ target.id !== squad.id && squadIsFull(target) ? ' (full)' : '' }}</option>
                                </select>
                            </label>
                            <button
                                type="submit"
                                :disabled="busyShooterId === sh.id"
                                class="col-span-2 rounded-lg bg-amber-600 py-2 text-sm font-bold text-white hover:bg-amber-700 disabled:opacity-50"
                            >{{ busyShooterId === sh.id ? 'Saving…' : 'Save details' }}</button>
                        </form>
                        </li>
                    </ul>
                    <div v-else class="px-4 py-5 text-center text-sm text-slate-500">
                        No shooters in this squad yet.
                    </div>
                </div>

                <p v-if="!matchStore.squads.length" class="rounded-xl border border-slate-700 bg-slate-800 p-5 text-center text-sm text-slate-500">
                    No squads yet. Create squads in the web squadding page before adding shooters here.
                </p>
            </section>
        </main>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useMatchStore } from '../stores/matchStore';
import OnlineIndicator from '../components/OnlineIndicator.vue';

const props = defineProps({
    matchId: { type: Number, required: true },
});

const matchStore = useMatchStore();

const matchId = computed(() => props.matchId);

// Divisions live on the match; walk-in requires one if any exist. Fall back
// to [] so the form doesn't try to render a <select> when the API hasn't
// loaded yet (otherwise Vue warns about v-for over undefined).
const divisions = computed(() => matchStore.currentMatch?.divisions ?? []);
const teams = computed(() => matchStore.currentMatch?.teams ?? []);

// Add walk-in form state ------------------------------------------------------
const addOpen = ref(true);
const walkin = ref({
    name: '',
    bib_number: '',
    caliber: '',
    squad_id: null,
    division_id: null,
});
const adding = ref(false);

const canSubmitWalkin = computed(() => {
    if (!walkin.value.name.trim()) return false;
    if (!walkin.value.squad_id) return false;
    if (divisions.value.length && !walkin.value.division_id) return false;
    return true;
});

async function submitWalkin() {
    if (!canSubmitWalkin.value || adding.value) return;
    adding.value = true;
    try {
        const payload = {
            name: walkin.value.name.trim(),
            squad_id: walkin.value.squad_id,
        };
        const bib = walkin.value.bib_number.trim();
        if (bib) payload.bib_number = bib;
        const cal = walkin.value.caliber.trim();
        if (cal) payload.caliber = cal;
        if (divisions.value.length) payload.division_id = walkin.value.division_id;

        const created = await matchStore.addShooter(matchId.value, payload);
        showFeedback('success', `${created.name} added.`);

        // Reset for the next walk-in but keep the squad / division so the
        // MD can rattle through a group without re-selecting every time.
        walkin.value.name = '';
        walkin.value.bib_number = '';
    } catch (e) {
        showFeedback('error', serverMessage(e, 'Could not add shooter.'));
    } finally {
        adding.value = false;
    }
}

// Per-shooter actions ---------------------------------------------------------
const busyShooterId = ref(null);

async function nudge(shooter, direction) {
    if (busyShooterId.value) return;
    busyShooterId.value = shooter.id;
    try {
        await matchStore.reorderShooter(matchId.value, shooter.id, direction);
    } catch (e) {
        showFeedback('error', serverMessage(e, 'Could not reorder shooter.'));
        // Resync on failure so the UI doesn't drift out of truth.
        await matchStore.fetchMatch(matchId.value, { silent: true });
    } finally {
        busyShooterId.value = null;
    }
}

function onMoveSelect(event, shooter) {
    const targetId = Number(event.target.value);
    event.target.value = '';
    if (!targetId || targetId === shooter.squad_id) return;
    moveShooter(shooter, targetId);
}

async function moveShooter(shooter, targetSquadId) {
    if (busyShooterId.value) return;
    busyShooterId.value = shooter.id;
    try {
        await matchStore.moveShooterToSquad(matchId.value, shooter.id, targetSquadId);
        const target = matchStore.squads.find(s => s.id === targetSquadId);
        showFeedback('success', `${shooter.name} moved to ${target?.name ?? 'new squad'}.`);
    } catch (e) {
        showFeedback('error', serverMessage(e, 'Could not move shooter.'));
        await matchStore.fetchMatch(matchId.value, { silent: true });
    } finally {
        busyShooterId.value = null;
    }
}

const editingId = ref(null);
const draft = ref({ caliber: '', division_id: null, team: '', squad_id: null });

function baseName(shooter) {
    const name = String(shooter?.name ?? '');
    const split = name.split(' — ');
    return split[0].trim() || name;
}

function caliberOf(shooter) {
    if (shooter?.caliber) return shooter.caliber;
    const name = String(shooter?.name ?? '');
    const split = name.split(' — ');
    return split.length > 1 ? split.slice(1).join(' — ').trim() : '';
}

function toggleEdit(shooter, squad) {
    if (editingId.value === shooter.id) {
        editingId.value = null;
        return;
    }
    editingId.value = shooter.id;
    draft.value = {
        caliber: caliberOf(shooter),
        division_id: shooter.division_id ?? null,
        team: shooter.team ?? '',
        squad_id: shooter.squad_id ?? squad.id,
    };
}

async function saveDetails(shooter, squad) {
    if (busyShooterId.value) return;
    busyShooterId.value = shooter.id;
    try {
        const payload = {
            caliber: draft.value.caliber.trim(),
            squad_id: draft.value.squad_id || squad.id,
        };
        if (divisions.value.length) {
            payload.division_id = draft.value.division_id || null;
        }
        if (teams.value.length) {
            payload.team = draft.value.team || null;
        }
        await matchStore.updateShooterDetails(matchId.value, shooter.id, payload);
        showFeedback('success', `${baseName(shooter)} updated.`);
        editingId.value = null;
    } catch (e) {
        showFeedback('error', serverMessage(e, 'Could not update shooter.'));
        await matchStore.fetchMatch(matchId.value, { silent: true });
    } finally {
        busyShooterId.value = null;
    }
}

async function toggleStatus(shooter) {
    if (busyShooterId.value) return;
    const next = shooter.status === 'withdrawn' ? 'active' : 'withdrawn';
    busyShooterId.value = shooter.id;
    try {
        await matchStore.updateShooterStatus(matchId.value, shooter.id, next);
        showFeedback('success', `${shooter.name} ${next === 'active' ? 'restored' : 'withdrawn'}.`);
    } catch (e) {
        showFeedback('error', serverMessage(e, 'Could not update status.'));
    } finally {
        busyShooterId.value = null;
    }
}

// Helpers ---------------------------------------------------------------------
const sortedSquads = computed(() => {
    return [...matchStore.squads].sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0));
});

function sortedShooters(squad) {
    return [...(squad.shooters ?? [])].sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0));
}

function activeCount(squad) {
    return (squad.shooters ?? []).filter(s => s.status === 'active').length;
}
function withdrawnCount(squad) {
    return (squad.shooters ?? []).filter(s => s.status === 'withdrawn').length;
}
function dqCount(squad) {
    return (squad.shooters ?? []).filter(s => s.status === 'dq').length;
}

// Capacity check: squad-level override wins; else match default; else
// unlimited. Mirrors server-side Squad::isFull(). Only `active` + `dq`
// (DQ'd shooters still occupy the slot — they didn't leave the match)
// count toward the cap.
function effectiveCapacity(squad) {
    if (squad.max_capacity) return squad.max_capacity;
    const matchCap = matchStore.currentMatch?.max_squad_size;
    return matchCap ? matchCap : null;
}
function occupiedSlots(squad) {
    return (squad.shooters ?? []).filter(s => s.status !== 'withdrawn').length;
}
function squadIsFull(squad) {
    const cap = effectiveCapacity(squad);
    if (!cap) return false;
    return occupiedSlots(squad) >= cap;
}
function squadCapHint(squad) {
    const cap = effectiveCapacity(squad);
    return cap ? `/${cap}` : '';
}

function otherSquads(squad) {
    return matchStore.squads.filter(s => s.id !== squad.id);
}

// Feedback banner. One slot, auto-dismiss after 3s so a steady add flow
// doesn't bury the squad list under a wall of toasts.
const feedback = ref(null);
let feedbackTimer = null;
function showFeedback(type, message) {
    feedback.value = { type, message };
    if (feedbackTimer) clearTimeout(feedbackTimer);
    feedbackTimer = setTimeout(() => { feedback.value = null; }, 3000);
}

function serverMessage(error, fallback) {
    const data = error?.response?.data;
    if (data?.message) return data.message;
    if (data?.errors) {
        const first = Object.values(data.errors)[0];
        if (Array.isArray(first) && first.length) return first[0];
    }
    return fallback;
}

onMounted(async () => {
    if (!matchStore.currentMatch || matchStore.currentMatch.id !== matchId.value) {
        await matchStore.fetchMatch(matchId.value);
    } else {
        // Background revalidation so our local shooter cache is fresh when
        // the MD lands on this screen from a scoring flow.
        matchStore.fetchMatch(matchId.value, { silent: true });
    }
});
</script>
