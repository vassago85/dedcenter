<template>
    <div v-if="sponsor" class="border-t border-slate-700/50 bg-slate-900/60 px-4 py-1.5">
        <div class="mx-auto flex max-w-lg items-center justify-center gap-2">
            <img
                v-if="sponsor.logo_url"
                :src="sponsor.logo_url"
                :alt="sponsor.name"
                class="h-4 w-auto max-w-[80px] object-contain opacity-60"
            />
            <span class="text-[10px] tracking-wide text-slate-500">
                {{ sponsor.label }} {{ sponsor.name }}
            </span>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useMatchStore } from '../stores/matchStore';

const matchStore = useMatchStore();
const sponsor = computed(() => matchStore.currentMatch?.scoring_sponsor ?? null);
</script>
