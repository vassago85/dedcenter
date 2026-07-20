<template>
    <div v-if="!ready" class="flex min-h-screen items-center justify-center bg-slate-900">
        <div class="h-10 w-10 animate-spin rounded-full border-4 border-slate-600 border-t-amber-500"></div>
    </div>
    <component v-else :is="scoringComponent" :matchId="matchId" />
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useMatchStore } from '../stores/matchStore';
import ScoringFlow from './ScoringFlow.vue';
import PrsScoringFlow from './PrsScoringFlow.vue';
import ElrScoringFlow from './ElrScoringFlow.vue';
import TeamSequenceFlow from './TeamSequenceFlow.vue';
import AlrhaScoringFlow from './AlrhaScoringFlow.vue';

const props = defineProps({
    matchId: { type: Number, required: true },
});

const router = useRouter();
const route = useRoute();
const matchStore = useMatchStore();
const ready = ref(false);

const scoringComponent = computed(() => {
    const match = matchStore.currentMatch;
    if (match && match.scoring_type === 'prs') {
        return PrsScoringFlow;
    }
    if (match && match.scoring_type === 'alrha') {
        return AlrhaScoringFlow;
    }
    if (match && match.scoring_type === 'elr') {
        return match.elr_engagement_mode === 'team_sequence' ? TeamSequenceFlow : ElrScoringFlow;
    }
    return ScoringFlow;
});

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
    ready.value = true;
});
</script>
