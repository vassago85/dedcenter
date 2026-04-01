import { ref, onMounted } from 'vue';
import db, { cacheMatches, getCachedMatches, cacheScoreboard, getCachedScoreboard } from '../lib/offlineDb';

export function useOfflineData() {
    const isOnline = ref(navigator.onLine);

    onMounted(() => {
        window.addEventListener('online', () => isOnline.value = true);
        window.addEventListener('offline', () => isOnline.value = false);
    });

    async function fetchMatchesWithCache() {
        try {
            const resp = await fetch('/api/matches');
            if (resp.ok) {
                const data = await resp.json();
                const matches = data.data || data;
                await cacheMatches(matches);
                return matches;
            }
        } catch {}

        return getCachedMatches();
    }

    async function fetchScoreboardWithCache(matchId) {
        try {
            const resp = await fetch(`/api/matches/${matchId}/scoreboard`);
            if (resp.ok) {
                const data = await resp.json();
                await cacheScoreboard(matchId, data);
                return data;
            }
        } catch {}

        const cached = await getCachedScoreboard(matchId);
        return cached?.data || null;
    }

    return {
        isOnline,
        fetchMatchesWithCache,
        fetchScoreboardWithCache,
    };
}
