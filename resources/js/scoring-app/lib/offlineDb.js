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
