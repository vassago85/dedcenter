import { defineStore } from 'pinia';
import { db } from '../db';
import axios from 'axios';

const SQUAD_LOCK_KEY = 'dc_locked_squad';

function plain(obj) {
    return JSON.parse(JSON.stringify(obj));
}

function readSquadLock(matchId) {
    try {
        const raw = localStorage.getItem(SQUAD_LOCK_KEY);
        if (!raw) return null;
        const lock = JSON.parse(raw);
        return lock.matchId === matchId ? lock : null;
    } catch {
        return null;
    }
}

export const useMatchStore = defineStore('match', {
    state: () => ({
        matches: [],
        currentMatch: null,
        loading: false,
        error: null,
        lockedSquadId: null,
        lockedSquadName: null,
        cachedMatchIds: new Set(),
        cachingMatchId: null,
    }),

    getters: {
        targetSets: (state) => state.currentMatch?.target_sets ?? [],
        squads: (state) => state.currentMatch?.squads ?? [],
        allShooters: (state) => {
            if (!state.currentMatch?.squads) return [];
            return state.currentMatch.squads.flatMap((s) =>
                s.shooters.map((sh) => ({ ...sh, squadName: s.name }))
            );
        },
        squadShooters: (state) => {
            if (!state.lockedSquadId || !state.currentMatch?.squads) return [];
            const squad = state.currentMatch.squads.find(s => s.id === state.lockedSquadId);
            if (!squad) return [];
            return squad.shooters.map(sh => ({ ...sh, squadName: squad.name }));
        },
        allGongs: (state) => {
            if (!state.currentMatch?.target_sets) return [];
            return state.currentMatch.target_sets.flatMap((ts) =>
                ts.gongs.map((g) => ({ ...g, targetSetLabel: ts.label, distance: ts.distance_meters }))
            );
        },
        hasSquadLock: (state) => !!state.lockedSquadId,
    },

    actions: {
        async fetchMatches() {
            this.loading = true;
            this.error = null;
            try {
                const { data } = await axios.get('/api/matches');
                const matches = plain(data.data);
                await db.matches.bulkPut(matches);
                this.matches = matches;
            } catch (e) {
                console.error('fetchMatches failed:', e);
                const cached = await db.matches.toArray();
                if (cached.length) {
                    this.matches = cached;
                } else {
                    const status = e.response?.status || 'network';
                    const detail = e.response?.data?.message || e.message || 'Unknown error';
                    this.error = `Unable to load matches (${status}: ${detail})`;
                }
            } finally {
                this.loading = false;
            }
        },

        async fetchMatch(matchId) {
            this.loading = true;
            this.error = null;
            try {
                const { data } = await axios.get(`/api/matches/${matchId}`);
                const match = plain(data.data);
                await db.matches.put(match);
                this.currentMatch = match;
            } catch (e) {
                const cached = await db.matches.get(matchId);
                if (cached) {
                    this.currentMatch = cached;
                } else {
                    this.error = 'Match not available offline.';
                }
            } finally {
                this.loading = false;
            }

            const lock = readSquadLock(matchId);
            if (lock) {
                this.lockedSquadId = lock.squadId;
                this.lockedSquadName = lock.squadName;
            } else {
                this.lockedSquadId = null;
                this.lockedSquadName = null;
            }
        },

        lockSquad(matchId, squadId, squadName) {
            this.lockedSquadId = squadId;
            this.lockedSquadName = squadName;
            localStorage.setItem(SQUAD_LOCK_KEY, JSON.stringify({ matchId, squadId, squadName }));
        },

        unlockSquad() {
            this.lockedSquadId = null;
            this.lockedSquadName = null;
            localStorage.removeItem(SQUAD_LOCK_KEY);
        },

        async cacheMatch(match) {
            await db.matches.put(plain(match));
            this.cachedMatchIds.add(match.id);
        },

        async checkCachedMatches() {
            const cached = await db.matches.toArray();
            this.cachedMatchIds = new Set(
                cached.filter(m => m.squads && m.squads.length > 0).map(m => m.id)
            );
        },

        isMatchCached(matchId) {
            return this.cachedMatchIds.has(matchId);
        },

        async cacheMatchForOffline(matchId) {
            this.cachingMatchId = matchId;
            try {
                const { data } = await axios.get(`/api/matches/${matchId}`);
                const match = plain(data.data);
                await db.matches.put(match);
                this.cachedMatchIds.add(match.id);
                return match;
            } finally {
                this.cachingMatchId = null;
            }
        },

        async clearMatchCache(matchId) {
            await db.matches.delete(matchId);
            this.cachedMatchIds.delete(matchId);
        },
    },
});
