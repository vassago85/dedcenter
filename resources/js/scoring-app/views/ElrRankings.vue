<template>
    <div class="min-h-screen bg-app text-primary">
        <header class="border-b border-border bg-surface px-4 py-4">
            <div class="mx-auto flex max-w-5xl items-center gap-3">
                <router-link
                    :to="{ name: 'match-overview', params: { matchId: props.matchId } }"
                    class="text-muted hover:text-primary"
                    aria-label="Back"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </router-link>
                <h1 class="text-lg font-bold">Rankings</h1>
                <span class="rounded bg-sky-600 px-1.5 py-0.5 text-[10px] font-bold uppercase">ELR</span>
                <div class="ml-auto flex items-center gap-3">
                    <span v-if="!loading && !error" class="flex items-center gap-1 text-[10px] text-muted">
                        <span class="h-1.5 w-1.5 rounded-full bg-green-500 animate-pulse"></span>
                        LIVE
                    </span>
                    <button
                        @click="fetchData"
                        :disabled="loading"
                        class="rounded-lg bg-surface-2 px-3 py-1.5 text-xs font-medium transition-colors hover:bg-border disabled:opacity-50"
                    >
                        Refresh
                    </button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-3 py-4">
            <div v-if="matchName" class="mb-3">
                <p class="text-base font-semibold">{{ matchName }}</p>
            </div>

            <!-- View tabs -->
            <div class="mb-4 flex gap-1.5">
                <button
                    v-for="t in tabs"
                    :key="t.id"
                    @click="activeTab = t.id"
                    :class="[
                        'rounded-lg px-3 py-1.5 text-sm font-medium transition-colors',
                        activeTab === t.id ? 'bg-sky-600 text-white' : 'bg-surface-2 text-muted hover:text-primary',
                    ]"
                >
                    {{ t.label }}
                </button>
            </div>

            <!-- Export buttons (staff only) -->
            <div v-if="canExport && stages.length" class="mb-4 flex flex-wrap gap-2">
                <a :href="csvUrl" class="rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-medium text-secondary hover:text-primary">
                    Export CSV
                </a>
                <a :href="pdfUrl" class="rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-medium text-secondary hover:text-primary">
                    Export PDF
                </a>
            </div>

            <div v-if="loading" class="space-y-2">
                <div v-for="n in 6" :key="n" class="h-10 animate-pulse rounded-lg bg-surface-2"></div>
            </div>

            <div v-else-if="error" class="rounded-xl border border-border bg-surface p-6 text-center">
                <p class="text-sm text-muted">{{ error }}</p>
            </div>

            <div v-else-if="scoresHidden" class="rounded-xl border border-border bg-surface p-6 text-center">
                <p class="text-sm text-muted">Scores for this match have not been published yet.</p>
            </div>

            <div v-else-if="!stages.length" class="rounded-xl border border-border bg-surface p-8 text-center">
                <p class="text-sm font-medium text-primary">No completed stages yet</p>
                <p class="mt-1 text-xs text-muted">Rankings appear as soon as a team finishes a stage. Stages still in progress are excluded.</p>
            </div>

            <template v-else>
                <!-- Overall individual -->
                <div v-if="activeTab === 'overall'" class="overflow-x-auto rounded-xl border border-border bg-surface">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-border text-left text-[11px] uppercase tracking-wide text-muted">
                                <th class="px-3 py-2 text-right">#</th>
                                <th class="px-3 py-2">Shooter</th>
                                <th class="px-3 py-2">Division</th>
                                <th class="px-3 py-2">Team</th>
                                <th v-for="s in stages" :key="s.stage_id" class="px-3 py-2 text-right whitespace-nowrap">{{ s.label }}</th>
                                <th class="px-3 py-2 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in data.overall" :key="row.shooter_id" :class="rowClass(row.rank)">
                                <td class="px-3 py-2 text-right font-bold" :class="rankColor(row.rank)">{{ rankLabel(row) }}</td>
                                <td class="px-3 py-2 font-medium">{{ row.name }}</td>
                                <td class="px-3 py-2 text-muted">{{ row.division || '—' }}</td>
                                <td class="px-3 py-2 text-muted">{{ row.team || '—' }}</td>
                                <td v-for="s in stages" :key="s.stage_id" class="px-3 py-2 text-right tabular-nums">{{ cell(row.stage_scores[s.stage_id]) }}</td>
                                <td class="px-3 py-2 text-right font-bold tabular-nums">{{ fmt(row.total_score) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Teams -->
                <div v-else-if="activeTab === 'teams'" class="overflow-x-auto rounded-xl border border-border bg-surface">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-border text-left text-[11px] uppercase tracking-wide text-muted">
                                <th class="px-3 py-2 text-right">#</th>
                                <th class="px-3 py-2">Team</th>
                                <th class="px-3 py-2">Divisions</th>
                                <th v-for="s in stages" :key="s.stage_id" class="px-3 py-2 text-right whitespace-nowrap">{{ s.label }}</th>
                                <th class="px-3 py-2 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in data.teams" :key="row.team_id" :class="rowClass(row.rank)">
                                <td class="px-3 py-2 text-right font-bold" :class="rankColor(row.rank)">{{ rankLabel(row) }}</td>
                                <td class="px-3 py-2 font-medium">{{ row.team }}</td>
                                <td class="px-3 py-2 text-muted">{{ row.division_composition }}</td>
                                <td v-for="s in stages" :key="s.stage_id" class="px-3 py-2 text-right tabular-nums">{{ cell(row.stage_scores[s.stage_id]) }}</td>
                                <td class="px-3 py-2 text-right font-bold tabular-nums">{{ fmt(row.total_score) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Divisions -->
                <div v-else>
                    <div v-if="showDivisionSelector" class="mb-3 flex flex-wrap items-center gap-2">
                        <span class="text-xs uppercase tracking-wide text-muted">Division</span>
                        <button
                            v-for="d in data.divisions"
                            :key="d.division_id ?? d.division"
                            @click="selectedDivision = divisionKey(d)"
                            :class="[
                                'rounded-lg px-3 py-1 text-xs font-medium transition-colors',
                                selectedDivision === divisionKey(d) ? 'bg-sky-600 text-white' : 'bg-surface-2 text-muted hover:text-primary',
                            ]"
                        >
                            {{ d.division }}
                        </button>
                    </div>

                    <div v-for="d in visibleDivisions" :key="d.division_id ?? d.division" class="mb-5">
                        <h3 class="mb-2 text-sm font-semibold text-secondary">{{ d.division }}</h3>
                        <div class="overflow-x-auto rounded-xl border border-border bg-surface">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-border text-left text-[11px] uppercase tracking-wide text-muted">
                                        <th class="px-3 py-2 text-right">#</th>
                                        <th class="px-3 py-2">Shooter</th>
                                        <th class="px-3 py-2">Team</th>
                                        <th v-for="s in stages" :key="s.stage_id" class="px-3 py-2 text-right whitespace-nowrap">{{ s.label }}</th>
                                        <th class="px-3 py-2 text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in d.rows" :key="row.shooter_id" :class="rowClass(row.rank)">
                                        <td class="px-3 py-2 text-right font-bold" :class="rankColor(row.rank)">{{ rankLabel(row) }}</td>
                                        <td class="px-3 py-2 font-medium">{{ row.name }}</td>
                                        <td class="px-3 py-2 text-muted">{{ row.team || '—' }}</td>
                                        <td v-for="s in stages" :key="s.stage_id" class="px-3 py-2 text-right tabular-nums">{{ cell(row.stage_scores[s.stage_id]) }}</td>
                                        <td class="px-3 py-2 text-right font-bold tabular-nums">{{ fmt(row.total_score) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </template>
        </main>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { useMatchStore } from '../stores/matchStore';

const props = defineProps({
    matchId: { type: Number, required: true },
});

const matchStore = useMatchStore();

const tabs = [
    { id: 'overall', label: 'Overall' },
    { id: 'teams', label: 'Teams' },
    { id: 'divisions', label: 'Divisions' },
];

const activeTab = ref('overall');
const loading = ref(true);
const error = ref(null);
const scoresHidden = ref(false);
const matchName = ref('');
const selectedDivision = ref(null);

const data = reactive({ overall: [], teams: [], divisions: [] });
const stages = ref([]);

let refreshTimer = null;

const canExport = computed(() => matchStore.currentMatch?.can_export === true);

const showDivisionSelector = computed(() => data.divisions.length >= 3);

const visibleDivisions = computed(() => {
    if (!showDivisionSelector.value) return data.divisions;
    const match = data.divisions.find((d) => divisionKey(d) === selectedDivision.value);
    return match ? [match] : data.divisions.slice(0, 1);
});

const csvUrl = computed(() => {
    const base = `/scoreboard/${props.matchId}/export/elr-rankings?view=${activeTab.value}`;
    return base;
});

const pdfUrl = computed(() => `/scoreboard/${props.matchId}/export/pdf-elr-rankings`);

function divisionKey(d) {
    return d.division_id ?? d.division;
}

function rankLabel(row) {
    return row.joint ? `=${row.rank}` : row.rank;
}

function fmt(v) {
    if (v === null || v === undefined) return '—';
    const n = Number(v);
    return Number.isInteger(n) ? String(n) : n.toFixed(2);
}

function cell(v) {
    return v === null || v === undefined ? '—' : fmt(v);
}

function rowClass(rank) {
    if (rank === 1) return 'bg-amber-500/10';
    if (rank === 2) return 'bg-slate-400/10';
    if (rank === 3) return 'bg-orange-500/10';
    return '';
}

function rankColor(rank) {
    if (rank === 1) return 'text-amber-400';
    if (rank === 2) return 'text-slate-300';
    if (rank === 3) return 'text-orange-400';
    return 'text-muted';
}

async function fetchData() {
    loading.value = true;
    error.value = null;
    try {
        const { data: res } = await axios.get(`/api/matches/${props.matchId}/elr-rankings`);
        matchName.value = res.match?.name ?? '';

        if (res.match && res.match.scores_published === false) {
            scoresHidden.value = true;
            stages.value = [];
            data.overall = [];
            data.teams = [];
            data.divisions = [];
            return;
        }

        scoresHidden.value = false;
        stages.value = res.stages ?? [];
        data.overall = res.overall ?? [];
        data.teams = res.teams ?? [];
        data.divisions = res.divisions ?? [];

        if (showDivisionSelector.value && selectedDivision.value === null && data.divisions.length) {
            selectedDivision.value = divisionKey(data.divisions[0]);
        }
    } catch (e) {
        error.value = 'Rankings are not available right now.';
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    if (matchStore.currentMatch?.id !== props.matchId) {
        matchStore.fetchMatch(props.matchId, { silent: true });
    }
    fetchData();
    refreshTimer = setInterval(fetchData, 12000);
});

onUnmounted(() => {
    if (refreshTimer) clearInterval(refreshTimer);
});
</script>
