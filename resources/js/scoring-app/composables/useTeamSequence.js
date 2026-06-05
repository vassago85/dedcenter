// Team gong-sequence engine for ELR matches.
//
// Builds the firing order for a 2-shooter team at a stage and the per-leg
// step list ("Step X of N"), plus the impact-based scoring helpers. A "leg"
// is one (shooter, gong) turn of up to 3 shots. The sequence rule:
//   walk gongs in distance order; for each gong, every assigned shooter (in
//   firing order) fires their 3 shots before advancing to the next gong.
//
// Gong assignment reuses the same division gating as the single-shooter ELR
// flow: explicit per-target division_ids (the elr_division_targets pivot) when
// present, otherwise the Minor=nearest-3 / Major=farthest-3 name convention.

export function sortedTargets(stage) {
    return [...(stage?.targets ?? [])].sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0));
}

// Ordered list of gongs a shooter engages at a stage (their calibre subset).
export function shooterTargets(shooter, stage) {
    const targets = sortedTargets(stage);
    if (targets.length === 0) return [];

    const divId = shooter?.division_id ?? null;
    const hasGating = targets.some(t => Array.isArray(t.division_ids) && t.division_ids.length > 0);
    if (hasGating && divId != null) {
        const filtered = targets.filter(t => Array.isArray(t.division_ids) && t.division_ids.includes(divId));
        if (filtered.length > 0) return filtered;
    }

    const name = (shooter?.division ?? '').toLowerCase();
    if (targets.length >= 4) {
        if (name.includes('major')) return targets.slice(1);      // drop nearest gong
        if (name.includes('minor')) return targets.slice(0, -1);  // drop farthest gong
    }
    return targets;
}

export function shooterEngagesTarget(shooter, target, stage) {
    return shooterTargets(shooter, stage).some(t => t.id === target.id);
}

// Active members ordered with the stage's first shooter (S1) leading.
export function orderedShooters(team, firstShooterId) {
    const active = (team?.shooters ?? [])
        .filter(s => (s.status ?? 'active') === 'active')
        .sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0) || (a.id - b.id));

    if (firstShooterId == null) return active;

    const first = active.find(s => s.id === firstShooterId);
    if (!first) return active;
    return [first, ...active.filter(s => s.id !== firstShooterId)];
}

// Default S1 for a stage: alternate the base order each stage so the shooter
// who went first last stage goes second this stage.
export function defaultFirstShooterId(team, stageIndex) {
    const active = (team?.shooters ?? [])
        .filter(s => (s.status ?? 'active') === 'active')
        .sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0) || (a.id - b.id));
    if (active.length === 0) return null;
    return active[stageIndex % active.length].id;
}

// Full leg list for a team at a stage. Each leg = { shooter, target, shots }.
//
// Current ordering is "alternating": per gong, each shooter fires before
// advancing. A planned per-match toggle ("full string": S1 fires all gongs,
// then S2) would branch the loop nesting here only — scoring is unaffected.
// See docs/elr-team-firing-pattern.md.
export function buildLegs(team, stage, firstShooterId) {
    const targets = sortedTargets(stage);
    const shooters = orderedShooters(team, firstShooterId);
    const legs = [];
    for (const target of targets) {
        for (const shooter of shooters) {
            if (shooterEngagesTarget(shooter, target, stage)) {
                legs.push({
                    shooter,
                    target,
                    shots: Math.max(1, target.max_shots || 3),
                });
            }
        }
    }
    return legs;
}

// ── Scoring (impact-based) ──

export function basePointsValue(target, distanceBased) {
    return distanceBased ? parseFloat(target?.distance_m ?? 0) : parseFloat(target?.base_points ?? 0);
}

export function multiplierForImpact(profile, impactNumber) {
    const mult = profile?.multipliers?.[impactNumber - 1];
    return typeof mult === 'number' ? mult : parseFloat(mult ?? 0);
}

export function pointsForImpact(target, impactNumber, profile, distanceBased) {
    const mult = multiplierForImpact(profile, impactNumber);
    return Math.round(basePointsValue(target, distanceBased) * mult * 100) / 100;
}

// Recompute impact numbers + points for a shooter's shots on one gong from an
// ordered (by shot number) list of results. Misses get impact null / 0 points.
// Returns [{ shotNumber, result, impactNumber, points }].
export function recomputeLeg(shots, target, profile, distanceBased) {
    let impact = 0;
    return shots
        .slice()
        .sort((a, b) => a.shotNumber - b.shotNumber)
        .map((s) => {
            if (s.result === 'hit') {
                impact++;
                return {
                    shotNumber: s.shotNumber,
                    result: 'hit',
                    impactNumber: impact,
                    points: pointsForImpact(target, impact, profile, distanceBased),
                };
            }
            return { shotNumber: s.shotNumber, result: s.result, impactNumber: null, points: 0 };
        });
}
