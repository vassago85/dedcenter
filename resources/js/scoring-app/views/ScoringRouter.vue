<template>
    <div v-if="!ready" class="flex min-h-screen items-center justify-center bg-slate-900">
        <div class="h-10 w-10 animate-spin rounded-full border-4 border-slate-600 border-t-amber-500"></div>
    </div>
    <component v-else :is="scoringComponent" :matchId="matchId" />
</template>

<script setup>
import { computed, defineAsyncComponent, onMounted, ref } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useMatchStore } from '../stores/matchStore';

const loaders = {
    standard: () => import('./ScoringFlow.vue'),
    prs: () => import('./PrsScoringFlow.vue'),
    elr: () => import('./ElrScoringFlow.vue'),
    team: () => import('./TeamSequenceFlow.vue'),
    alrha: () => import('./AlrhaScoringFlow.vue'),
};
const flows = Object.fromEntries(
    Object.entries(loaders).map(([key, load]) => [key, defineAsyncComponent(load)])
);

function flowKey(match) {
    if (match?.scoring_type === 'prs') return 'prs';
    if (match?.scoring_type === 'alrha') return 'alrha';
    if (match?.scoring_type === 'elr') {
        return match.elr_engagement_mode === 'team_sequence' ? 'team' : 'elr';
    }
    return 'standard';
}

// The service worker only caches chunks it has fetched, so pull the other
// flows once the active one is up to keep every flow available offline.
function warmOtherFlows(activeKey) {
    const warm = () => {
        for (const [key, load] of Object.entries(loaders)) {
            if (key !== activeKey) load().catch(() => {});
        }
    };
    if ('requestIdleCallback' in window) window.requestIdleCallback(warm);
    else setTimeout(warm, 2000);
}

const props = defineProps({
    matchId: { type: Number, required: true },
});

const router = useRouter();
const route = useRoute();
const matchStore = useMatchStore();
const ready = ref(false);

const scoringComponent = computed(() => flows[flowKey(matchStore.currentMatch)]);

onMounted(async () => {
    if (!matchStore.currentMatch || matchStore.currentMatch.id !== props.matchId) {
        await matchStore.fetchMatch(props.matchId);
    }
    // Bounce deep-links into a live scoring flow back to the overview when the
    // match is already scored — UI makes the state obvious, banner explains why,
    // and MDs still get the 'Re-open' path. Exception: when the deep-link is
    // specifically asking us to open the correction modal (?correct=<id>), we
    // let the child component through so the modal can offer its own one-tap
    // reopen-and-fix path instead of dumping the MD on the match overview.
    if (matchStore.currentMatch?.status === 'completed' && !route.query.correct) {
        router.replace({ name: 'match-overview', params: { matchId: props.matchId } });
        return;
    }
    // Relay matches score from the matrix. This route stays for PRS, ELR,
    // ALRHA, and the ?correct= deep link from the corrections feed.
    const scoringType = matchStore.currentMatch?.scoring_type;
    const relayMatch = scoringType && !['prs', 'elr', 'alrha'].includes(scoringType);
    if (relayMatch && !route.query.correct) {
        router.replace({ name: 'scoring-matrix', params: { matchId: props.matchId } });
        return;
    }
    // Resolve the flow chunk behind the existing spinner.
    const activeKey = flowKey(matchStore.currentMatch);
    try {
        await loaders[activeKey]();
    } catch { /* the async component reports the load failure */ }
    ready.value = true;
    warmOtherFlows(activeKey);
});
</script>
