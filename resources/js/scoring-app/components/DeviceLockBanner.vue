<template>
    <div v-if="matchStore.hasAnyLock" class="border-b border-amber-700/40 bg-amber-900/15 px-4 py-1.5">
        <div class="mx-auto flex items-center gap-2" :class="maxWidthClass">
            <svg class="h-3.5 w-3.5 flex-shrink-0 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
            </svg>
            <span class="truncate text-xs font-medium text-amber-400">
                <template v-if="matchStore.hasSquadLock && matchStore.hasStageLock">
                    {{ matchStore.lockedSquadName }} &middot; {{ matchStore.lockedStageName }}
                </template>
                <template v-else-if="matchStore.hasSquadLock">
                    Squad: {{ matchStore.lockedSquadName }}
                </template>
                <template v-else-if="matchStore.hasStageLock">
                    Stage: {{ matchStore.lockedStageName }}
                </template>
            </span>
        </div>
    </div>
</template>

<script setup>
import { useMatchStore } from '../stores/matchStore';

defineProps({
    maxWidthClass: { type: String, default: 'max-w-lg' },
});

const matchStore = useMatchStore();
</script>
