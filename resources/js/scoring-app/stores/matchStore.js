import { defineStore } from 'pinia';
import { db } from '../db';
import axios from 'axios';

const SQUAD_LOCK_KEY = 'dc_locked_squad';
const STAGE_LOCK_KEY = 'dc_locked_stage';

function plain(obj) {
    return JSON.parse(JSON.stringify(obj));
}

function readLock(key, matchId) {
    try {
        const raw = localStorage.getItem(key);
        if (!raw) return null;
        const lock = JSON.parse(raw);
        return lock.matchId === matchId ? lock : null;
    } catch {
        return null;
    }
}

function readSquadLock(matchId) {
    return readLock(SQUAD_LOCK_KEY, matchId);
}

function readStageLock(matchId) {
    return readLock(STAGE_LOCK_KEY, matchId);
}

function readPin(matchId) {
    try {
        const raw = localStorage.getItem(`dc_lock_pin_${matchId}`);
        return raw ?? null;
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
        lockedStageId: null,
        lockedStageName: null,
        hasPin: false,
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
        hasStageLock: (state) => !!state.lockedStageId,
        hasAnyLock: (state) => !!state.lockedSquadId || !!state.lockedStageId,

        completionMatrix: (state) => {
            if (!state.currentMatch) return {};
            const scores = state.currentMatch.scores || [];
            const matrix = {};

            for (const squad of (state.currentMatch.squads || [])) {
                matrix[squad.id] = {};
                const activeShooters = squad.shooters.filter(s => s.status === 'active');

                for (const ts of (state.currentMatch.target_sets || [])) {
                    const gongIds = new Set(ts.gongs.map(g => g.id));
                    const shooterIds = new Set(activeShooters.map(s => s.id));
                    const expected = activeShooters.length * ts.gongs.length;

                    let actual = 0;
                    for (const score of scores) {
                        if (shooterIds.has(score.shooter_id) && gongIds.has(score.gong_id)) {
                            actual++;
                        }
                    }

                    matrix[squad.id][ts.id] = {
                        expected,
                        actual,
                        status: actual === 0 ? 'pending' : (actual >= expected ? 'scored' : 'in-progress'),
                    };
                }
            }
            return matrix;
        },
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

            const squadLock = readSquadLock(matchId);
            if (squadLock) {
                this.lockedSquadId = squadLock.squadId;
                this.lockedSquadName = squadLock.squadName;
            } else {
                this.lockedSquadId = null;
                this.lockedSquadName = null;
            }

            const stageLock = readStageLock(matchId);
            if (stageLock) {
                this.lockedStageId = stageLock.stageId;
                this.lockedStageName = stageLock.stageName;
            } else {
                this.lockedStageId = null;
                this.lockedStageName = null;
            }

            this.hasPin = !!readPin(matchId);
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

        lockStage(matchId, stageId, stageName) {
            this.lockedStageId = stageId;
            this.lockedStageName = stageName;
            localStorage.setItem(STAGE_LOCK_KEY, JSON.stringify({ matchId, stageId, stageName }));
        },

        unlockStage() {
            this.lockedStageId = null;
            this.lockedStageName = null;
            localStorage.removeItem(STAGE_LOCK_KEY);
        },

        setPin(matchId, pin) {
            localStorage.setItem(`dc_lock_pin_${matchId}`, pin);
            this.hasPin = true;
        },

        verifyPin(matchId, pin) {
            const stored = readPin(matchId);
            return stored === pin;
        },

        clearPin(matchId) {
            localStorage.removeItem(`dc_lock_pin_${matchId}`);
            this.hasPin = false;
        },

        clearAllLocks(matchId) {
            this.unlockSquad();
            this.unlockStage();
            this.clearPin(matchId);
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

        async updateShooterStatus(matchId, shooterId, status) {
            await axios.patch(`/api/matches/${matchId}/shooters/${shooterId}/status`, { status });
            if (this.currentMatch) {
                for (const squad of this.currentMatch.squads) {
                    const shooter = squad.shooters.find(s => s.id === shooterId);
                    if (shooter) {
                        shooter.status = status;
                        break;
                    }
                }
            }
        },
    },
});
