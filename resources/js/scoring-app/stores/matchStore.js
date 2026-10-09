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
        canManage: (state) => state.currentMatch?.can_manage ?? false,
        // Wider than canManage: RO + MD + creator + owner. Older cached
        // payloads omit the flag, so fall back to canManage. The API enforces this.
        canManageSquadding: (state) => {
            const match = state.currentMatch;
            if (!match) return false;
            if (typeof match.can_manage_squadding === 'boolean') return match.can_manage_squadding;
            return !!match.can_manage;
        },
        hasSquadLock: (state) => !!state.lockedSquadId,
        hasStageLock: (state) => !!state.lockedStageId,
        hasAnyLock: (state) => !!state.lockedSquadId || !!state.lockedStageId,

        completionMatrix: (state) => {
            if (!state.currentMatch) return {};
            const squads = state.currentMatch.squads || [];
            const targetSets = state.currentMatch.target_sets || [];

            const gongToSet = new Map();
            for (const ts of targetSets) {
                for (const g of ts.gongs) gongToSet.set(g.id, ts.id);
            }

            const activeShooterToSquad = new Map();
            const activeCounts = new Map();
            const counts = new Map();
            for (const squad of squads) {
                let active = 0;
                for (const sh of squad.shooters) {
                    if (sh.status !== 'active') continue;
                    activeShooterToSquad.set(sh.id, squad.id);
                    active++;
                }
                activeCounts.set(squad.id, active);
                counts.set(squad.id, new Map());
            }

            for (const score of (state.currentMatch.scores || [])) {
                const squadId = activeShooterToSquad.get(score.shooter_id);
                if (squadId === undefined) continue;
                const tsId = gongToSet.get(score.gong_id);
                if (tsId === undefined) continue;
                const squadCounts = counts.get(squadId);
                squadCounts.set(tsId, (squadCounts.get(tsId) ?? 0) + 1);
            }

            const matrix = {};
            for (const squad of squads) {
                matrix[squad.id] = {};
                const active = activeCounts.get(squad.id);
                const squadCounts = counts.get(squad.id);
                for (const ts of targetSets) {
                    const expected = active * ts.gongs.length;
                    const actual = squadCounts.get(ts.id) ?? 0;
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

        async fetchMatch(matchId, { silent = false } = {}) {
            // `silent` skips the loading flag so callers that already have the
            // match in cache (e.g. coming back from a sibling route) don't
            // flash a spinner while we revalidate in the background. The
            // network refresh still happens — we just don't tear down the UI
            // for it.
            if (!silent) this.loading = true;
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
                } else if (!silent) {
                    this.error = 'Match not available offline.';
                }
            } finally {
                if (!silent) this.loading = false;
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

        clearAllLocks() {
            this.unlockSquad();
            this.unlockStage();
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

        // Patch the in-memory match so scoring stays on screen. A full refetch
        // flips ScoringMatrix / ScoringFlow back to a loading state.
        async addShooter(matchId, payload) {
            const { data } = await axios.post(`/api/matches/${matchId}/shooters`, payload);
            const created = data.shooter;
            if (this.currentMatch && created) {
                const squad = this.currentMatch.squads.find(s => s.id === created.squad_id);
                if (squad) {
                    squad.shooters.push({
                        id: created.id,
                        name: created.name,
                        bib_number: created.bib_number,
                        sort_order: created.sort_order,
                        division_id: created.division_id ?? null,
                        division: created.division ?? null,
                        team_id: null,
                        team: null,
                        category_ids: [],
                        status: created.status ?? 'active',
                        alrha_class: null,
                        is_coached: false,
                        gong_position: null,
                        shared_rifle_key: null,
                    });
                }
            }
            return created;
        },

        async moveShooterToSquad(matchId, shooterId, targetSquadId) {
            const { data } = await axios.patch(
                `/api/matches/${matchId}/shooters/${shooterId}/squad`,
                { squad_id: targetSquadId },
            );
            const updated = data.shooter;
            if (this.currentMatch && updated) {
                let moving = null;
                for (const squad of this.currentMatch.squads) {
                    const idx = squad.shooters.findIndex(s => s.id === shooterId);
                    if (idx >= 0) {
                        moving = squad.shooters.splice(idx, 1)[0];
                        break;
                    }
                }
                if (moving) {
                    moving.sort_order = updated.sort_order;
                    const target = this.currentMatch.squads.find(s => s.id === updated.squad_id);
                    if (target) target.shooters.push(moving);
                }
            }
            return updated;
        },

        async reorderShooter(matchId, shooterId, direction) {
            const { data } = await axios.patch(
                `/api/matches/${matchId}/shooters/${shooterId}/order`,
                { direction },
            );
            const updated = data.shooter;
            if (this.currentMatch && updated) {
                // API returns only the moved shooter. Swap sort_order with
                // whoever currently holds the slot they just took.
                const squad = this.currentMatch.squads.find(s => s.id === updated.squad_id);
                if (squad) {
                    const self = squad.shooters.find(s => s.id === updated.id);
                    if (self) {
                        const oldSort = self.sort_order;
                        const neighbour = squad.shooters.find(
                            s => s.id !== updated.id && s.sort_order === updated.sort_order,
                        );
                        if (neighbour) {
                            neighbour.sort_order = oldSort;
                        }
                        self.sort_order = updated.sort_order;
                    }
                }
            }
            return updated;
        },

        async issueDisqualification(matchId, shooterId, reason, targetSetId = null) {
            const { data } = await axios.post(`/api/matches/${matchId}/disqualifications`, {
                shooter_id: shooterId,
                target_set_id: targetSetId,
                reason,
            });

            if (!targetSetId && this.currentMatch) {
                for (const squad of this.currentMatch.squads) {
                    const shooter = squad.shooters.find(s => s.id === shooterId);
                    if (shooter) {
                        shooter.status = 'dq';
                        break;
                    }
                }
            }

            if (this.currentMatch) {
                if (!this.currentMatch.disqualifications) {
                    this.currentMatch.disqualifications = [];
                }
                this.currentMatch.disqualifications.push(data.disqualification);
            }

            return data;
        },

        async completeMatch(matchId, dryRun = false) {
            const { data } = await axios.post(`/api/matches/${matchId}/complete`, { dry_run: dryRun });
            return data;
        },

        async reopenMatch(matchId) {
            const { data } = await axios.post(`/api/matches/${matchId}/reopen`);
            if (this.currentMatch?.id === matchId) {
                this.currentMatch.status = 'active';
            }
            const listEntry = this.matches.find((m) => m.id === matchId);
            if (listEntry) {
                listEntry.status = 'active';
            }
            return data;
        },
    },
});
