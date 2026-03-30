<template>
    <div class="flex min-h-screen flex-col bg-app text-primary">
        <header class="border-b border-border bg-surface px-4 py-4">
            <div class="mx-auto flex max-w-lg items-center gap-3">
                <router-link to="/score" class="text-muted hover:text-primary">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </router-link>
                <h1 class="text-lg font-bold">Seasons</h1>
            </div>
        </header>

        <main class="mx-auto w-full max-w-lg flex-1 px-4 py-5">
            <div v-if="loading" class="flex justify-center py-12">
                <div class="h-8 w-8 animate-spin rounded-full border-2 border-border border-t-accent"></div>
            </div>

            <div v-else-if="!seasons.length" class="rounded-xl border border-border bg-surface p-8 text-center">
                <p class="text-muted">No seasons found.</p>
            </div>

            <div v-else class="space-y-3">
                <router-link
                    v-for="season in seasons"
                    :key="season.id"
                    :to="{ name: 'season-leaderboard', params: { seasonId: season.id } }"
                    class="block rounded-xl border border-border bg-surface p-4 transition-colors hover:bg-surface-2"
                >
                    <p class="font-semibold">{{ season.name }}</p>
                    <p class="text-xs text-muted">
                        {{ season.year }}
                        <span v-if="season.start_date && season.end_date"> &middot; {{ season.start_date }} &ndash; {{ season.end_date }}</span>
                        &middot; {{ season.matches_count }} {{ season.matches_count === 1 ? 'match' : 'matches' }}
                    </p>
                </router-link>
            </div>
        </main>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const seasons = ref([]);
const loading = ref(true);

onMounted(async () => {
    try {
        const { data } = await axios.get('/api/seasons');
        seasons.value = data.seasons ?? [];
    } catch (e) {
        console.error('Failed to load seasons', e);
    } finally {
        loading.value = false;
    }
});
</script>
