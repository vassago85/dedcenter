import { defineStore } from 'pinia';
import { db } from '../db/index';
import axios from 'axios';

function generateDeviceId() {
    let id = localStorage.getItem('dc_device_id');
    if (!id) {
        id = 'dev_' + Math.random().toString(36).substring(2, 10) + Date.now().toString(36);
        localStorage.setItem('dc_device_id', id);
    }
    return id;
}

export const useElrScoringStore = defineStore('elrScoring', {
    state: () => ({
        matchId: null,
        shots: new Map(),
        teamStageEntries: new Map(), // key: `${teamId}-${elrStageId}`
        syncing: false,
        pendingCount: 0,
        deviceId: generateDeviceId(),
        authExpired: false,
    }),

    getters: {
        shotKey: () => (shooterId, targetId, shotNumber) => `${shooterId}-${targetId}-${shotNumber}`,

        getShotsForTarget: (state) => (shooterId, targetId) => {
            const result = [];
            for (const [key, shot] of state.shots) {
                if (shot.shooterId === shooterId && shot.elrTargetId === targetId) {
                    result.push(shot);
                }
            }
            return result.sort((a, b) => a.shotNumber - b.shotNumber);
        },

        teamStageKey: () => (teamId, elrStageId) => `${teamId}-${elrStageId}`,

        getTeamStageEntry: (state) => (teamId, elrStageId) =>
            state.teamStageEntries.get(`${teamId}-${elrStageId}`) ?? null,
    },

    actions: {
        async initForMatch(matchId) {
            this.matchId = matchId;
            this.shots = new Map();
            this.teamStageEntries = new Map();
            this.authExpired = false;

            try {
                const localShots = await db.elrShots.where('matchId').equals(matchId).toArray();
                for (const s of localShots) {
                    const key = `${s.shooterId}-${s.elrTargetId}-${s.shotNumber}`;
                    this.shots.set(key, s);
                }
            } catch {
                // elrShots table may not exist yet
            }

            try {
                const localEntries = await db.elrTeamStageEntries.where('matchId').equals(matchId).toArray();
                for (const e of localEntries) {
                    this.teamStageEntries.set(`${e.teamId}-${e.elrStageId}`, e);
                }
            } catch {
                // table may not exist yet
            }

            // Pull any shots already recorded on the server / other devices into
            // the local store so the impact grid reflects them — otherwise a
            // device that imported a match with existing scores shows an empty
            // scoresheet and the RO re-enters (double-scores) them.
            await this.hydrateFromServer(matchId);

            await this.updatePendingCount();
        },

        // Fetch existing ELR shots from whichever server backs this app (the
        // native local hub server, or the cloud) and merge them into Dexie.
        // Local UNSYNCED shots always win — we never clobber a pending edit.
        // Keyed by shooter+target+shotNumber so this can only upsert, never
        // duplicate.
        async hydrateFromServer(matchId) {
            let serverShots = null;

            // Native app / LAN hub: local Ktor feed (camelCase, returns all shots).
            try {
                const res = await axios.get('/api/sync/elr-shots', { params: { match_id: matchId } });
                serverShots = (res.data?.data ?? []).map((s) => ({
                    shooterId: s.shooterId,
                    elrTargetId: s.elrTargetId,
                    shotNumber: s.shotNumber,
                    impactNumber: s.impactNumber ?? null,
                    result: s.result,
                    pointsAwarded: s.pointsAwarded ?? 0,
                    deviceId: s.deviceId ?? null,
                    recordedAt: s.recordedAt ?? null,
                }));
            } catch {
                // Cloud PWA: incremental sync feed (snake_case).
                try {
                    const res = await axios.get(`/api/matches/${matchId}/scores/sync`);
                    serverShots = (res.data?.elr_shots ?? []).map((s) => ({
                        shooterId: s.shooter_id,
                        elrTargetId: s.elr_target_id,
                        shotNumber: s.shot_number,
                        impactNumber: s.impact_number ?? null,
                        result: s.result,
                        pointsAwarded: s.points_awarded ?? 0,
                        deviceId: s.device_id ?? null,
                        recordedAt: s.recorded_at ?? null,
                    }));
                } catch (e) {
                    if (e.response?.status === 401 || e.response?.status === 419) {
                        this.authExpired = true;
                    }
                    return; // offline / unsupported — keep local-only view
                }
            }

            for (const srv of serverShots) {
                if (srv.shooterId == null || srv.elrTargetId == null || srv.shotNumber == null) continue;
                const key = `${srv.shooterId}-${srv.elrTargetId}-${srv.shotNumber}`;

                let local = this.shots.get(key);
                try {
                    local = await db.elrShots
                        .where('[shooterId+elrTargetId+shotNumber]')
                        .equals([srv.shooterId, srv.elrTargetId, srv.shotNumber])
                        .first() ?? local;
                } catch { /* db not ready — fall back to in-memory */ }

                // Pending local edit wins over the server copy.
                if (local && local.synced === false) continue;

                const merged = {
                    shooterId: srv.shooterId,
                    elrTargetId: srv.elrTargetId,
                    matchId,
                    shotNumber: srv.shotNumber,
                    impactNumber: srv.impactNumber,
                    result: srv.result,
                    pointsAwarded: srv.pointsAwarded,
                    deviceId: srv.deviceId,
                    recordedAt: srv.recordedAt,
                    synced: true,
                };

                try {
                    await db.elrShots
                        .where('[shooterId+elrTargetId+shotNumber]')
                        .equals([srv.shooterId, srv.elrTargetId, srv.shotNumber])
                        .delete();
                    await db.elrShots.add(merged);
                } catch { /* db not ready */ }

                this.shots.set(key, merged);
            }
        },

        async recordShot({ matchId, shooterId, elrTargetId, shotNumber, result, pointsAwarded, impactNumber = null }) {
            const key = `${shooterId}-${elrTargetId}-${shotNumber}`;
            const shot = {
                shooterId,
                elrTargetId,
                matchId: matchId || this.matchId,
                shotNumber,
                impactNumber,
                result,
                pointsAwarded,
                deviceId: this.deviceId,
                recordedAt: new Date().toISOString(),
                synced: false,
            };

            this.shots.set(key, shot);

            try {
                await db.elrShots.where('[shooterId+elrTargetId+shotNumber]').equals([shooterId, elrTargetId, shotNumber]).delete();
                await db.elrShots.add(shot);
            } catch {
                // db not ready
            }

            await this.updatePendingCount();
        },

        // Undo a shot. Unsynced shots are deleted outright (never sent); a
        // shot already synced to the server is neutralized with a not_taken
        // overwrite (0 points, no impact) so the server can't keep stale
        // points, while the UI treats the slot as open for re-entry.
        async removeShot({ shooterId, elrTargetId, shotNumber }) {
            const key = `${shooterId}-${elrTargetId}-${shotNumber}`;
            const existing = this.shots.get(key);
            this.shots.delete(key);

            try {
                await db.elrShots.where('[shooterId+elrTargetId+shotNumber]').equals([shooterId, elrTargetId, shotNumber]).delete();
            } catch {
                // db not ready
            }

            if (existing?.synced) {
                await this.recordShot({
                    matchId: this.matchId,
                    shooterId,
                    elrTargetId,
                    shotNumber,
                    result: 'not_taken',
                    pointsAwarded: 0,
                    impactNumber: null,
                });
            }

            await this.updatePendingCount();
        },

        // Upsert a team's per-stage lifecycle row (timer + rotation). Merges
        // with any existing entry so partial updates (e.g. just completed_at)
        // don't wipe started_at / first_shooter.
        async saveTeamStageEntry({ matchId, teamId, elrStageId, squadId, firstShooterId, position, startedAt, completedAt, timedOut, overtimeReason }) {
            const key = `${teamId}-${elrStageId}`;
            const existing = this.teamStageEntries.get(key) ?? {};
            const entry = {
                ...existing,
                matchId: matchId || this.matchId,
                teamId,
                elrStageId,
                squadId: squadId !== undefined ? squadId : existing.squadId ?? null,
                firstShooterId: firstShooterId !== undefined ? firstShooterId : existing.firstShooterId ?? null,
                position: position !== undefined ? position : existing.position ?? null,
                startedAt: startedAt !== undefined ? startedAt : existing.startedAt ?? null,
                completedAt: completedAt !== undefined ? completedAt : existing.completedAt ?? null,
                timedOut: timedOut !== undefined ? timedOut : existing.timedOut ?? false,
                // MD-captured note for shooting past the team time limit.
                // Persisted on the entry so a tablet that takes a team into
                // overtime carries the explanation to the server + other
                // devices on the next sync.
                overtimeReason: overtimeReason !== undefined ? overtimeReason : existing.overtimeReason ?? null,
                deviceId: this.deviceId,
                synced: false,
            };

            this.teamStageEntries.set(key, entry);

            try {
                await db.elrTeamStageEntries
                    .where('[teamId+elrStageId]').equals([teamId, elrStageId]).delete();
                await db.elrTeamStageEntries.add(entry);
            } catch {
                // db not ready
            }

            await this.updatePendingCount();
        },

        async refreshShots(matchId) {
            // Pull server-side shots first so a refresh surfaces impacts entered
            // on other devices / the cloud, then rebuild the in-memory map.
            await this.hydrateFromServer(matchId);

            const merged = new Map();
            try {
                const localShots = await db.elrShots.where('matchId').equals(matchId).toArray();
                for (const s of localShots) {
                    const key = `${s.shooterId}-${s.elrTargetId}-${s.shotNumber}`;
                    merged.set(key, s);
                }
            } catch { /* table may not exist */ }
            this.shots = merged;

            const mergedEntries = new Map();
            try {
                const localEntries = await db.elrTeamStageEntries.where('matchId').equals(matchId).toArray();
                for (const e of localEntries) {
                    mergedEntries.set(`${e.teamId}-${e.elrStageId}`, e);
                }
            } catch { /* table may not exist */ }
            this.teamStageEntries = mergedEntries;

            await this.updatePendingCount();
        },

        async updatePendingCount() {
            try {
                const pendingShots = await db.elrShots
                    .where('matchId').equals(this.matchId)
                    .filter(s => !s.synced)
                    .count();
                let pendingEntries = 0;
                try {
                    pendingEntries = await db.elrTeamStageEntries
                        .where('matchId').equals(this.matchId)
                        .filter(e => !e.synced)
                        .count();
                } catch { /* table may not exist */ }
                this.pendingCount = pendingShots + pendingEntries;
            } catch {
                this.pendingCount = 0;
            }
        },

        async syncShots() {
            if (this.syncing || !navigator.onLine) return;
            this.syncing = true;

            try {
                const unsynced = await db.elrShots
                    .where('matchId').equals(this.matchId)
                    .filter(s => !s.synced)
                    .toArray();

                if (unsynced.length) {
                    const payload = {
                        shots: unsynced.map(s => ({
                            shooter_id: s.shooterId,
                            elr_target_id: s.elrTargetId,
                            shot_number: s.shotNumber,
                            result: s.result,
                            device_id: s.deviceId,
                            recorded_at: s.recordedAt,
                        })),
                    };

                    await axios.post(`/api/matches/${this.matchId}/elr-shots`, payload);

                    for (const s of unsynced) {
                        await db.elrShots.update(s.localId, { synced: true });
                        const key = `${s.shooterId}-${s.elrTargetId}-${s.shotNumber}`;
                        const current = this.shots.get(key);
                        if (current) {
                            this.shots.set(key, { ...current, synced: true });
                        }
                    }
                }

                await this.syncTeamStageEntries();
                await this.updatePendingCount();
            } catch (e) {
                if (e.response?.status === 401 || e.response?.status === 419) {
                    this.authExpired = true;
                } else {
                    console.error('ELR sync failed:', e);
                }
            } finally {
                this.syncing = false;
            }
        },

        async syncTeamStageEntries() {
            let unsynced = [];
            try {
                unsynced = await db.elrTeamStageEntries
                    .where('matchId').equals(this.matchId)
                    .filter(e => !e.synced)
                    .toArray();
            } catch {
                return; // table may not exist
            }

            for (const e of unsynced) {
                try {
                    await axios.post(`/api/matches/${this.matchId}/elr-team-stage`, {
                        team_id: e.teamId,
                        elr_stage_id: e.elrStageId,
                        squad_id: e.squadId ?? null,
                        first_shooter_id: e.firstShooterId ?? null,
                        position: e.position ?? null,
                        started_at: e.startedAt ?? null,
                        completed_at: e.completedAt ?? null,
                        timed_out: !!e.timedOut,
                        overtime_reason: e.overtimeReason ?? null,
                        device_id: e.deviceId,
                    });

                    await db.elrTeamStageEntries.update(e.localId, { synced: true });
                    const key = `${e.teamId}-${e.elrStageId}`;
                    const current = this.teamStageEntries.get(key);
                    if (current) {
                        this.teamStageEntries.set(key, { ...current, synced: true });
                    }
                } catch (err) {
                    if (err.response?.status === 401 || err.response?.status === 419) {
                        this.authExpired = true;
                        return;
                    }
                    // leave unsynced; will retry on next sync
                }
            }
        },
    },
});
