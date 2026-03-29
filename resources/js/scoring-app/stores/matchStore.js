import { defineStore } from 'pinia';
import { db } from '../db';
import axios from 'axios';

const SQUAD_LOCK_KEY = 'dc_locked_squad';

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
                this.matches = data.data;
                await db.matches.bulkPut(this.matches);
            } catch (e) {
                const cached = await db.matches.toArray();
                if (cached.length) {
                    this.matches = cached;
                } else {
                    this.error = 'Unable to load matches. Check your connection.';
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
                this.currentMatch = data.data;
                await this.cacheMatch(data.data);
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
            await db.matches.put(match);
        },
    },
});
