import Dexie from 'dexie';

const db = new Dexie('DeadCenterOffline');

// v1–v3 kept match/profile caches that nothing reads anymore. v4 drops those
// stores and leaves the two queues the scoring app actually replays.
db.version(1).stores({
    matches: 'id, status, date, scoring_type',
    registrations: 'id, match_id, user_id',
    scoreboards: 'match_id',
    notifications: 'id, read_at',
    userProfile: 'id',
});

db.version(2).stores({
    matches: 'id, status, date, scoring_type',
    registrations: 'id, match_id, user_id',
    scoreboards: 'match_id',
    notifications: 'id, read_at',
    userProfile: 'id',
    sideBetQueue: '++id, &[match_id+shooter_id], match_id, queued_at',
});

db.version(3).stores({
    matches: 'id, status, date, scoring_type',
    registrations: 'id, match_id, user_id',
    scoreboards: 'match_id',
    notifications: 'id, read_at',
    userProfile: 'id',
    sideBetQueue: '++id, &[match_id+shooter_id], match_id, queued_at',
    correctionQueue: '++id, &[match_id+shooter_id+stage_id], match_id, queued_at',
});

db.version(4).stores({
    sideBetQueue: '++id, &[match_id+shooter_id], match_id, queued_at',
    correctionQueue: '++id, &[match_id+shooter_id+stage_id], match_id, queued_at',
});

// Last tap wins for a shooter. The unique index means we delete the old row
// before insert — put() would collide on the auto-increment id.
export async function queueSideBetToggle(matchId, shooterId, desiredState) {
    await db.transaction('rw', db.sideBetQueue, async () => {
        const existing = await db.sideBetQueue
            .where('[match_id+shooter_id]')
            .equals([matchId, shooterId])
            .first();
        if (existing) {
            await db.sideBetQueue.delete(existing.id);
        }
        await db.sideBetQueue.add({
            match_id: matchId,
            shooter_id: shooterId,
            desired_state: !!desiredState,
            queued_at: Date.now(),
        });
    });
}

export async function getSideBetQueue(matchId = null) {
    if (matchId == null) return db.sideBetQueue.orderBy('queued_at').toArray();
    return db.sideBetQueue.where('match_id').equals(matchId).sortBy('queued_at');
}

export async function getSideBetQueueCount(matchId = null) {
    if (matchId == null) return db.sideBetQueue.count();
    return db.sideBetQueue.where('match_id').equals(matchId).count();
}

export async function removeSideBetQueueEntry(id) {
    await db.sideBetQueue.delete(id);
}

// Latest correction for a shooter+stage wins. Replayed against
// POST /api/matches/{match}/shooters/{shooter}/correct.
export async function queueShooterCorrection(matchId, shooterId, stageId, payload) {
    await db.transaction('rw', db.correctionQueue, async () => {
        const existing = await db.correctionQueue
            .where('[match_id+shooter_id+stage_id]')
            .equals([matchId, shooterId, stageId])
            .first();
        if (existing) {
            await db.correctionQueue.delete(existing.id);
        }
        await db.correctionQueue.add({
            match_id: matchId,
            shooter_id: shooterId,
            stage_id: stageId,
            payload,
            queued_at: Date.now(),
        });
    });
}

export async function getCorrectionQueue(matchId = null) {
    if (matchId == null) return db.correctionQueue.orderBy('queued_at').toArray();
    return db.correctionQueue.where('match_id').equals(matchId).sortBy('queued_at');
}

export async function removeCorrectionQueueEntry(id) {
    await db.correctionQueue.delete(id);
}
