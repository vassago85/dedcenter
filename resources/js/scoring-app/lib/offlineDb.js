import Dexie from 'dexie';

const db = new Dexie('DeadCenterOffline');

db.version(1).stores({
    matches: 'id, status, date, scoring_type',
    registrations: 'id, match_id, user_id',
    scoreboards: 'match_id',
    notifications: 'id, read_at',
    userProfile: 'id',
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
    ]);
}
