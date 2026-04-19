<template>
    <div v-if="!ready" class="flex min-h-screen items-center justify-center bg-slate-900">
        <div class="h-10 w-10 animate-spin rounded-full border-4 border-slate-600 border-t-amber-500"></div>
    </div>
    <component v-else :is="scoringComponent" :matchId="matchId" />
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useMatchStore } from '../stores/matchStore';
import ScoringFlow from './ScoringFlow.vue';
import PrsScoringFlow from './PrsScoringFlow.vue';
import ElrScoringFlow from './ElrScoringFlow.vue';

const props = defineProps({
    matchId: { type: Number, required: true },
});

const router = useRouter();
const matchStore = useMatchStore();
const ready = ref(false);

const scoringComponent = computed(() => {
    const match = matchStore.currentMatch;
    if (match && match.scoring_type === 'prs') {
        return PrsScoringFlow;
    }
    if (match && match.scoring_type === 'elr') {
        return ElrScoringFlow;
    }
    return ScoringFlow;
});

onMounted(async () => {
    if (!matchStore.currentMatch || matchStore.currentMatch.id !== props.matchId) {
        await matchStore.fetchMatch(props.matchId);
    }
    // Bounce deep-links into a live scoring flow back to the overview when the
    // match is already scored — UI makes the state obvious, banner explains why,
    // and MDs still get the 'Re-open' path.
    if (matchStore.currentMatch?.status === 'completed') {
        router.replace({ name: 'match-overview', params: { matchId: props.matchId } });
        return;
    }
    ready.value = true;
});
</script>
