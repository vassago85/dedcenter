<template>
    <div class="flex min-h-screen flex-col bg-app text-primary">
        <!-- Header -->
        <header class="border-b border-border bg-surface px-4 py-4">
            <div class="mx-auto flex max-w-lg items-center gap-3">
                <router-link
                    :to="{ name: 'match-overview', params: { matchId: matchId } }"
                    class="text-muted hover:text-primary"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </router-link>
                <div class="min-w-0 flex-1">
                    <h1 class="truncate text-lg font-bold">{{ matchStore.currentMatch?.name ?? 'Loading...' }}</h1>
                    <p class="text-xs text-muted">Scoring Matrix</p>
                </div>
                <OnlineIndicator />
            </div>
        </header>

        <DeviceLockBanner />

        <!-- Loading -->
        <div v-if="matchStore.loading" class="flex flex-1 items-center justify-center">
            <div class="h-8 w-8 animate-spin rounded-full border-2 border-border border-t-accent"></div>
        </div>

        <main v-else-if="matchStore.currentMatch" class="mx-auto w-full max-w-lg flex-1 px-4 py-5">
            <!-- Progress summary -->
            <div class="mb-5 rounded-xl border border-border bg-surface px-4 py-3 text-center">
                <p class="text-2xl font-bold">{{ scoredCount }} <span class="text-sm font-normal text-muted">of</span> {{ totalCells }} <span class="text-sm font-normal text-muted">scored</span></p>
                <div class="mt-2 h-1.5 rounded-full bg-surface-2">
                    <div
                        class="h-full rounded-full bg-[#22c55e] transition-all duration-300"
                        :style="{ width: progressPercent + '%' }"
                    ></div>
                </div>
            </div>

            <!-- Distance cards -->
            <div class="space-y-3">
                <div
                    v-for="ts in targetSets"
                    :key="ts.id"
                    class="rounded-xl border overflow-hidden transition-all"
                    :class="distanceCardClass(ts.id)"
                >
                    <!-- Distance header (tappable) -->
                    <button
                        class="flex w-full items-center gap-3 px-4 py-4 text-left transition-colors hover:bg-surface-2"
                        @click="toggleDistance(ts.id)"
                    >
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg text-sm font-bold"
                             :class="distanceBadgeClass(ts.id)">
                            {{ distanceStatus(ts.id).scored }}/{{ distanceStatus(ts.id).total }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold">{{ ts.label }}</p>
                            <p class="text-xs text-muted">{{ ts.distance_meters }}m &middot; {{ distanceStatusLabel(ts.id) }}</p>
                        </div>
                        <div v-if="ts.distance_multiplier" class="text-xs font-bold text-amber-400">{{ ts.distance_multiplier }}x</div>
                        <svg
                            class="h-5 w-5 flex-shrink-0 text-muted transition-transform duration-200"
                            :class="{ 'rotate-180': selectedDistance === ts.id }"
                            fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>

                    <!-- Expanded relay list -->
                    <div v-if="selectedDistance === ts.id" class="border-t border-border">
                        <div class="divide-y divide-border/50">
                            <div
                                v-for="(squad, idx) in squads"
                                :key="squad.id"
                                class="bg-surface"
                            >
                                <!-- Squad row: tap = Roll Call (unchanged),
                                     tap chevron = expand shooter list. Split
                                     into two independent buttons so ROs who
                                     just want to walk the whole relay keep
                                     the one-tap path they know. -->
                                <div class="flex w-full items-center gap-1 px-2 py-1">
                                    <button
                                        class="flex flex-1 items-center gap-3 rounded-lg px-2 py-2 text-left transition-colors hover:bg-surface-2 active:scale-[0.99]"
                                        @click="openCell(squad.id, ts.id)"
                                    >
                                        <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-md text-xs font-bold"
                                             :class="relayCellBadge(squad.id, ts.id)">
                                            <template v-if="cellStatus(squad.id, ts.id) === 'scored'">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                                </svg>
                                            </template>
                                            <template v-else>
                                                {{ idx + 1 }}
                                            </template>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-medium">Relay {{ idx + 1 }}</p>
                                            <p class="text-xs text-muted">{{ squad.name }} &middot; {{ activeShooterCount(squad) }} shooters</p>
                                        </div>
                                        <div class="text-right">
                                            <span v-if="cellStatus(squad.id, ts.id) === 'scored'" class="text-xs font-bold text-[#22c55e]">Complete</span>
                                            <span v-else-if="cellStatus(squad.id, ts.id) === 'in-progress'" class="text-xs font-bold text-[#f59e0b]">{{ cellFraction(squad.id, ts.id) }}</span>
                                            <span v-else class="text-xs text-muted">Pending</span>
                                        </div>
                                        <svg class="h-4 w-4 flex-shrink-0 text-muted" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                        </svg>
                                    </button>
                                    <button
                                        class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg text-muted transition-colors hover:bg-surface-2 hover:text-primary"
                                        :aria-label="isSquadExpanded(squad.id, ts.id) ? 'Hide shooters' : 'Show shooters'"
                                        @click.stop="toggleSquadShooters(squad.id, ts.id)"
                                    >
                                        <svg class="h-4 w-4 transition-transform" :class="{ 'rotate-180': isSquadExpanded(squad.id, ts.id) }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                        </svg>
                                    </button>
                                </div>

                                <!-- Shooter drill-down: jump to any shooter in
                                     this squad at this distance without going
                                     through Roll Call. Handy on match day
                                     when the RO already knows who they need
                                     to re-score. -->
                                <div
                                    v-if="isSquadExpanded(squad.id, ts.id)"
                                    class="border-t border-border/70 bg-surface-2/30 px-4 py-2"
                                >
                                    <p class="mb-2 text-[10px] font-semibold uppercase tracking-widest text-muted">Jump to shooter</p>
                                    <div v-if="squadShooters(squad).length === 0" class="px-2 py-2 text-xs text-muted">
                                        No active shooters in this squad.
                                    </div>
                                    <div v-else class="grid grid-cols-1 gap-1.5 sm:grid-cols-2">
                                        <button
                                            v-for="shooter in squadShooters(squad)"
                                            :key="shooter.id"
                                            class="flex items-center gap-2 rounded-lg border border-border bg-surface px-3 py-2 text-left text-xs transition-colors hover:border-accent hover:bg-surface-2 active:scale-[0.98]"
                                            @click="openShooter(squad.id, ts.id, shooter.id)"
                                        >
                                            <div class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-md text-[10px] font-bold"
                                                 :class="shooterCellBadge(shooter, ts.id)">
                                                {{ shooter.bib_number ?? '?' }}
                                            </div>
                                            <span class="min-w-0 flex-1 truncate font-medium text-primary">{{ shooter.name }}</span>
                                            <span class="text-[10px] text-muted">{{ shooterCellLabel(shooter, ts.id) }}</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useMatchStore } from '../stores/matchStore';
import { useScoringStore } from '../stores/scoringStore';
import OnlineIndicator from '../components/OnlineIndicator.vue';
import DeviceLockBanner from '../components/DeviceLockBanner.vue';

const props = defineProps({
    matchId: { type: Number, required: true },
});

const router = useRouter();
const matchStore = useMatchStore();
const scoringStore = useScoringStore();

const matchId = computed(() => props.matchId);
const targetSets = computed(() => matchStore.targetSets);
const squads = computed(() => matchStore.squads);
const matrix = computed(() => matchStore.completionMatrix);
const selectedDistance = ref(null);
// Which `${squadId}:${targetSetId}` cells are showing their shooter
// drill-down. Persisted only in memory — a rebuild resets to a clean
// collapsed state so the matrix doesn't feel noisy on first open.
const expandedShooters = ref(new Set());

const totalCells = computed(() => squads.value.length * targetSets.value.length);
const scoredCount = computed(() => {
    let count = 0;
    for (const squadId in matrix.value) {
        for (const tsId in matrix.value[squadId]) {
            if (matrix.value[squadId][tsId].status === 'scored') count++;
        }
    }
    return count;
});
const progressPercent = computed(() => {
    if (!totalCells.value) return 0;
    return Math.round((scoredCount.value / totalCells.value) * 100);
});

function distanceStatus(tsId) {
    let scored = 0;
    let inProgress = 0;
    const total = squads.value.length;
    for (const squad of squads.value) {
        const status = cellStatus(squad.id, tsId);
        if (status === 'scored') scored++;
        else if (status === 'in-progress') inProgress++;
    }
    return { scored, inProgress, total };
}

function distanceStatusLabel(tsId) {
    const s = distanceStatus(tsId);
    if (s.scored === s.total) return 'All relays complete';
    if (s.scored === 0 && s.inProgress === 0) return 'Not started';
    return `${s.scored} complete, ${s.inProgress} in progress`;
}

function distanceCardClass(tsId) {
    const s = distanceStatus(tsId);
    if (s.scored === s.total) return 'border-[#22c55e]/60 bg-[#22c55e]/5';
    if (s.scored > 0 || s.inProgress > 0) return 'border-[#f59e0b]/60 bg-[#f59e0b]/5';
    return 'border-border bg-surface';
}

function distanceBadgeClass(tsId) {
    const s = distanceStatus(tsId);
    if (s.scored === s.total) return 'bg-[#22c55e]/20 text-[#22c55e]';
    if (s.scored > 0 || s.inProgress > 0) return 'bg-[#f59e0b]/20 text-[#f59e0b]';
    return 'bg-surface-2 text-muted';
}

function cellStatus(squadId, tsId) {
    return matrix.value[squadId]?.[tsId]?.status ?? 'pending';
}

function cellFraction(squadId, tsId) {
    const cell = matrix.value[squadId]?.[tsId];
    if (!cell) return '';
    return `${cell.actual}/${cell.expected}`;
}

function relayCellBadge(squadId, tsId) {
    const status = cellStatus(squadId, tsId);
    if (status === 'scored') return 'bg-[#22c55e]/20 text-[#22c55e]';
    if (status === 'in-progress') return 'bg-[#f59e0b]/20 text-[#f59e0b]';
    return 'bg-surface-2 text-muted';
}

function activeShooterCount(squad) {
    return (squad.shooters ?? []).filter(s => s.status === 'active').length;
}

// Only active shooters get a jump chip — withdrawn/DQ'd shooters
// shouldn't be scoreable directly, and Roll Call is still the place to
// re-enable them.
function squadShooters(squad) {
    return (squad.shooters ?? []).filter(s => s.status === 'active');
}

// Per-shooter progress at a given target set. Not stored on the
// completionMatrix (which is squad-level) so we recompute inline from
// the scores array. Cheap because we already have gong ids on the
// target set and scores in memory.
function shooterProgress(shooterId, tsId) {
    const ts = targetSets.value.find(t => t.id === tsId);
    if (!ts) return { actual: 0, expected: 0, status: 'pending' };
    const gongIds = new Set((ts.gongs ?? []).map(g => g.id));
    const expected = gongIds.size;
    const scores = matchStore.currentMatch?.scores ?? [];
    let actual = 0;
    for (const s of scores) {
        if (s.shooter_id === shooterId && gongIds.has(s.gong_id)) actual++;
    }
    const status = actual === 0 ? 'pending' : (actual >= expected ? 'scored' : 'in-progress');
    return { actual, expected, status };
}

function shooterCellBadge(shooter, tsId) {
    const p = shooterProgress(shooter.id, tsId);
    if (p.status === 'scored') return 'bg-[#22c55e]/20 text-[#22c55e]';
    if (p.status === 'in-progress') return 'bg-[#f59e0b]/20 text-[#f59e0b]';
    return 'bg-surface-2 text-muted';
}

function shooterCellLabel(shooter, tsId) {
    const p = shooterProgress(shooter.id, tsId);
    if (p.status === 'scored') return 'Scored';
    if (p.status === 'in-progress') return `${p.actual}/${p.expected}`;
    return 'Pending';
}

function toggleDistance(tsId) {
    selectedDistance.value = selectedDistance.value === tsId ? null : tsId;
}

function cellKey(squadId, tsId) {
    return `${squadId}:${tsId}`;
}

function isSquadExpanded(squadId, tsId) {
    return expandedShooters.value.has(cellKey(squadId, tsId));
}

function toggleSquadShooters(squadId, tsId) {
    const key = cellKey(squadId, tsId);
    const next = new Set(expandedShooters.value);
    if (next.has(key)) {
        next.delete(key);
    } else {
        next.add(key);
    }
    expandedShooters.value = next;
}

function openCell(squadId, tsId) {
    router.push({
        name: 'roll-call',
        params: { matchId: matchId.value, squadId, targetSetId: tsId },
    });
}

// Jump straight into scoring at a specific shooter — bypasses Roll
// Call. The scoring view reads `?shooter=` on mount and sets
// currentShooterIndex accordingly, so the RO taps a name and starts
// on that person's row for this distance.
function openShooter(squadId, tsId, shooterId) {
    router.push({
        name: 'scoped-scoring',
        params: { matchId: matchId.value, squadId, targetSetId: tsId },
        query: { shooter: shooterId },
    });
}

onMounted(async () => {
    await matchStore.fetchMatch(matchId.value);

    if (matchStore.currentMatch?.status === 'completed') {
        router.replace({ name: 'match-overview', params: { matchId: matchId.value } });
        return;
    }

    const serverScores = matchStore.currentMatch?.scores ?? [];
    await scoringStore.initForMatch(matchId.value, serverScores);
    const merged = [];
    for (const s of scoringStore.scores.values()) {
        merged.push({ shooter_id: s.shooterId, gong_id: s.gongId, is_hit: s.isHit });
    }
    if (matchStore.currentMatch) matchStore.currentMatch.scores = merged;

    for (const ts of targetSets.value) {
        const s = distanceStatus(ts.id);
        if (s.scored < s.total) {
            selectedDistance.value = ts.id;
            break;
        }
    }
});
</script>
