import Dexie from 'dexie';

export const db = new Dexie('DeadCenterDB');

db.version(2).stores({
    matches: 'id, status',
    targetSets: 'id, matchId, sortOrder',
    gongs: 'id, targetSetId, number',
    squads: 'id, matchId, sortOrder',
    shooters: 'id, squadId, sortOrder',
    scores: '++localId, [shooterId+gongId], matchId, synced',
    stageTimes: '++localId, [shooterId+targetSetId], matchId, synced',
    pendingSync: '++id, matchId, createdAt',
}).upgrade(tx => {
    // v2 adds stageTimes table - no data migration needed
});
