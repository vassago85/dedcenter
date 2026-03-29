import { defineStore } from 'pinia';
import { db } from '../db';
import axios from 'axios';

function generateDeviceId() {
    let id = localStorage.getItem('dc_device_id');
    if (!id) {
        id = 'dev_' + Math.random().toString(36).substring(2, 10) + Date.now().toString(36);
        localStorage.setItem('dc_device_id', id);
    }
    return id;
}

export const useScoringStore = defineStore('scoring', {
    state: () => ({
        matchId: null,
        scores: new Map(),
        stageTimes: new Map(),
        deletedScores: [],
        currentTargetSetIndex: 0,
        currentGongIndex: 0,
        currentShooterIndex: 0,
        syncing: false,
        pendingCount: 0,
        deviceId: generateDeviceId(),
        authExpired: false,
    }),

    getters: {
        scoreKey: () => (shooterId, gongId) => `${shooterId}-${gongId}`,
        stageTimeKey: () => (shooterId, targetSetId) => `${shooterId}-${targetSetId}`,
        getScore: (state) => (shooterId, gongId) => {
            return state.scores.get(`${shooterId}-${gongId}`) ?? null;
        },
        getStageTime: (state) => (shooterId, targetSetId) => {
            return state.stageTimes.get(`${shooterId}-${targetSetId}`) ?? null;
        },
    },

    actions: {
        async initForMatch(matchId, existingScores = [], existingStageTimes = []) {
            this.matchId = matchId;
            this.scores = new Map();
            this.stageTimes = new Map();
            this.deletedScores = [];
            this.currentTargetSetIndex = 0;
            this.currentGongIndex = 0;
            this.currentShooterIndex = 0;
            this.authExpired = false;

            for (const s of existingScores) {
                this.scores.set(`${s.shooter_id}-${s.gong_id}`, {
                    shooterId: s.shooter_id,
                    gongId: s.gong_id,
                    isHit: s.is_hit,
                    recordedAt: s.recorded_at,
                    synced: true,
                });
            }

            for (const st of existingStageTimes) {
                this.stageTimes.set(`${st.shooter_id}-${st.target_set_id}`, {
                    shooterId: st.shooter_id,
                    targetSetId: st.target_set_id,
                    timeSeconds: st.time_seconds,
                    recordedAt: st.recorded_at,
                    synced: true,
                });
            }

            const localScores = await db.scores.where('matchId').equals(matchId).toArray();
            for (const s of localScores) {
                const key = `${s.shooterId}-${s.gongId}`;
                if (!s.synced || !this.scores.has(key)) {
                    this.scores.set(key, s);
                }
            }

            const localTimes = await db.stageTimes.where('matchId').equals(matchId).toArray();
            for (const st of localTimes) {
                const key = `${st.shooterId}-${st.targetSetId}`;
                if (!st.synced || !this.stageTimes.has(key)) {
                    this.stageTimes.set(key, st);
                }
            }

            await this.updatePendingCount();
        },

        async recordScore(shooterId, gongId, isHit) {
            const key = `${shooterId}-${gongId}`;
            const score = {
                shooterId,
                gongId,
                matchId: this.matchId,
                isHit,
                deviceId: this.deviceId,
                recordedAt: new Date().toISOString(),
                synced: false,
            };

            this.scores.set(key, score);

            await db.scores.where('[shooterId+gongId]').equals([shooterId, gongId]).delete();
            await db.scores.add(score);
            await this.updatePendingCount();
        },

        async removeScore(shooterId, gongId) {
            const key = `${shooterId}-${gongId}`;
            const existing = this.scores.get(key);

            this.scores.delete(key);
            await db.scores.where('[shooterId+gongId]').equals([shooterId, gongId]).delete();

            if (existing?.synced) {
                this.deletedScores.push({ shooterId, gongId });
            }

            await this.updatePendingCount();
        },

        async recordStageTime(shooterId, targetSetId, timeSeconds) {
            const key = `${shooterId}-${targetSetId}`;
            const stageTime = {
                shooterId,
                targetSetId,
                matchId: this.matchId,
                timeSeconds,
                deviceId: this.deviceId,
                recordedAt: new Date().toISOString(),
                synced: false,
            };

            this.stageTimes.set(key, stageTime);

            await db.stageTimes.where('[shooterId+targetSetId]').equals([shooterId, targetSetId]).delete();
            await db.stageTimes.add(stageTime);
            await this.updatePendingCount();
        },

        async updatePendingCount() {
            const pendingScores = await db.scores
                .where('matchId').equals(this.matchId)
                .filter((s) => !s.synced)
                .count();
            const pendingTimes = await db.stageTimes
                .where('matchId').equals(this.matchId)
                .filter((s) => !s.synced)
                .count();
            this.pendingCount = pendingScores + pendingTimes + this.deletedScores.length;
        },

        async syncScores() {
            if (this.syncing || !navigator.onLine) return;

            this.syncing = true;
            try {
                const unsyncedScores = await db.scores
                    .where('matchId').equals(this.matchId)
                    .filter((s) => !s.synced)
                    .toArray();

                const unsyncedTimes = await db.stageTimes
                    .where('matchId').equals(this.matchId)
                    .filter((s) => !s.synced)
                    .toArray();

                const pendingDeletions = [...this.deletedScores];

                if (!unsyncedScores.length && !unsyncedTimes.length && !pendingDeletions.length) {
                    this.syncing = false;
                    return;
                }

                const payload = {};

                if (unsyncedScores.length) {
                    payload.scores = unsyncedScores.map((s) => ({
                        shooter_id: s.shooterId,
                        gong_id: s.gongId,
                        is_hit: s.isHit,
                        device_id: s.deviceId,
                        recorded_at: s.recordedAt,
                    }));
                }

                if (pendingDeletions.length) {
                    payload.deleted_scores = pendingDeletions.map((d) => ({
                        shooter_id: d.shooterId,
                        gong_id: d.gongId,
                    }));
                }

                if (unsyncedTimes.length) {
                    payload.stage_times = unsyncedTimes.map((st) => ({
                        shooter_id: st.shooterId,
                        target_set_id: st.targetSetId,
                        time_seconds: st.timeSeconds,
                        device_id: st.deviceId,
                        recorded_at: st.recordedAt,
                    }));
                }

                await axios.post(`/api/matches/${this.matchId}/scores`, payload);

                this.deletedScores = [];

                for (const s of unsyncedScores) {
                    await db.scores.update(s.localId, { synced: true });
                    const key = `${s.shooterId}-${s.gongId}`;
                    const current = this.scores.get(key);
                    if (current) {
                        this.scores.set(key, { ...current, synced: true });
                    }
                }

                for (const st of unsyncedTimes) {
                    await db.stageTimes.update(st.localId, { synced: true });
                    const key = `${st.shooterId}-${st.targetSetId}`;
                    const current = this.stageTimes.get(key);
                    if (current) {
                        this.stageTimes.set(key, { ...current, synced: true });
                    }
                }

                await this.updatePendingCount();
            } catch (e) {
                if (e.response?.status === 401 || e.response?.status === 419) {
                    this.authExpired = true;
                } else if (e.response?.status === 403) {
                    console.error('Sync failed: not authorized to score this match.');
                } else {
                    console.error('Sync failed:', e);
                }
            } finally {
                this.syncing = false;
            }
        },

        // Standard scoring navigation
        advanceToNextShooter(totalShooters) {
            if (this.currentShooterIndex < totalShooters - 1) {
                this.currentShooterIndex++;
                return true;
            }
            return false;
        },

        advanceToNextGong(totalGongs, totalShooters) {
            this.currentShooterIndex = 0;
            if (this.currentGongIndex < totalGongs - 1) {
                this.currentGongIndex++;
                return true;
            }
            return false;
        },

        advanceToNextTargetSet(totalSets) {
            this.currentShooterIndex = 0;
            this.currentGongIndex = 0;
            if (this.currentTargetSetIndex < totalSets - 1) {
                this.currentTargetSetIndex++;
                return true;
            }
            return false;
        },

        // PRS navigation: Stage -> Shooter -> (all gongs at once)
        prsAdvanceToNextShooter(totalShooters) {
            if (this.currentShooterIndex < totalShooters - 1) {
                this.currentShooterIndex++;
                return true;
            }
            return false;
        },

        prsAdvanceToNextStage(totalStages) {
            this.currentShooterIndex = 0;
            if (this.currentTargetSetIndex < totalStages - 1) {
                this.currentTargetSetIndex++;
                return true;
            }
            return false;
        },
    },
});
