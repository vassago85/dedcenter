# ELR Team Firing-Pattern Toggle (planned — not yet built)

A future enhancement to the existing ELR **team gong-sequence** mode
(`ElrEngagementMode::TeamSequence`). It adds a per-match toggle for *how the two
team members take turns*, without changing how shots are scored.

## The two patterns

For a Minor/Major team on a 4-gong station (Minor = G1-G3, Major = G2-G4):

- **Alternating** (current, default): per gong, shooter 1 fires 3 then shooter 2
  fires 3 before advancing.
  Order: `S1·G1, S1·G2, S2·G2, S1·G3, S2·G3, S2·G4`.
- **Full string** (new): shooter 1 fires *all* their shots across *all* their
  gongs (9), then shooter 2 fires all theirs (9).
  Order: `S1·G1, S1·G2, S1·G3, S2·G2, S2·G3, S2·G4`.

## Why scoring is unaffected

Impacts are counted **per shooter per gong** (a miss never consumes a multiplier
slot). That is independent of the order legs are presented in, so only the
*sequence ordering* and a config flag change — no scoring-engine changes.

## Scope when built

1. **DB / config**: add `matches.elr_team_firing_pattern` (enum
   `alternating` | `full_string`, default `alternating`). Migration mirrors
   `2026_06_05_000001_add_team_sequence_to_elr.php`. Add to `ShootingMatch`
   fillable + casts.
2. **API**: expose it on `MatchResource` (alongside `elr_engagement_mode` /
   `elr_team_time_limit_seconds`).
3. **Match-edit UI**: a toggle in the team-sequence block of both
   `resources/views/pages/admin/matches/edit.blade.php` and
   `.../org/matches/edit.blade.php` (only visible when mode is `team_sequence`).
4. **Vue**: branch `buildLegs()` in
   `resources/js/scoring-app/composables/useTeamSequence.js` — when
   `full_string`, group legs by shooter (in firing order) then gong, instead of
   by gong then shooter. `TeamSequenceFlow.vue` reads the flag from the match;
   the timer, undo, summary, and corrections all work unchanged.
5. **Android**: add `elrTeamFiringPattern` to `MatchEntity` (+ Room version
   bump), import it in `ImportMatchScreen`, serve it in `MatchRoutes`
   (`ApiMatchDetail`). No Ktor scoring changes needed — ordering lives entirely
   in the bundled Vue SPA. Rebuild + copy assets.

## Notes

- Rotation rules (firing-order swap between stages, S1/S2 swap within team) are
  orthogonal and apply to both patterns.
- Keep `alternating` the default so existing team-sequence matches are unchanged.
