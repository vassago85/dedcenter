import { defineStore } from 'pinia';
import { db } from '../db/index';
import axios from 'axios';

const EMPTY = Object.freeze([]);
const SHOT_INDEX = '[shooterId+elrTargetId+shotNumber]';

// Set when syncShots() is called while a sync is already in flight, so the
// running sync does one more pass instead of dropping the request.
let syncRequested = false;

function generateDeviceId() {
    let id = localStorage.getItem('dc_device_id');
    if (!id) {
        id = 'dev_' + Math.random().toString(36).substring(2, 10) + Date.now().toString(36);
        localStorage.setItem('dc_device_id', id);
    }
    return id;
}

function shotKey(s) {
    return `${s.shooterId}-${s.elrTargetId}-${s.shotNumber}`;
}

function targetKey(shooterId, targetId) {
    return `${shooterId}-${targetId}`;
}

function byShotNumber(a, b) {
    return a.shotNumber - b.shotNumber;
}

// shooter+target → shots sorted by shotNumber, kept in step with `shots`.
function buildTargetIndex(shots) {
    const index = new Map();
    for (const shot of shots.values()) {
        const key = targetKey(shot.shooterId, shot.elrTargetId);
        const list = index.get(key);
        if (list) list.push(shot);
        else index.set(key, [shot]);
    }
    for (const list of index.values()) list.sort(byShotNumber);
    return index;
}

function putShot(store, shot) {
    store.shots.set(shotKey(shot), shot);
    const key = targetKey(shot.shooterId, shot.elrTargetId);
    const list = (store.shotsByTarget.get(key) ?? EMPTY).filter(s => s.shotNumber !== shot.shotNumber);
    list.push(shot);
    list.sort(byShotNumber);
    store.shotsByTarget.set(key, list);
}

function dropShot(store, shooterId, elrTargetId, shotNumber) {
    store.shots.delete(shotKey({ shooterId, elrTargetId, shotNumber }));
    const key = targetKey(shooterId, elrTargetId);
    const list = store.shotsByTarget.get(key);
    if (!list) return;
    const next = list.filter(s => s.shotNumber !== shotNumber);
    if (next.length) store.shotsByTarget.set(key, next);
    else store.shotsByTarget.delete(key);
}

function sameShot(a, b) {
    return a.matchId === b.matchId
        && a.result === b.result
        && (a.impactNumber ?? null) === (b.impactNumber ?? null)
        && a.pointsAwarded === b.pointsAwarded
        && (a.deviceId ?? null) === (b.deviceId ?? null)
        && (a.recordedAt ?? null) === (b.recordedAt ?? null)
        && a.synced === b.synced;
}

function isAuthError(e) {
    return e.response?.status === 401 || e.response?.status === 419;
}

// Native app / LAN hub: local Ktor feed (camelCase, returns all shots).
// Only the standalone (APK) build is served by Ktor; Laravel has no such route.
async function fetchHubShots(matchId) {
    const res = await axios.get('/api/sync/elr-shots', { params: { match_id: matchId } });
    return (res.data?.data ?? []).map((s) => ({
        shooterId: s.shooterId,
        elrTargetId: s.elrTargetId,
        shotNumber: s.shotNumber,
        impactNumber: s.impactNumber ?? null,
        result: s.result,
        pointsAwarded: s.pointsAwarded ?? 0,
        deviceId: s.deviceId ?? null,
        recordedAt: s.recordedAt ?? null,
    }));
}

// Cloud PWA: incremental sync feed (snake_case).
async function fetchCloudShots(matchId) {
    const res = await axios.get(`/api/matches/${matchId}/scores/sync`);
    return (res.data?.elr_shots ?? []).map((s) => ({
        shooterId: s.shooter_id,
        elrTargetId: s.elr_target_id,
        shotNumber: s.shot_number,
        impactNumber: s.impact_number ?? null,
        result: s.result,
        pointsAwarded: s.points_awarded ?? 0,
        deviceId: s.device_id ?? null,
        recordedAt: s.recorded_at ?? null,
    }));
}

// One push of unsynced shots + team-stage entries. Returns false on failure.
async function pushUnsynced(store) {
    try {
        const unsynced = await db.elrShots
            .where('matchId').equals(store.matchId)
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

            await axios.post(`/api/matches/${store.matchId}/elr-shots`, payload);

            await db.elrShots.bulkUpdate(unsynced.map(s => ({ key: s.localId, changes: { synced: true } })));
            for (const s of unsynced) {
                const current = store.shots.get(shotKey(s));
                if (current) putShot(store, { ...current, synced: true });
            }
        }

        await store.syncTeamStageEntries();
        await store.updatePendingCount();
        return true;
    } catch (e) {
        if (isAuthError(e)) {
            store.authExpired = true;
        } else {
            console.error('ELR sync failed:', e);
        }
        return false;
    }
}

export const useElrScoringStore = defineStore('elrScoring', {
    state: () => ({
        matchId: null,
        shots: new Map(),
        shotsByTarget: new Map(),
        teamStageEntries: new Map(), // key: `${teamId}-${elrStageId}`
        syncing: false,
        pendingCount: 0,
        deviceId: generateDeviceId(),
        authExpired: false,
    }),

    getters: {
        getShotsForTarget: (state) => (shooterId, targetId) =>
            state.shotsByTarget.get(targetKey(shooterId, targetId)) ?? EMPTY,

        // Shooters with at least one real (non not_taken) shot recorded.
        shooterIdsWithShots: (state) => {
            const ids = new Set();
            for (const shot of state.shots.values()) {
                if (shot.result && shot.result !== 'not_taken') ids.add(shot.shooterId);
            }
            return ids;
        },

        getTeamStageEntry: (state) => (teamId, elrStageId) =>
            state.teamStageEntries.get(`${teamId}-${elrStageId}`) ?? null,
    },

    actions: {
        async initForMatch(matchId) {
            this.matchId = matchId;
            this.shots = new Map();
            this.shotsByTarget = new Map();
            this.teamStageEntries = new Map();
            this.authExpired = false;

            try {
                const localShots = await db.elrShots.where('matchId').equals(matchId).toArray();
                const shots = new Map();
                for (const s of localShots) shots.set(shotKey(s), s);
                this.shots = shots;
                this.shotsByTarget = buildTargetIndex(shots);
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

            if (window.__DC_STANDALONE === true) {
                try {
                    serverShots = await fetchHubShots(matchId);
                } catch { /* fall through to the cloud feed */ }
            }

            if (serverShots === null) {
                try {
                    serverShots = await fetchCloudShots(matchId);
                } catch (e) {
                    if (isAuthError(e)) {
                        this.authExpired = true;
                    }
                    return; // offline / unsupported — keep local-only view
                }
            }

            const incoming = new Map();
            for (const srv of serverShots) {
                if (srv.shooterId == null || srv.elrTargetId == null || srv.shotNumber == null) continue;
                incoming.set(shotKey(srv), {
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
                });
            }
            if (!incoming.size) return;

            let accepted;
            try {
                accepted = await db.transaction('rw', db.elrShots, async () => {
                    const rows = await db.elrShots
                        .where(SHOT_INDEX)
                        .anyOf([...incoming.values()].map(s => [s.shooterId, s.elrTargetId, s.shotNumber]))
                        .toArray();
                    const rowsByKey = new Map();
                    for (const row of rows) {
                        const key = shotKey(row);
                        const list = rowsByKey.get(key);
                        if (list) list.push(row);
                        else rowsByKey.set(key, [row]);
                    }

                    const out = [];
                    const puts = [];
                    const deletes = [];
                    for (const [key, merged] of incoming) {
                        const existing = rowsByKey.get(key);
                        const local = existing?.[0] ?? this.shots.get(key);
                        // Pending local edit wins over the server copy.
                        if (local && local.synced === false) continue;
                        out.push(merged);
                        if (!existing) {
                            puts.push(merged);
                            continue;
                        }
                        if (existing.length > 1) deletes.push(...existing.slice(1).map(r => r.localId));
                        if (existing.length > 1 || !sameShot(existing[0], merged)) {
                            puts.push({ ...merged, localId: existing[0].localId });
                        }
                    }
                    if (deletes.length) await db.elrShots.bulkDelete(deletes);
                    if (puts.length) await db.elrShots.bulkPut(puts);
                    return out;
                });
            } catch {
                // db not ready — fall back to in-memory
                accepted = [...incoming.values()];
            }

            for (const merged of accepted) {
                const current = this.shots.get(shotKey(merged));
                if (current && (current.synced === false || sameShot(current, merged))) continue;
                putShot(this, merged);
            }
        },

        async recordShot(shot) {
            await this.recordShots([shot]);
        },

        // Batch variant: one Dexie transaction and one pending recount for
        // every shot written by a single tap.
        async recordShots(inputs) {
            const shots = new Map();
            for (const { matchId, shooterId, elrTargetId, shotNumber, result, pointsAwarded, impactNumber = null } of inputs) {
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
                shots.set(shotKey(shot), shot);
            }
            if (!shots.size) return;

            for (const shot of shots.values()) putShot(this, shot);

            try {
                await db.transaction('rw', db.elrShots, async () => {
                    await db.elrShots
                        .where(SHOT_INDEX)
                        .anyOf([...shots.values()].map(s => [s.shooterId, s.elrTargetId, s.shotNumber]))
                        .delete();
                    await db.elrShots.bulkAdd([...shots.values()]);
                });
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
            const existing = this.shots.get(shotKey({ shooterId, elrTargetId, shotNumber }));
            dropShot(this, shooterId, elrTargetId, shotNumber);

            if (existing?.synced) {
                // recordShot replaces the Dexie row in the same transaction.
                await this.recordShot({
                    matchId: this.matchId,
                    shooterId,
                    elrTargetId,
                    shotNumber,
                    result: 'not_taken',
                    pointsAwarded: 0,
                    impactNumber: null,
                });
                return;
            }

            try {
                await db.elrShots.where(SHOT_INDEX).equals([shooterId, elrTargetId, shotNumber]).delete();
            } catch {
                // db not ready
            }

            await this.updatePendingCount();
        },

        // Upsert a team's per-stage lifecycle row (timer + rotation). Merges
        // with any existing entry so partial updates (e.g. just completed_at)
        // don't wipe started_at / first_shooter. `synced` marks entries that
        // came from the server so they are not re-uploaded.
        async saveTeamStageEntry({ matchId, teamId, elrStageId, squadId, firstShooterId, position, startedAt, completedAt, timedOut, overtimeReason, synced = false }) {
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
                synced,
            };

            this.teamStageEntries.set(key, entry);

            try {
                await db.transaction('rw', db.elrTeamStageEntries, async () => {
                    await db.elrTeamStageEntries
                        .where('[teamId+elrStageId]').equals([teamId, elrStageId]).delete();
                    await db.elrTeamStageEntries.add(entry);
                });
            } catch {
                // db not ready
            }

            if (!synced || existing.synced === false) {
                await this.updatePendingCount();
            }
        },

        async refreshShots(matchId) {
            // Pull server-side shots first so a refresh surfaces impacts entered
            // on other devices / the cloud, then rebuild the in-memory map.
            await this.hydrateFromServer(matchId);

            const merged = new Map();
            try {
                const localShots = await db.elrShots.where('matchId').equals(matchId).toArray();
                for (const s of localShots) merged.set(shotKey(s), s);
            } catch { /* table may not exist */ }

            // Only swap the maps when Dexie disagrees with memory, so an idle
            // poll doesn't invalidate every grid cell that reads the index.
            let changed = merged.size !== this.shots.size;
            if (!changed) {
                for (const [key, s] of merged) {
                    const current = this.shots.get(key);
                    if (!current || !sameShot(current, s)) {
                        changed = true;
                        break;
                    }
                }
            }
            if (changed) {
                this.shots = merged;
                this.shotsByTarget = buildTargetIndex(merged);
            }

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
                const [pendingShots, pendingEntries] = await Promise.all([
                    db.elrShots
                        .where('matchId').equals(this.matchId)
                        .filter(s => !s.synced)
                        .count(),
                    db.elrTeamStageEntries
                        .where('matchId').equals(this.matchId)
                        .filter(e => !e.synced)
                        .count()
                        .catch(() => 0), // table may not exist
                ]);
                this.pendingCount = pendingShots + pendingEntries;
            } catch {
                this.pendingCount = 0;
            }
        },

        async syncShots() {
            if (!navigator.onLine) return;
            if (this.syncing) {
                syncRequested = true;
                return;
            }
            this.syncing = true;

            try {
                let ok;
                do {
                    syncRequested = false;
                    ok = await pushUnsynced(this);
                } while (ok && syncRequested && navigator.onLine);
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
                    if (isAuthError(err)) {
                        this.authExpired = true;
                        return;
                    }
                    // leave unsynced; will retry on next sync
                }
            }
        },
    },
});
