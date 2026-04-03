# Advertising System — Developer Guide

> **Note:** The system was refactored from "sponsorship" to "advertising" (April 2026). The underlying tables (`sponsors`, `sponsor_assignments`) retain their original names. All user-facing UI uses "brand", "advertiser", and "powered by" wording. Internally in code, the `Sponsor` model represents a brand/advertiser.

## Overview

DeadCenter uses an advertising system for feature-based "powered by" brand placements. Three active placements per event: Leaderboard, Results, and Scoring. The `matches` table holds advertising mode, MD package status, and full-package brand FK. Individual placements are tracked in `sponsor_assignments`.

## Data Model

### `sponsors` table
Central sponsor entity with name, slug, logo, contact info, active status, date windows, and an `assignable_by_match_director` flag controlling who can assign the sponsor.

### `sponsor_assignments` table
Links a sponsor to a placement via:
- `scope_type` — `platform`, `match`, or `matchbook`
- `scope_id` — null for platform, match ID, or match book ID
- `placement_key` — string-backed `PlacementKey` enum value

## Resolution Hierarchy

`SponsorPlacementResolver` resolves the best sponsor for a placement:

1. **Matchbook-specific** (scope_type=matchbook, scope_id=matchBookId) — only for matchbook placements
2. **Match-level** (scope_type=match, scope_id=matchId)
3. **Platform-level** (scope_type=platform, scope_id=null)
4. **null** — no sponsor, nothing rendered

All queries filter by: sponsor active, assignment active, date windows (starts_at/ends_at).

## PlacementKey Enum

| Key | Surface | Level |
|-----|---------|-------|
| `global_leaderboard` | Leaderboard | Platform |
| `global_results` | Results | Platform |
| `global_scoring` | Scoring | Platform |
| `global_exports` | Exports | Platform |
| `global_matchbook` | Match Book | Platform |
| `match_leaderboard` | Leaderboard | Match |
| `match_results` | Results | Match |
| `match_scoring` | Scoring | Match |
| `match_exports` | Exports | Match |
| `match_matchbook` | Match Book | Match |
| `matchbook_cover` | Cover | Matchbook |
| `matchbook_footer` | Footer | Matchbook |
| `matchbook_inside_cover` | Inside Cover | Matchbook |
| `matchbook_results_section` | Results | Matchbook |
| `sponsor_info_feature` | Info Page | Platform |

## `<x-sponsor-block>` Component

```blade
<x-sponsor-block placement="global_leaderboard" variant="block" />
<x-sponsor-block placement="global_results" :match-id="$match->id" variant="inline" />
```

Variants: `inline` (compact), `block` (card), `footer` (subtle), `cover` (large).

## MatchBook Pro Integration

`MatchBook` model belongs to `ShootingMatch` (one-to-one via `match_id`). It holds all documentation content (venue, stages, safety, branding). Related models: `MatchBookLocation`, `MatchBookStage`, `MatchBookShot`.

### PDF Generation
Uses `PdfDocumentRenderer` (Gotenberg primary, DomPDF fallback). Templates under `resources/views/matchbook/`.

### Sponsor Assignment in Match Books
- Cover sponsor: resolved via `matchbook_cover` placement
- Inside cover sponsors: resolved via `matchbook_inside_cover`
- Footer: resolved via `matchbook_footer`
- Acknowledgement text: stored in `match_books.sponsor_acknowledgement`

## Admin Configuration

**Route: `admin/sponsors`** — CRUD for sponsors (name, logo, dates, active, assignable by MD).

**Route: `admin/sponsor-assignments`** — Platform-level placement management (global defaults).

**Route: `admin/sponsor-info`** — Private sponsor information page content editor + access token.

## Match Director Configuration

Match directors assign sponsors via the match edit page. They can only choose sponsors with `assignable_by_match_director = true`. Empty selection inherits platform defaults.

Livewire component: `<livewire:match-sponsor-assignment :match="$match" />`

## Private Sponsor Info Page

Token-protected at `/sponsor-info/{token}`. Content stored in Settings with `sponsor_info_*` keys. Admin regenerates the token from `admin/sponsor-info`. Page includes `noindex, nofollow` meta.

## Testing

```bash
php artisan test --filter=SponsorPlacement
php artisan test --filter=SponsorInfoPage
```

## Seeding

```bash
php artisan db:seed --class=SponsorSeeder
```

Creates a demo sponsor, platform assignments, and sample sponsor info page content.
