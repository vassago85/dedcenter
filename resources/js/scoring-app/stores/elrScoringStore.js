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
    },

    actions: {
        async initForMatch(matchId) {
            this.matchId = matchId;
            this.shots = new Map();
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

            await this.updatePendingCount();
        },

        async recordShot({ matchId, shooterId, elrTargetId, shotNumber, result, pointsAwarded }) {
            const key = `${shooterId}-${elrTargetId}-${shotNumber}`;
            const shot = {
                shooterId,
                elrTargetId,
                matchId: matchId || this.matchId,
                shotNumber,
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

        async updatePendingCount() {
            try {
                const pending = await db.elrShots
                    .where('matchId').equals(this.matchId)
                    .filter(s => !s.synced)
                    .count();
                this.pendingCount = pending;
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

                if (!unsynced.length) { this.syncing = false; return; }

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
    },
});
