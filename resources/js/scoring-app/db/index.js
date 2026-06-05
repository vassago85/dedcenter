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

db.version(3).stores({
    matches: 'id, status',
    targetSets: 'id, matchId, sortOrder',
    gongs: 'id, targetSetId, number',
    squads: 'id, matchId, sortOrder',
    shooters: 'id, squadId, sortOrder',
    scores: '++localId, [shooterId+gongId], matchId, synced',
    stageTimes: '++localId, [shooterId+targetSetId], matchId, synced',
    pendingSync: '++id, matchId, createdAt',
    elrShots: '++localId, matchId, shooterId, elrTargetId, shotNumber, [shooterId+elrTargetId+shotNumber]',
});

db.version(4).stores({
    matches: 'id, status',
    targetSets: 'id, matchId, sortOrder',
    gongs: 'id, targetSetId, number',
    squads: 'id, matchId, sortOrder',
    shooters: 'id, squadId, sortOrder',
    scores: '++localId, [shooterId+gongId], matchId, synced',
    stageTimes: '++localId, [shooterId+targetSetId], matchId, synced',
    pendingSync: '++id, matchId, createdAt',
    elrShots: '++localId, matchId, shooterId, elrTargetId, shotNumber, [shooterId+elrTargetId+shotNumber]',
    prsShotScores: '++localId, [shooterId+stageId], [shooterId+stageId+shotNumber], matchId, synced',
    prsStageResults: '++localId, [shooterId+stageId], matchId, synced',
});

// v5: ELR team gong-sequence mode. elrShots gains impactNumber (no index
// change needed); add elrTeamStageEntries to track each team's per-stage
// lifecycle (timer, rotation) offline so it survives reloads and syncs on
// reconnect.
db.version(5).stores({
    matches: 'id, status',
    targetSets: 'id, matchId, sortOrder',
    gongs: 'id, targetSetId, number',
    squads: 'id, matchId, sortOrder',
    shooters: 'id, squadId, sortOrder',
    scores: '++localId, [shooterId+gongId], matchId, synced',
    stageTimes: '++localId, [shooterId+targetSetId], matchId, synced',
    pendingSync: '++id, matchId, createdAt',
    elrShots: '++localId, matchId, shooterId, elrTargetId, shotNumber, [shooterId+elrTargetId+shotNumber]',
    prsShotScores: '++localId, [shooterId+stageId], [shooterId+stageId+shotNumber], matchId, synced',
    prsStageResults: '++localId, [shooterId+stageId], matchId, synced',
    elrTeamStageEntries: '++localId, matchId, [teamId+elrStageId], synced',
});
