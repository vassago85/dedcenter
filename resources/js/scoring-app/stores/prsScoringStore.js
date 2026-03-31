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

export const usePrsScoringStore = defineStore('prsScoring', {
    state: () => ({
        matchId: null,
        currentScreen: 'match-home',
        selectedSquadId: null,
        selectedStageId: null,
        selectedShooterId: null,
        shots: [],
        currentShotIndex: 0,
        rawTimeSeconds: null,
        isTimerRunning: false,
        timerStartedAt: null,
        timerElapsed: 0,
        timerMode: 'manual',
        rawDigits: '',
        stageCompletions: new Map(),
        syncing: false,
        pendingCount: 0,
        authExpired: false,
        deviceId: generateDeviceId(),
    }),

    getters: {
        hits: (state) => state.shots.filter(s => s.result === 'hit').length,
        misses: (state) => state.shots.filter(s => s.result === 'miss').length,
        notTaken: (state) => state.shots.filter(s => s.result === 'not_taken').length,
        currentShotNumber: (state) => state.currentShotIndex + 1,
        allShotsScored: (state) => state.shots.every(s => s.result !== 'not_taken'),
        isComplete: (state) => (shooterId, stageId) => {
            return state.stageCompletions.has(`${shooterId}-${stageId}`);
        },
        shooterStatus: (state) => (shooterId, stageId) => {
            const key = `${shooterId}-${stageId}`;
            if (state.stageCompletions.has(key)) return 'COMPLETED';
            return 'NOT_STARTED';
        },
        completionData: (state) => (shooterId, stageId) => {
            return state.stageCompletions.get(`${shooterId}-${stageId}`) ?? null;
        },
        effectiveTime: (state) => {
            if (state.timerMode === 'manual') {
                return state.rawTimeSeconds ?? 0;
            }
            return state.rawTimeSeconds !== null ? state.rawTimeSeconds : state.timerElapsed;
        },
    },

    actions: {
        async initForMatch(matchId, prsStageResults = []) {
            this.matchId = matchId;
            this.currentScreen = 'match-home';
            this.selectedSquadId = null;
            this.selectedStageId = null;
            this.selectedShooterId = null;
            this.shots = [];
            this.currentShotIndex = 0;
            this.rawTimeSeconds = null;
            this.isTimerRunning = false;
            this.timerStartedAt = null;
            this.timerElapsed = 0;
            this.timerMode = 'manual';
            this.rawDigits = '';
            this.stageCompletions = new Map();
            this.authExpired = false;

            for (const r of prsStageResults) {
                const key = `${r.shooter_id}-${r.stage_id}`;
                this.stageCompletions.set(key, {
                    shooterId: r.shooter_id,
                    stageId: r.stage_id,
                    hits: r.hits,
                    misses: r.misses,
                    notTaken: r.not_taken,
                    time: r.official_time_seconds,
                    completedAt: r.completed_at,
                });
            }

            const localResults = await db.prsStageResults.where('matchId').equals(matchId).toArray();
            for (const r of localResults) {
                const key = `${r.shooterId}-${r.stageId}`;
                if (!r.synced || !this.stageCompletions.has(key)) {
                    this.stageCompletions.set(key, {
                        shooterId: r.shooterId,
                        stageId: r.stageId,
                        hits: r.hits,
                        misses: r.misses,
                        notTaken: r.notTaken,
                        time: r.officialTime,
                        completedAt: r.completedAt,
                    });
                }
            }

            await this.updatePendingCount();
        },

        initStage(totalShots) {
            this.shots = [];
            for (let i = 1; i <= totalShots; i++) {
                this.shots.push({ shot_number: i, result: 'not_taken' });
            }
            this.currentShotIndex = 0;
            this.rawTimeSeconds = null;
            this.isTimerRunning = false;
            this.timerStartedAt = null;
            this.timerElapsed = 0;
            this.rawDigits = '';
        },

        recordShot(result) {
            if (this.currentShotIndex >= this.shots.length) return;
            this.shots[this.currentShotIndex].result = result;
            if (this.currentShotIndex < this.shots.length - 1) {
                this.currentShotIndex++;
            }
        },

        undoLastShot() {
            if (this.currentShotIndex > 0 && this.shots[this.currentShotIndex].result === 'not_taken') {
                this.currentShotIndex--;
            }
            this.shots[this.currentShotIndex].result = 'not_taken';
        },

        goToShot(index) {
            if (index >= 0 && index < this.shots.length) {
                this.currentShotIndex = index;
            }
        },

        async loadShooterState(shooterId, stageId) {
            const key = `${shooterId}-${stageId}`;
            const completion = this.stageCompletions.get(key);
            if (completion) {
                return completion;
            }
            const localShots = await db.prsShotScores
                .where('[shooterId+stageId]')
                .equals([shooterId, stageId])
                .toArray();
            return localShots.length > 0 ? localShots : null;
        },

        async completeStage(matchId, stageId, shooterId, squadId, stage) {
            const timeRequired = stage.is_timed_stage || stage.is_tiebreaker;
            const time = this.effectiveTime;

            if (timeRequired && (!time || time <= 0)) {
                return { success: false, error: 'Time is required for this stage.' };
            }

            const payload = {
                shooter_id: shooterId,
                squad_id: squadId,
                raw_time_seconds: time > 0 ? parseFloat(time.toFixed(2)) : null,
                shots: this.shots.map(s => ({
                    shot_number: s.shot_number,
                    result: s.result,
                })),
            };

            const completion = {
                shooterId,
                stageId,
                hits: this.hits,
                misses: this.misses,
                notTaken: this.notTaken,
                time: payload.raw_time_seconds,
                completedAt: new Date().toISOString(),
            };

            this.stageCompletions.set(`${shooterId}-${stageId}`, completion);

            await db.prsShotScores.where('[shooterId+stageId]').equals([shooterId, stageId]).delete();
            for (const shot of this.shots) {
                await db.prsShotScores.add({
                    matchId,
                    stageId,
                    shooterId,
                    shotNumber: shot.shot_number,
                    result: shot.result,
                    deviceId: this.deviceId,
                    recordedAt: new Date().toISOString(),
                    synced: false,
                });
            }

            await db.prsStageResults.where('[shooterId+stageId]').equals([shooterId, stageId]).delete();
            await db.prsStageResults.add({
                matchId,
                stageId,
                shooterId,
                squadId,
                hits: this.hits,
                misses: this.misses,
                notTaken: this.notTaken,
                rawTime: payload.raw_time_seconds,
                officialTime: payload.raw_time_seconds,
                completedAt: new Date().toISOString(),
                synced: false,
            });

            await this.updatePendingCount();

            try {
                if (navigator.onLine) {
                    const headers = {};
                    const lockStage = localStorage.getItem('dc_locked_stage');
                    const lockSquad = localStorage.getItem('dc_locked_squad');
                    if (lockStage) {
                        try { headers['X-Device-Lock-Stage'] = JSON.parse(lockStage).stageId; } catch {}
                    }
                    if (lockSquad) {
                        try { headers['X-Device-Lock-Squad'] = JSON.parse(lockSquad).squadId; } catch {}
                    }
                    headers['X-Device-Id'] = this.deviceId;

                    await axios.post(`/api/matches/${matchId}/stages/${stageId}/score`, payload, { headers });

                    await db.prsShotScores.where('[shooterId+stageId]').equals([shooterId, stageId]).modify({ synced: true });
                    await db.prsStageResults.where('[shooterId+stageId]').equals([shooterId, stageId]).modify({ synced: true });
                    await this.updatePendingCount();
                }
            } catch (e) {
                if (e.response?.status === 401 || e.response?.status === 419) {
                    this.authExpired = true;
                }
                console.error('PRS score sync failed:', e);
            }

            return { success: true };
        },

        async syncPendingResults() {
            if (this.syncing || !navigator.onLine) return;
            this.syncing = true;

            try {
                const unsyncedResults = await db.prsStageResults
                    .where('matchId').equals(this.matchId)
                    .filter(r => !r.synced)
                    .toArray();

                for (const result of unsyncedResults) {
                    const shots = await db.prsShotScores
                        .where('[shooterId+stageId]')
                        .equals([result.shooterId, result.stageId])
                        .toArray();

                    const payload = {
                        shooter_id: result.shooterId,
                        squad_id: result.squadId,
                        raw_time_seconds: result.rawTime,
                        shots: shots.map(s => ({
                            shot_number: s.shotNumber,
                            result: s.result,
                        })),
                    };

                    try {
                        const headers = { 'X-Device-Id': this.deviceId };
                        await axios.post(`/api/matches/${this.matchId}/stages/${result.stageId}/score`, payload, { headers });

                        await db.prsShotScores.where('[shooterId+stageId]').equals([result.shooterId, result.stageId]).modify({ synced: true });
                        await db.prsStageResults.where('[shooterId+stageId]').equals([result.shooterId, result.stageId]).modify({ synced: true });
                    } catch (e) {
                        if (e.response?.status === 401 || e.response?.status === 419) {
                            this.authExpired = true;
                            break;
                        }
                        console.error('Sync failed for stage result:', e);
                    }
                }

                await this.updatePendingCount();
            } finally {
                this.syncing = false;
            }
        },

        async updatePendingCount() {
            const pendingShots = await db.prsShotScores
                .where('matchId').equals(this.matchId)
                .filter(s => !s.synced)
                .count();
            const pendingResults = await db.prsStageResults
                .where('matchId').equals(this.matchId)
                .filter(r => !r.synced)
                .count();
            this.pendingCount = pendingShots + pendingResults;
        },

        navigateTo(screen) {
            this.currentScreen = screen;
        },

        selectSquad(squadId) {
            this.selectedSquadId = squadId;
        },

        selectStage(stageId) {
            this.selectedStageId = stageId;
        },

        selectShooter(shooterId) {
            this.selectedShooterId = shooterId;
        },

        resetTimer() {
            this.isTimerRunning = false;
            this.timerStartedAt = null;
            this.timerElapsed = 0;
            this.rawTimeSeconds = null;
            this.rawDigits = '';
        },
    },
});
