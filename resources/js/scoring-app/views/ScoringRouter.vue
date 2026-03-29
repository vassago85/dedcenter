<template>
    <component :is="scoringComponent" :matchId="matchId" />
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useMatchStore } from '../stores/matchStore';
import ScoringFlow from './ScoringFlow.vue';
import PrsScoringFlow from './PrsScoringFlow.vue';

const props = defineProps({
    matchId: { type: Number, required: true },
});

const matchStore = useMatchStore();

const scoringComponent = computed(() => {
    const match = matchStore.currentMatch;
    if (match && match.scoring_type === 'prs') {
        return PrsScoringFlow;
    }
    return ScoringFlow;
});

onMounted(async () => {
    if (!matchStore.currentMatch || matchStore.currentMatch.id !== props.matchId) {
        await matchStore.fetchMatch(props.matchId);
    }
});
</script>
