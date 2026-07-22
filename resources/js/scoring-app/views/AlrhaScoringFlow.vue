<template>
    <div class="min-h-screen bg-slate-900 text-slate-100">
        <div class="mx-auto max-w-6xl px-4 py-6">
            <!-- Header: match + active shooter class + relay -->
            <header class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="text-xs uppercase tracking-widest text-sky-300/80">
                        ALRHA · {{ activeClassLabel }}
                    </div>
                    <h1 class="mt-1 text-xl font-semibold text-white">{{ match?.name ?? 'Match' }}</h1>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <SyncBadge
                        :pending="elrStore.pendingCount"
                        :syncing="elrStore.syncing"
                        @sync="elrStore.syncShots()"
                    />
                    <button v-for="squad in squads" :key="squad.id"
                        @click="selectSquad(squad.id)"
                        :class="[
                            'rounded-lg px-3 py-1.5 text-xs font-medium border transition-colors',
                            squad.id === activeSquadId
                                ? 'bg-sky-600 text-white border-sky-500'
                                : 'bg-slate-800 text-slate-300 border-slate-700 hover:border-sky-500/60'
                        ]">
                        {{ squad.name }}
                    </button>
                </div>
            </header>

            <!-- Class filter chips (only visible on dual-class matches) -->
            <div v-if="isDualClass" class="mb-4 flex flex-wrap items-center gap-2">
                <span class="text-xs uppercase tracking-widest text-slate-500">Filter</span>
                <button v-for="opt in classFilterOptions" :key="opt.value"
                    @click="setClassFilter(opt.value)"
                    :class="[
                        'rounded-full border px-3 py-1 text-xs font-medium transition-colors',
                        classFilter === opt.value
                            ? 'border-sky-500 bg-sky-600/20 text-sky-100'
                            : 'border-slate-700 bg-slate-800 text-slate-300 hover:border-sky-500/60'
                    ]">
                    {{ opt.label }} <span class="ml-1 text-slate-400">({{ opt.count }})</span>
                </button>
            </div>

            <!-- Block picker (CBC / Far / Near) -->
            <div class="mb-6 grid grid-cols-3 gap-2">
                <button v-for="block in blocks" :key="block.key"
                    @click="activeBlock = block.key"
                    :class="[
                        'rounded-lg border p-3 text-left transition-colors',
                        activeBlock === block.key
                            ? 'border-sky-500 bg-sky-600/10'
                            : 'border-slate-700 bg-slate-800/60 hover:border-sky-500/50'
                    ]">
                    <div class="text-sm font-medium text-white">{{ block.label }}</div>
                    <div class="text-xs text-slate-400 mt-0.5">{{ block.subtitle }}</div>
                </button>
            </div>

            <!-- Shooter jump strip: parity with the classic scoring flow.
                 Tap a chip to jump directly to that shooter — no need to
                 walk through prev/next. Tinted green once the shooter has
                 any recorded shots so the RO can see who's still empty. -->
            <div v-if="shooters.length > 1" class="mb-3 -mx-4 overflow-x-auto px-4 py-1">
                <div class="flex items-center gap-2">
                    <span class="text-[10px] font-semibold uppercase tracking-widest text-slate-500 flex-shrink-0">Jump</span>
                    <button
                        v-for="(s, idx) in shooters"
                        :key="s.id"
                        @click="jumpToShooter(idx)"
                        :class="[
                            'flex flex-shrink-0 items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium transition-colors',
                            idx === activeShooterIndex
                                ? 'border-sky-400 bg-sky-600/25 text-sky-100'
                                : shooterHasAnyShots(s.id)
                                    ? 'border-emerald-700/70 bg-emerald-900/20 text-emerald-200 hover:border-emerald-400'
                                    : 'border-slate-700 bg-slate-800 text-slate-300 hover:border-slate-500 hover:text-white',
                        ]"
                    >
                        <span v-if="isDualClass && s.alrha_class"
                            :class="[
                                'rounded px-1 py-0.5 text-[9px] font-bold',
                                s.alrha_class === 'hunters' ? 'bg-emerald-500/30 text-emerald-100' : 'bg-sky-500/30 text-sky-100',
                            ]">
                            {{ s.alrha_class === 'hunters' ? 'H' : 'V' }}
                        </span>
                        <span class="truncate max-w-[7rem]">{{ shortName(s.name) }}</span>
                    </button>
                </div>
            </div>

            <!-- Shooter card -->
            <div v-if="activeShooter" class="mb-6 rounded-xl border border-slate-700 bg-slate-800/60 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-xs uppercase tracking-widest text-slate-400">
                            Now scoring · Shooter {{ activeShooterIndex + 1 }}/{{ shooters.length }}
                            <span v-if="isDualClass && activeShooter.alrha_class"
                                :class="[
                                    'ml-2 rounded px-1.5 py-0.5 text-[10px] font-semibold',
                                    activeShooter.alrha_class === 'hunters'
                                        ? 'bg-emerald-500/20 text-emerald-200'
                                        : 'bg-sky-500/20 text-sky-200'
                                ]">
                                {{ activeShooter.alrha_class === 'hunters' ? 'HUNTERS' : 'VARMINT' }}
                            </span>
                        </div>
                        <div class="mt-1 text-lg font-semibold text-white">{{ activeShooter.name }}</div>
                        <div class="text-xs text-slate-400">
                            {{ activeShooter.category ?? 'Open' }}
                            <span v-if="activeShooter.is_coached" class="ml-1 rounded bg-amber-500/10 px-1.5 py-0.5 text-amber-300">Coached</span>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button @click="prevShooter" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-1.5 text-xs text-slate-300 hover:border-sky-500/60">← Prev</button>
                        <button @click="nextShooter" class="rounded-lg bg-sky-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-sky-700">Next →</button>
                    </div>
                </div>
            </div>

            <!-- Targets in active block (filtered to active shooter's class) -->
            <div v-if="activeShooter" class="space-y-4">
                <div v-if="!blockTargets.length"
                    class="rounded-xl border border-dashed border-slate-700 bg-slate-800/40 p-6 text-center text-slate-400">
                    No targets in this block for
                    {{ activeShooter.alrha_class === 'hunters' ? 'Hunters' : (activeShooter.alrha_class === 'varmint' ? 'Varmint' : 'this shooter') }}.
                </div>
                <div v-for="target in blockTargets" :key="target.id"
                    class="rounded-xl border border-slate-700 bg-slate-800/60 p-4">
                    <div class="mb-3 flex items-center justify-between">
                        <div>
                            <div class="text-sm font-semibold text-white">{{ target.name }}</div>
                            <div class="text-xs text-slate-400">{{ target.distance_m }} m · Shots score 5-4-3-2-1</div>
                        </div>
                        <div class="text-xs text-slate-400">
                            {{ shotsHitFor(target.id) }} / {{ target.max_shots }} hits
                        </div>
                    </div>
                    <div class="grid grid-cols-5 gap-2">
                        <div v-for="shotNumber in target.max_shots" :key="shotNumber"
                            class="rounded-lg border border-slate-700 bg-slate-900/50 p-2">
                            <div class="text-[10px] uppercase tracking-widest text-slate-500">
                                Shot {{ shotNumber }} · {{ pointsForShot(shotNumber) }} pts
                            </div>
                            <div class="mt-1 flex gap-1">
                                <button @click="recordShot(target, shotNumber, 'hit')"
                                    :class="[
                                        'flex-1 rounded px-2 py-1 text-xs font-medium border transition-colors',
                                        resultFor(target.id, shotNumber) === 'hit'
                                            ? 'bg-emerald-600 text-white border-emerald-500'
                                            : 'bg-slate-800 text-slate-300 border-slate-700 hover:border-emerald-500/60'
                                    ]">Hit</button>
                                <button @click="recordShot(target, shotNumber, 'miss')"
                                    :class="[
                                        'flex-1 rounded px-2 py-1 text-xs font-medium border transition-colors',
                                        resultFor(target.id, shotNumber) === 'miss'
                                            ? 'bg-rose-600 text-white border-rose-500'
                                            : 'bg-slate-800 text-slate-300 border-slate-700 hover:border-rose-500/60'
                                    ]">Miss</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="rounded-xl border border-dashed border-slate-700 bg-slate-800/40 p-6 text-center text-slate-400">
                {{ shooters.length === 0
                    ? (isDualClass ? 'No shooters match the current class filter.' : 'Select a relay to start scoring.')
                    : 'Select a relay to start scoring.' }}
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useMatchStore } from '../stores/matchStore';
import { useElrScoringStore } from '../stores/elrScoringStore';
import SyncBadge from '../components/SyncBadge.vue';

const props = defineProps({
    matchId: { type: Number, required: true },
});

const matchStore = useMatchStore();
// ALRHA rides the same ELR shot pipeline (elr_shots table, /elr-shots
// endpoint, Dexie-backed queue). Using this store — instead of the
// no-op we had before — is what actually persists shots to the server
// and back-populates the scoreboard. Without it every tap disappeared
// on refresh and the standings API had nothing to score.
const elrStore = useElrScoringStore();
const activeSquadId = ref(null);
const activeShooterIndex = ref(0);
const activeBlock = ref('far');
const classFilter = ref('all');

const match = computed(() => matchStore.currentMatch);

// Dual-class detection: relies on match.alrha_classes when the API is
// updated, otherwise falls back to inspecting stage tags so an older
// scoreboard payload still routes correctly.
const isDualClass = computed(() => {
    if (match.value?.is_dual_class === true) return true;
    if (Array.isArray(match.value?.alrha_classes) && match.value.alrha_classes.length > 1) {
        return true;
    }
    const seen = new Set();
    for (const stage of stages.value) {
        if (stage.alrha_class) seen.add(stage.alrha_class);
    }
    return seen.size > 1;
});

const activeClassLabel = computed(() => {
    const c = activeShooter.value?.alrha_class ?? match.value?.alrha_class;
    if (c === 'hunters') return 'LR Hunters';
    if (c === 'varmint') return 'LR Varmint';
    return match.value?.alrha_class_label ?? 'ALRHA';
});

const stages = computed(() => match.value?.elr_stages ?? match.value?.elrStages ?? []);
const squads = computed(() => match.value?.squads ?? []);
const activeSquad = computed(() => squads.value.find(s => s.id === activeSquadId.value) ?? squads.value[0]);

// Shooters currently visible for scoring. On dual-class matches we
// respect the class filter (Both/Hunters/Varmint); on single-class we
// show everyone in the relay.
const shooters = computed(() => {
    const all = activeSquad.value?.shooters ?? [];
    if (!isDualClass.value || classFilter.value === 'all') {
        return all;
    }
    return all.filter(sh => (sh.alrha_class ?? '') === classFilter.value);
});
const activeShooter = computed(() => shooters.value[activeShooterIndex.value] ?? null);

const classFilterOptions = computed(() => {
    const all = activeSquad.value?.shooters ?? [];
    const count = c => all.filter(sh => (sh.alrha_class ?? '') === c).length;
    return [
        { value: 'all', label: 'Both classes', count: all.length },
        { value: 'hunters', label: 'Hunters', count: count('hunters') },
        { value: 'varmint', label: 'Varmint', count: count('varmint') },
    ];
});

const blocks = computed(() => {
    return [
        { key: 'cbc', label: 'Cold Bore', subtitle: 'One shot, prize table only' },
        { key: 'far', label: 'Far block', subtitle: 'Top-2 distances' },
        { key: 'near', label: 'Near block', subtitle: 'Bottom-3 distances' },
    ];
});

// Only show targets that belong to the active shooter's class stage
// tree (so a Hunter on the 1000 m CBC gets the Springbuck cut-out, and
// a Varmint on the CBC gets the Jackal cut-out — even though both
// classes' targets live on the same match).
const blockTargets = computed(() => {
    const block = activeBlock.value;
    const shooterClass = activeShooter.value?.alrha_class ?? null;
    return stages.value.flatMap(stage => (stage.targets ?? []).map(t => ({
        ...t,
        _stage_class: stage.alrha_class ?? t.alrha_class ?? null,
    })))
        .filter(target => (target.alrha_block ?? '') === block)
        .filter(target => {
            const targetClass = target.alrha_class ?? target._stage_class ?? null;
            // Untagged targets belong to legacy single-class matches — show
            // them to everyone (they matched match-level class already).
            if (!targetClass) return true;
            // On dual-class matches, only show targets that match the
            // shooter's class. If the shooter has no class set (a
            // freshly imported squad), fall back to showing everything
            // so scoring still works.
            if (!shooterClass) return true;
            return targetClass === shooterClass;
        });
});

function selectSquad(id) {
    activeSquadId.value = id;
    activeShooterIndex.value = 0;
}
function setClassFilter(v) {
    classFilter.value = v;
    activeShooterIndex.value = 0;
}
function prevShooter() {
    if (activeShooterIndex.value > 0) activeShooterIndex.value--;
}
function nextShooter() {
    if (activeShooterIndex.value < shooters.value.length - 1) activeShooterIndex.value++;
}

// Jump directly to a shooter — matches the classic scoring flow so
// the RO experience is identical across scoring modes.
function jumpToShooter(idx) {
    if (idx < 0 || idx >= shooters.value.length) return;
    activeShooterIndex.value = idx;
}

// Cheap check to tint the jump chip: does this shooter have any
// recorded shot at all in the current session? Walking the whole
// shots Map is fine at match-day scale (a few hundred entries max).
function shooterHasAnyShots(shooterId) {
    for (const shot of elrStore.shots.values()) {
        if (shot.shooterId === shooterId && shot.result && shot.result !== 'not_taken') {
            return true;
        }
    }
    return false;
}

// Short display name for the jump chip so a long "Katherine
// Featherington-Smyth" doesn't blow out the horizontal strip.
function shortName(name) {
    if (!name) return '';
    const parts = name.trim().split(/\s+/);
    if (parts.length === 1) return parts[0];
    return `${parts[0]} ${parts[parts.length - 1][0]}.`;
}

function pointsForShot(shotNumber) {
    const multipliers = match.value?.elr_scoring_profile?.multipliers ?? [5, 4, 3, 2, 1];
    return multipliers[shotNumber - 1] ?? 0;
}

// Read the persisted shot for the active shooter/target/shot from the
// ELR store (which is hydrated from Dexie + server on mount and kept in
// sync every 15s). This is what makes the buttons "remember" their state
// across reloads and reflect anything scored on another device.
function resultFor(targetId, shotNumber) {
    if (!activeShooter.value) return null;
    const shots = elrStore.getShotsForTarget(activeShooter.value.id, targetId);
    const shot = shots.find(s => s.shotNumber === shotNumber);
    if (!shot) return null;
    // A neutralized shot (undo of a synced shot) counts as unrecorded so
    // the RO can re-enter it.
    if (shot.result === 'not_taken') return null;
    return shot.result;
}

function shotsHitFor(targetId) {
    if (!activeShooter.value) return 0;
    return elrStore
        .getShotsForTarget(activeShooter.value.id, targetId)
        .filter(s => s.result === 'hit')
        .length;
}

async function recordShot(target, shotNumber, result) {
    if (!activeShooter.value) return;

    const points = result === 'hit' ? pointsForShot(shotNumber) : 0;

    // Persist through the shared ELR pipeline: Dexie write immediately
    // (so the button stays lit even offline), background sync every 15s
    // pushes to POST /matches/{id}/elr-shots, and AlrhaScoringService on
    // the server picks it up when it partitions per class. This is the
    // same path ELRScoringFlow uses — ALRHA just piggy-backs.
    await elrStore.recordShot({
        matchId: props.matchId,
        shooterId: activeShooter.value.id,
        elrTargetId: target.id,
        shotNumber,
        result,
        pointsAwarded: points,
    });
}

let syncInterval;

onMounted(async () => {
    if (!matchStore.currentMatch || matchStore.currentMatch.id !== props.matchId) {
        await matchStore.fetchMatch(props.matchId);
    }
    await elrStore.initForMatch(props.matchId);

    if (squads.value.length) {
        activeSquadId.value = squads.value[0].id;
    }

    // Same 15s sync loop as ELRScoringFlow so pending taps land on the
    // server without the RO having to press a button.
    syncInterval = setInterval(async () => {
        if (!navigator.onLine) return;
        if (elrStore.pendingCount > 0) {
            await elrStore.syncShots();
        }
        try {
            await matchStore.fetchMatch(props.matchId);
            await elrStore.refreshShots(props.matchId);
        } catch { /* offline / transient */ }
    }, 15000);
});

onUnmounted(() => {
    clearInterval(syncInterval);
});
</script>
