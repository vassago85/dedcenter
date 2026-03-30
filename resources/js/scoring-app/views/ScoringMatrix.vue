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

            <!-- Distance column headers -->
            <div class="mb-2 grid gap-2" :style="gridStyle">
                <div class="text-xs text-muted"></div>
                <div
                    v-for="ts in targetSets"
                    :key="'hdr-' + ts.id"
                    class="truncate text-center text-xs font-semibold text-secondary"
                >
                    {{ ts.distance_meters }}m
                </div>
            </div>

            <!-- Matrix rows -->
            <div class="space-y-2">
                <div
                    v-for="(squad, idx) in squads"
                    :key="squad.id"
                    class="grid items-center gap-2"
                    :style="gridStyle"
                >
                    <div class="truncate text-sm font-medium text-secondary">Relay {{ idx + 1 }}</div>

                    <button
                        v-for="ts in targetSets"
                        :key="squad.id + '-' + ts.id"
                        class="flex h-14 items-center justify-center rounded-lg border-2 transition-all active:scale-95"
                        :class="cellClass(squad.id, ts.id)"
                        @click="openCell(squad.id, ts.id)"
                    >
                        <template v-if="cellStatus(squad.id, ts.id) === 'scored'">
                            <svg class="h-6 w-6 text-[#22c55e]" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                        </template>
                        <template v-else-if="cellStatus(squad.id, ts.id) === 'in-progress'">
                            <span class="text-xs font-bold text-[#f59e0b]">{{ cellFraction(squad.id, ts.id) }}</span>
                        </template>
                        <template v-else>
                            <span class="text-xs text-muted">&mdash;</span>
                        </template>
                    </button>
                </div>
            </div>
        </main>
    </div>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useMatchStore } from '../stores/matchStore';
import OnlineIndicator from '../components/OnlineIndicator.vue';

const props = defineProps({
    matchId: { type: Number, required: true },
});

const router = useRouter();
const matchStore = useMatchStore();

const matchId = computed(() => props.matchId);
const targetSets = computed(() => matchStore.targetSets);
const squads = computed(() => matchStore.squads);
const matrix = computed(() => matchStore.completionMatrix);

const gridStyle = computed(() => ({
    gridTemplateColumns: `5rem repeat(${targetSets.value.length}, minmax(0, 1fr))`,
}));

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

function cellStatus(squadId, tsId) {
    return matrix.value[squadId]?.[tsId]?.status ?? 'pending';
}

function cellFraction(squadId, tsId) {
    const cell = matrix.value[squadId]?.[tsId];
    if (!cell) return '';
    return `${cell.actual}/${cell.expected}`;
}

function cellClass(squadId, tsId) {
    const status = cellStatus(squadId, tsId);
    if (status === 'scored') return 'border-[#22c55e]/60 bg-[#22c55e]/10';
    if (status === 'in-progress') return 'border-[#f59e0b]/60 bg-[#f59e0b]/10';
    return 'border-border bg-surface';
}

function openCell(squadId, tsId) {
    router.push({
        name: 'roll-call',
        params: { matchId: matchId.value, squadId, targetSetId: tsId },
    });
}

onMounted(async () => {
    if (!matchStore.currentMatch || matchStore.currentMatch.id !== matchId.value) {
        await matchStore.fetchMatch(matchId.value);
    }
});
</script>
