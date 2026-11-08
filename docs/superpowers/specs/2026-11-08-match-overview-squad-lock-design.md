# Match overview truth and squad lock

Safety slice for the match control center. Finished squads stay put, and the Overview tab stops contradicting the squad list.

## Goal

From Ready onward, one click cannot reshuffle or delete the roster. The Overview status, scores line, and assignment warning each state a fact that is true for that match.

## Decisions already made

- Bulk squad actions lock at Ready, Active, and Completed. The per-shooter move stays available through Ready and Active. Completed locks individual edits too. Reopen Match stays Completed → Active, which brings individual edits back. Bulk actions stay locked until the match is stepped back to Squadding Open or earlier.
- Overview status uses the real status name. Ready says Ready.
- Zero recorded shots says "No scores yet". "Published" appears only when the match is Completed, the publish switch is on, and at least one shot exists. In-progress shots with the board on say "Live". Shots with the board off say "Hidden".
- "Not in a squad" counts confirmed registrations that were never placed. A shooter already in a squad is never in that count. "No team" is a separate line, and only on a match that uses teams.

## Squad lock

Two checks live on `ShootingMatch` and are the only rule the pages and the API use:

- `bulkSquadEditsLocked()`: true when status is Ready, Active, or Completed.
- `individualSquadEditsLocked()`: true when status is Completed.

Draft, Pre-Registration, Registration Open, Registration Closed, Squadding Open, and Squadding Closed keep today's behavior.

### Bulk actions

Refused while `bulkSquadEditsLocked()` is true. The button stays on the page, disabled, with this helper text: "Squads are locked. Step the match back to Squadding Open to randomize, auto-setup, or delete."

A refused server call shows that same sentence and writes nothing.

Methods, on both the organization and admin squadding pages, and on the organization and admin setup pages where the method exists:

- `autoSetupRelays`
- `autoSquad`
- `randomizeRelays`
- `autoTeam`
- `deleteShooter`
- `deleteSquad`
- `removeFromRelay`

### Individual edits

Refused while `individualSquadEditsLocked()` is true. Through Ready and Active they keep working, including a move between squads during a live match.

Helper text on a Completed match: "This match is completed. Reopen it from Scoring before moving shooters."

Methods:

- `moveShooter`, `moveShooterUp`, `moveShooterDown`
- `addWalkin`, `addSquad`
- `markPresent`, `markNoShow`, `markWithdrawn`, `markAllPresent`, `markAllNoShow`
- `setShooterCaliber`, `setShooterDivision`, `setShooterTeam`
- `assignToTeam`, `removeFromTeam`

Tablet API, same Completed-only refusal, HTTP 422 with the reopen sentence, and no write:

- `ShooterManagementController` `store`, `update`, `move`, `reorder`
- `SyncController::pushRoster`

`update` and `pushRoster` may change squad, cartridge, division, or team. On a Completed match the whole request is refused. On Ready and Active it still applies.

Reopen Match is unchanged. It moves Completed to Active. It does not unlock bulk actions. Stepping Active or Ready back to Squadding Open uses the existing status control and unlocks bulk actions. This slice adds no new reopen button.

`closeSquadding` and the side-bet toggle are status and pot controls. This slice does not change them.

## Overview

`MatchDashboardService::build()` is the source. The header in `resources/views/components/match-dashboard.blade.php` reads it. The finish-match steps keep using the `scores_published` flag. This slice does not change that flag, its default, or when result emails send.

### Status

`status_label` is `MatchStatus::label()`: Draft, Pre-Registration, Registration Open, Registration Closed, Squadding Open, Squadding Closed, Ready, Active, Completed.

### Scores line

The header reads a new `scores_line` of `none`, `hidden`, `live`, or `published`. Shot count uses the same stores as `attendanceRoster()`:

- ELR and ALRHA: `elr_shots` rows whose result is Hit or Miss
- PRS: `prs_shot_scores` rows
- Relay: `scores` rows

| Recorded shots | Publish switch | Status | Label | Colour |
| --- | --- | --- | --- | --- |
| 0 | either | any | No scores yet | muted |
| 1 or more | off | any | Hidden | amber |
| 1 or more | on | not Completed | Live | emerald |
| 1 or more | on | Completed | Published | emerald |

The word sits where the header already says "Scores published" or "Scores hidden". The prefix stays "Scores".

### Assignment warning

Remove the combined "not assigned to a squad or team" count. That count treats a missing team as a missing squad, and the shooter query only includes people who already have a squad.

Two counts:

- **Not in a squad.** Confirmed registrations (`payment_status` confirmed) that have a `user_id`, where that user has no shooter row on this match. Registrations with no user are left out of the count. A shooter with a null `squad_id`, if one exists, is included.
- **No team.** Shooters on this match with a null `team_id`. Computed only when the match uses teams. Otherwise the count is 0 and the line is absent.

A match uses teams when any of these is true:

- `team_event` is true
- scoring type is ELR or ALRHA and the engagement mode is a team sequence
- the match has at least one team row

Copy, with the Fix link that already goes to squadding:

- 1 shooter is not in a squad
- N shooters are not in a squad
- 1 shooter has no team
- N shooters have no team

Both lines can show. Neither shows at zero. The relay sandbox (Ready, three shooters in Squad 1, no teams, no shots) shows status Ready, "No scores yet", and no assignment warning.

## Cartridge column

On the organization and admin squadding tables, the cartridge input uses `ShooterRosterService::caliberFor()`. A registration cartridge that is null or blank must not hide the suffix. `caliberFor` returns the registration cartridge when it has text, and otherwise the suffix after " — " in the shooter name.

A name stored without that separator, such as "Christo Els 6 Creedmoor", stays one display string and an empty cartridge. Splitting those identities is out of scope.

The Shares rifle column is unchanged. It reads `share_rifle_with` on a confirmed registration. The 3 Oct Royal Flush import does not write that field, so the column stays blank for that match.

## Out of scope

- Type-first match setup, hiding setup blocks, PRS starting stage, ELR engagement fields, ALRHA pair rows
- The shooter "Your squad" card, email, and push
- Self-squadding capacity, lock date, blocked squads, squad-with-a-mate, move requests
- Multiple rifles under one person, and making SA ID optional on import
- Renaming "Relay" to "Squad" on PRS controls
- Changing where Reopen Match lands
- Changing the `scores_published` default

## Tests

- Ready, Active, and Completed refuse each bulk method and leave squads and shooters unchanged. Squadding Open still runs them.
- Ready and Active accept `moveShooter`. Completed refuses it. After `reopenMatch`, the status is Active and `moveShooter` succeeds. `randomizeRelays` still refuses.
- Completed `pushRoster` and tablet `move` return 422 and leave the shooter in place. Ready `pushRoster` still applies a squad change.
- A relay match with every shooter in a squad and no teams has no assignment warning. A team match with a shooter whose `team_id` is null shows the no-team line and does not call that shooter unassigned from a squad.
- A confirmed registration with no shooter row shows the not-in-a-squad line.
- A Ready match with no shot rows has status label Ready and scores line "No scores yet", including when `scores_published` is true.
- A Completed match with one shot and `scores_published` true says Published. The same match with the switch off says Hidden. An Active match with one shot and the switch on says Live.
- A shooter named "Alex Shooter — 6.5 CM" with a blank registration cartridge shows "6.5 CM" in the squadding input.
