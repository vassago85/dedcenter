<template>
    <div class="min-h-screen bg-slate-900 text-white">
        <header class="border-b border-slate-700 bg-slate-800 px-4 py-4">
            <div class="mx-auto flex max-w-lg items-center gap-3">
                <router-link :to="{ name: 'match-overview', params: { matchId: props.matchId } }" class="text-slate-400 hover:text-white">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </router-link>
                <h1 class="text-lg font-bold truncate">Select Squad</h1>
            </div>
        </header>

        <DeviceLockBanner />

        <main class="mx-auto max-w-lg px-4 py-6">
            <div v-if="matchStore.loading" class="flex justify-center py-12">
                <div class="h-8 w-8 animate-spin rounded-full border-2 border-slate-600 border-t-red-500"></div>
            </div>

            <template v-else-if="matchStore.currentMatch">
                <p class="mb-4 text-sm text-slate-400">
                    Choose a squad to score for this session.
                </p>

                <div v-if="!matchStore.squads.length" class="rounded-xl border border-slate-700 bg-slate-800 p-8 text-center">
                    <p class="text-slate-400">No squads have been set up for this match.</p>
                </div>

                <div v-else class="space-y-3">
                    <button
                        v-for="squad in matchStore.squads"
                        :key="squad.id"
                        @click="selectSquad(squad)"
                        class="w-full rounded-xl border border-slate-700 bg-slate-800 p-4 text-left transition-colors hover:border-red-600 hover:bg-slate-700/50 active:bg-slate-700"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="font-semibold text-white">{{ squad.name }}</h3>
                                <p class="mt-1 text-sm text-slate-400">{{ squad.shooters.length }} shooters</p>
                            </div>
                            <svg class="h-5 w-5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                            </svg>
                        </div>
                        <div v-if="squad.shooters.length" class="mt-2 flex flex-wrap gap-1">
                            <span
                                v-for="sh in squad.shooters"
                                :key="sh.id"
                                class="rounded bg-slate-700 px-2 py-0.5 text-xs text-slate-300"
                            >{{ sh.name }}</span>
                        </div>
                    </button>
                </div>
            </template>
        </main>
    </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useMatchStore } from '../stores/matchStore';
import { useScoringStore } from '../stores/scoringStore';
import DeviceLockBanner from '../components/DeviceLockBanner.vue';

const props = defineProps({
    matchId: { type: Number, required: true },
});

const router = useRouter();
const matchStore = useMatchStore();
const scoringStore = useScoringStore();

// Pick a squad for this scoring session and hand off to ScoringFlow
// positioned on that squad (not just the generic scoring view, which
// would re-prompt for stage/squad).
async function selectSquad(squad) {
    // Make sure scoringStore is initialised for this match so jumpToSquad
    // has the correct squad ordering loaded.
    const scores = matchStore.currentMatch?.scores ?? [];
    await scoringStore.initForMatch(props.matchId, scores);

    const squads = (matchStore.squads ?? []).slice().sort(
        (a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0)
    );
    const idx = squads.findIndex(s => s.id === squad.id);
    if (idx >= 0 && typeof scoringStore.jumpToSquad === 'function') {
        scoringStore.jumpToSquad(idx);
    }

    router.push({ name: 'scoring', params: { matchId: props.matchId } });
}

onMounted(async () => {
    if (!matchStore.currentMatch || matchStore.currentMatch.id !== props.matchId) {
        await matchStore.fetchMatch(props.matchId);
    }
});
</script>
