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

        <DeviceLockBanner />

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
                        :class="{
                            'border-border': shooter.status === 'active',
                            'border-red-600/50 bg-red-900/10': shooter.status === 'dq',
                            'border-border/50 opacity-50': shooter.status === 'withdrawn',
                        }"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5">
                                <p class="truncate font-medium" :class="{ 'line-through text-muted': shooter.status === 'dq' }">{{ shooter.name }}</p>
                                <span v-if="shooter.status === 'dq'" class="rounded bg-red-600/30 px-1.5 py-0.5 text-[9px] font-bold text-red-400">DQ</span>
                            </div>
                            <p v-if="shooter.bib_number" class="text-xs text-muted">Bib #{{ shooter.bib_number }}</p>
                        </div>

                        <div class="flex items-center gap-2 ml-3">
                            <button
                                v-if="shooter.status !== 'dq'"
                                @click="openDqModal(shooter)"
                                class="rounded-lg bg-red-600/20 px-2 py-1 text-[10px] font-bold text-red-400 hover:bg-red-600/30 transition-colors"
                                title="Disqualify"
                            >DQ</button>

                            <button
                                v-if="shooter.status !== 'dq'"
                                @click="toggleShooter(shooter)"
                                class="relative h-7 w-12 flex-shrink-0 rounded-full transition-colors focus:outline-none"
                                :class="shooter.status === 'active' ? 'bg-[#22c55e]' : 'bg-surface-2'"
                                role="switch"
                                :aria-checked="shooter.status === 'active'"
                            >
                                <span
                                    class="absolute top-0.5 left-0.5 h-6 w-6 rounded-full bg-white shadow transition-transform"
                                    :class="shooter.status === 'active' ? 'translate-x-5' : 'translate-x-0'"
                                ></span>
                            </button>

                            <span v-if="shooter.status === 'dq'" class="text-xs font-bold text-red-400">Disqualified</span>
                        </div>
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

        <!-- DQ Modal -->
        <div v-if="dqModal.show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60" @click.self="dqModal.show = false">
            <div class="w-full max-w-sm mx-4 rounded-xl border border-border bg-surface p-5 shadow-2xl space-y-4">
                <h3 class="text-lg font-bold text-primary">Disqualify Shooter</h3>
                <p class="text-sm text-muted">
                    DQ <span class="font-semibold text-primary">{{ dqModal.shooterName }}</span> from:
                </p>
                <div class="flex gap-2">
                    <button
                        @click="dqModal.type = 'match'"
                        class="flex-1 rounded-lg border px-3 py-2 text-sm font-medium transition-colors"
                        :class="dqModal.type === 'match' ? 'border-red-500 bg-red-600/20 text-red-400' : 'border-border text-muted hover:bg-surface-2'"
                    >Entire Match</button>
                    <button
                        @click="dqModal.type = 'stage'"
                        class="flex-1 rounded-lg border px-3 py-2 text-sm font-medium transition-colors"
                        :class="dqModal.type === 'stage' ? 'border-red-500 bg-red-600/20 text-red-400' : 'border-border text-muted hover:bg-surface-2'"
                    >This Stage Only</button>
                </div>
                <div>
                    <label class="text-xs font-medium text-muted">Reason (required)</label>
                    <textarea
                        v-model="dqModal.reason"
                        rows="3"
                        placeholder="Describe the offence..."
                        class="mt-1 w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary placeholder-[var(--color-text-muted)] focus:border-red-500"
                    ></textarea>
                    <p v-if="dqModal.error" class="mt-1 text-xs text-red-400">{{ dqModal.error }}</p>
                </div>
                <div class="flex gap-3">
                    <button @click="dqModal.show = false" class="flex-1 rounded-lg border border-border px-4 py-2.5 text-sm font-medium text-muted hover:bg-surface-2 transition-colors">Cancel</button>
                    <button
                        @click="confirmDq"
                        :disabled="dqModal.submitting"
                        class="flex-1 rounded-lg bg-red-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-red-700 transition-colors disabled:opacity-50"
                    >{{ dqModal.submitting ? 'Processing...' : 'Confirm DQ' }}</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useMatchStore } from '../stores/matchStore';
import OnlineIndicator from '../components/OnlineIndicator.vue';
import DeviceLockBanner from '../components/DeviceLockBanner.vue';

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

const dqModal = reactive({
    show: false,
    shooterId: null,
    shooterName: '',
    type: 'match',
    reason: '',
    error: '',
    submitting: false,
});

function openDqModal(shooter) {
    dqModal.shooterId = shooter.id;
    dqModal.shooterName = shooter.name;
    dqModal.type = 'match';
    dqModal.reason = '';
    dqModal.error = '';
    dqModal.submitting = false;
    dqModal.show = true;
}

async function confirmDq() {
    if (!dqModal.reason || dqModal.reason.trim().length < 5) {
        dqModal.error = 'Reason must be at least 5 characters.';
        return;
    }

    dqModal.submitting = true;
    dqModal.error = '';

    try {
        const stageId = dqModal.type === 'stage' ? targetSetId.value : null;
        await matchStore.issueDisqualification(matchId.value, dqModal.shooterId, dqModal.reason.trim(), stageId);
        dqModal.show = false;
    } catch (e) {
        dqModal.error = e.response?.data?.message || 'Failed to issue DQ.';
    } finally {
        dqModal.submitting = false;
    }
}

async function toggleShooter(shooter) {
    if (shooter.status === 'dq') return;
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
