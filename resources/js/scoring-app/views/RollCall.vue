<template>
    <div class="flex min-h-screen flex-col bg-app text-primary">
        <!-- Header -->
        <header class="border-b border-border bg-surface px-4 py-4">
            <div class="mx-auto flex max-w-lg items-center gap-3">
                <router-link
                    :to="{ name: 'scoring-matrix', params: { matchId: matchId } }"
                    class="text-muted hover:text-primary"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </router-link>
                <div class="min-w-0 flex-1">
                    <h1 class="truncate text-lg font-bold">Relay {{ relayIndex }} &mdash; {{ distanceLabel }}</h1>
                    <p class="text-xs text-muted">Roll Call</p>
                </div>
                <OnlineIndicator />
            </div>
        </header>

        <!-- Loading -->
        <div v-if="matchStore.loading" class="flex flex-1 items-center justify-center">
            <div class="h-8 w-8 animate-spin rounded-full border-2 border-border border-t-accent"></div>
        </div>

        <template v-else-if="squad">
            <main class="mx-auto w-full max-w-lg flex-1 px-4 py-5">
                <p class="mb-4 text-sm text-muted">
                    Toggle shooters who are <span class="font-semibold text-primary">present</span> for this stage. Absent shooters will be marked as withdrawn.
                </p>

                <div class="space-y-2">
                    <div
                        v-for="shooter in shooters"
                        :key="shooter.id"
                        class="flex items-center justify-between rounded-xl border bg-surface px-4 py-3 transition-colors"
                        :class="shooter.status === 'active' ? 'border-border' : 'border-border/50 opacity-50'"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">{{ shooter.name }}</p>
                            <p v-if="shooter.bib_number" class="text-xs text-muted">Bib #{{ shooter.bib_number }}</p>
                        </div>

                        <button
                            @click="toggleShooter(shooter)"
                            class="relative ml-3 h-7 w-12 flex-shrink-0 rounded-full transition-colors focus:outline-none"
                            :class="shooter.status === 'active' ? 'bg-[#22c55e]' : 'bg-surface-2'"
                            role="switch"
                            :aria-checked="shooter.status === 'active'"
                        >
                            <span
                                class="absolute top-0.5 left-0.5 h-6 w-6 rounded-full bg-white shadow transition-transform"
                                :class="shooter.status === 'active' ? 'translate-x-5' : 'translate-x-0'"
                            ></span>
                        </button>
                    </div>
                </div>
            </main>

            <!-- Bottom action -->
            <div class="border-t border-border bg-surface px-4 py-4">
                <div class="mx-auto max-w-lg">
                    <button
                        @click="startScoring"
                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-accent py-4 text-lg font-bold text-white shadow-lg transition-colors hover:bg-accent-hover active:scale-[0.98]"
                    >
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                        </svg>
                        Start Scoring
                    </button>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useMatchStore } from '../stores/matchStore';
import OnlineIndicator from '../components/OnlineIndicator.vue';

const props = defineProps({
    matchId: { type: Number, required: true },
    squadId: { type: Number, required: true },
    targetSetId: { type: Number, required: true },
});

const router = useRouter();
const matchStore = useMatchStore();

const matchId = computed(() => props.matchId);
const squadId = computed(() => props.squadId);
const targetSetId = computed(() => props.targetSetId);

const squad = computed(() =>
    matchStore.squads.find(s => s.id === squadId.value)
);

const relayIndex = computed(() => {
    const idx = matchStore.squads.findIndex(s => s.id === squadId.value);
    return idx >= 0 ? idx + 1 : '?';
});

const targetSet = computed(() =>
    matchStore.targetSets.find(ts => ts.id === targetSetId.value)
);

const distanceLabel = computed(() =>
    targetSet.value ? `${targetSet.value.distance_meters}m` : ''
);

const shooters = computed(() => squad.value?.shooters ?? []);

async function toggleShooter(shooter) {
    const newStatus = shooter.status === 'active' ? 'withdrawn' : 'active';
    try {
        await matchStore.updateShooterStatus(matchId.value, shooter.id, newStatus);
    } catch (e) {
        console.error('Failed to update shooter status', e);
        shooter.status = newStatus;
    }
}

function startScoring() {
    router.push({
        name: 'scoped-scoring',
        params: {
            matchId: matchId.value,
            squadId: squadId.value,
            targetSetId: targetSetId.value,
        },
    });
}

onMounted(async () => {
    if (!matchStore.currentMatch || matchStore.currentMatch.id !== matchId.value) {
        await matchStore.fetchMatch(matchId.value);
    }
});
</script>
