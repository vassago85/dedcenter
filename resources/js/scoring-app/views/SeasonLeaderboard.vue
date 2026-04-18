<template>
    <div class="min-h-screen bg-app text-primary">
        <header class="border-b border-border bg-surface px-4 py-4">
            <div class="mx-auto flex max-w-4xl items-center gap-3">
                <router-link :to="{ name: 'season-list' }" class="text-muted hover:text-primary">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </router-link>
                <h1 class="text-lg font-bold">Season Leaderboard</h1>
            </div>
        </header>

        <main class="mx-auto max-w-4xl px-4 py-6">
            <div v-if="loading" class="flex justify-center py-12">
                <div class="h-8 w-8 animate-spin rounded-full border-2 border-border border-t-accent"></div>
            </div>

            <div v-else-if="error" class="rounded-xl border border-red-800 bg-red-900/30 p-4 text-center">
                <p class="text-red-300">{{ error }}</p>
            </div>

            <div v-else>
                <div v-if="seasonName" class="mb-5 text-center">
                    <h2 class="text-xl font-bold">{{ seasonName }}</h2>
                    <p v-if="seasonDates" class="text-sm text-muted">{{ seasonDates }}</p>
                </div>

                <div v-if="!standings.length" class="rounded-xl border border-border bg-surface p-8 text-center">
                    <p class="text-muted">No standings yet. Matches need to be scored and assigned to this season.</p>
                </div>

                <div v-else>
                    <p class="mb-3 text-center text-xs text-muted">
                        Each match scored out of its own points value (regular = 100, final = 200).
                        Season total = sum of your <strong>best 3</strong> results.
                        Match ranking by score; ties broken by matches played.
                    </p>

                    <div class="overflow-x-auto rounded-xl border border-border bg-surface">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-border text-left text-muted">
                                    <th class="px-3 py-3 text-center w-10">#</th>
                                    <th class="px-3 py-3">Shooter</th>
                                    <th class="px-3 py-3 text-center w-16">Played</th>
                                    <th
                                        v-for="match in matchColumns"
                                        :key="'mh-' + match.match_id"
                                        class="px-3 py-3 text-center text-xs tabular-nums whitespace-nowrap"
                                        :class="match.points_value >= 200 ? 'text-amber-300' : 'text-muted'"
                                    >
                                        <div class="truncate max-w-[110px]">{{ match.match_name }}</div>
                                        <div class="text-[10px] text-muted">/ {{ match.points_value }}</div>
                                    </th>
                                    <th class="px-3 py-3 text-right text-amber-400 font-bold whitespace-nowrap">Best 3 / {{ seasonCap }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr
                                    v-for="entry in standings"
                                    :key="entry.user_id"
                                    class="transition-colors hover:bg-surface-2"
                                    :class="rankRowClass(entry.rank)"
                                >
                                    <td class="px-3 py-3 text-center">
                                        <span
                                            v-if="entry.rank <= 3"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-full text-sm font-bold"
                                            :class="medalClass(entry.rank)"
                                        >{{ entry.rank }}</span>
                                        <span v-else class="text-muted">{{ entry.rank }}</span>
                                    </td>
                                    <td class="px-3 py-3 font-medium">{{ entry.name }}</td>
                                    <td class="px-3 py-3 text-center tabular-nums">{{ entry.matches_played }}</td>
                                    <td
                                        v-for="match in matchColumns"
                                        :key="'mc-' + entry.user_id + '-' + match.match_id"
                                        class="px-3 py-3 text-center tabular-nums"
                                    >
                                        <span
                                            v-if="getResult(entry, match.match_id)"
                                            :class="[
                                                getResult(entry, match.match_id).counted ? 'font-bold text-primary' : 'text-muted line-through decoration-muted/50',
                                                match.points_value >= 200 ? 'ring-1 ring-amber-500/40 rounded px-1.5 py-0.5' : ''
                                            ]"
                                            :title="getResult(entry, match.match_id).counted ? 'Counted in best 3' : 'Dropped (outside best 3)'"
                                        >{{ getResult(entry, match.match_id).relative_score }}</span>
                                        <span v-else class="text-muted/60">—</span>
                                    </td>
                                    <td class="px-3 py-3 text-right tabular-nums text-lg font-bold text-amber-400">{{ entry.best3_total }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    seasonId: { type: Number, required: true },
});

const standings = ref([]);
const seasonName = ref('');
const seasonDates = ref('');
const loading = ref(true);
const error = ref(null);

// Derive the season's match columns from the first entry that has match_results.
// Every shooter's match_results contain the same set of matches (missing = DNS).
const matchColumns = computed(() => {
    const seen = new Map();
    for (const entry of standings.value) {
        for (const r of entry.match_results || []) {
            if (!seen.has(r.match_id)) {
                seen.set(r.match_id, {
                    match_id: r.match_id,
                    match_name: r.match_name,
                    match_date: r.match_date,
                    points_value: r.points_value,
                });
            }
        }
    }
    return Array.from(seen.values()).sort((a, b) => (a.match_date || '').localeCompare(b.match_date || ''));
});

// Season cap = top 3 points_values across all matches, summed.
const seasonCap = computed(() => {
    return matchColumns.value
        .map((m) => m.points_value)
        .sort((a, b) => b - a)
        .slice(0, 3)
        .reduce((a, b) => a + b, 0) || 300;
});

function getResult(entry, matchId) {
    return (entry.match_results || []).find((r) => r.match_id === matchId) || null;
}

function medalClass(rank) {
    if (rank === 1) return 'bg-amber-500 text-black';
    if (rank === 2) return 'bg-slate-400 text-black';
    if (rank === 3) return 'bg-orange-600 text-white';
    return '';
}

function rankRowClass(rank) {
    if (rank === 1) return 'bg-amber-900/15';
    if (rank === 2) return 'bg-slate-600/10';
    if (rank === 3) return 'bg-orange-900/10';
    return '';
}

onMounted(async () => {
    try {
        const { data } = await axios.get(`/api/seasons/${props.seasonId}/standings`);
        seasonName.value = data.season?.name ?? '';
        if (data.season?.start_date && data.season?.end_date) {
            seasonDates.value = `${data.season.start_date} — ${data.season.end_date}`;
        }
        standings.value = data.standings ?? [];
    } catch (e) {
        error.value = 'Unable to load season standings.';
    } finally {
        loading.value = false;
    }
});
</script>
