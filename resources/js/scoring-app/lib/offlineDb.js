import Dexie from 'dexie';

const db = new Dexie('DeadCenterOffline');

db.version(1).stores({
    matches: 'id, status, date, scoring_type',
    registrations: 'id, match_id, user_id',
    scoreboards: 'match_id',
    notifications: 'id, read_at',
    userProfile: 'id',
});

// v2: side-bet buy-in toggle queue. Compound index on (match_id,
// shooter_id) so we can coalesce — if the MD taps the same shooter
// in→out→in while offline, only the latest desired state survives
// to be replayed when the phone comes back online.
db.version(2).stores({
    matches: 'id, status, date, scoring_type',
    registrations: 'id, match_id, user_id',
    scoreboards: 'match_id',
    notifications: 'id, read_at',
    userProfile: 'id',
    sideBetQueue: '++id, &[match_id+shooter_id], match_id, queued_at',
});

// v3: single-shooter correction queue. Used by CorrectShooterModal when
// the MD applies a stage correction while offline. Coalesced on
// (match_id, shooter_id, stage_id) — the latest correction for that
// (shooter, stage) wins, because the server replays correction state
// idempotently (target gong/shot states + reason).
db.version(3).stores({
    matches: 'id, status, date, scoring_type',
    registrations: 'id, match_id, user_id',
    scoreboards: 'match_id',
    notifications: 'id, read_at',
    userProfile: 'id',
    sideBetQueue: '++id, &[match_id+shooter_id], match_id, queued_at',
    correctionQueue: '++id, &[match_id+shooter_id+stage_id], match_id, queued_at',
});

export default db;

export async function cacheMatches(matches) {
    await db.matches.bulkPut(matches.map(m => ({
        ...m,
        _cachedAt: Date.now(),
    })));
}

export async function getCachedMatches() {
    return db.matches.orderBy('date').reverse().toArray();
}

export async function cacheScoreboard(matchId, data) {
    await db.scoreboards.put({
        match_id: matchId,
        data,
        _cachedAt: Date.now(),
    });
}

export async function getCachedScoreboard(matchId) {
    return db.scoreboards.get(matchId);
}

export async function cacheRegistrations(registrations) {
    await db.registrations.bulkPut(registrations);
}

export async function getUserRegistrations(userId) {
    return db.registrations.where('user_id').equals(userId).toArray();
}

export async function cacheNotifications(notifications) {
    await db.notifications.bulkPut(notifications);
}

export async function getCachedNotifications() {
    return db.notifications.orderBy('id').reverse().toArray();
}

export async function cacheUserProfile(profile) {
    await db.userProfile.put(profile);
}

export async function getCachedUserProfile() {
    return db.userProfile.toArray().then(arr => arr[0] || null);
}

export async function clearAllOfflineData() {
    await Promise.all([
        db.matches.clear(),
        db.registrations.clear(),
        db.scoreboards.clear(),
        db.notifications.clear(),
        db.userProfile.clear(),
        db.sideBetQueue.clear(),
        db.correctionQueue.clear(),
    ]);
}

// ─── Side-bet buy-in offline queue ─────────────────────────────────────────
// Each row is an "MD asked for this shooter to end up in/out of the pot"
// instruction, replayed against POST /side-bet/toggle/{shooter} when the
// device is back online. Server endpoint is idempotent (accepts explicit
// `in: bool`), so re-tries are safe.

export async function queueSideBetToggle(matchId, shooterId, desiredState) {
    // Coalesce: replace any prior queued entry for this (match, shooter)
    // pair so the last tap wins. The unique index on [match_id+shooter_id]
    // means we must explicitly delete the old row first instead of just
    // upserting through put() (which would fail the unique constraint
    // because put() needs the auto-incremented id).
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

export async function clearSideBetQueueForMatch(matchId) {
    await db.sideBetQueue.where('match_id').equals(matchId).delete();
}

// ─── Single-shooter correction offline queue ───────────────────────────────
// Used by CorrectShooterModal when the MD makes a stage correction while
// offline. We store the *desired* end state for that shooter+stage along
// with the reason, then replay it against
//   POST /api/matches/{match}/shooters/{shooter}/correct
// when connectivity returns. Server is idempotent because it computes a
// diff against the current Score rows.

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

export async function getCorrectionQueueCount(matchId = null) {
    if (matchId == null) return db.correctionQueue.count();
    return db.correctionQueue.where('match_id').equals(matchId).count();
}

export async function removeCorrectionQueueEntry(id) {
    await db.correctionQueue.delete(id);
}

export async function clearCorrectionQueueForMatch(matchId) {
    await db.correctionQueue.where('match_id').equals(matchId).delete();
}
