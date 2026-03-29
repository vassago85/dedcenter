# DeadCenter Agent Handoff

## Project Overview
DeadCenter is a multi-discipline shooting sport scoring platform. It's a Laravel 12 app with Livewire/Volt, Flux UI, Tailwind CSS 4, Alpine.js, and a Vue.js SPA for the scoring interface. It runs in Docker both locally (port 8091) and on production (port 8092, server 41.72.157.26 at `/opt/deadcenter/`).

- **Repo:** https://github.com/vassago85/dedcenter (branch: `master`)
- **Local:** http://localhost:8091
- **Production:** https://deadcenter.co.za (behind reverse proxy, port 8092)
- **Stack:** Laravel 12, Livewire 4/Volt, Flux UI, Tailwind CSS 4, Alpine.js, Vue 3, Vite 7, MySQL 8.0, Redis
- **Git rules:** Windows PowerShell -- no `&&`, no heredocs. Use `;` or separate sequential shell calls. Simple `-m "message"` for commits.

## Accounts (in DatabaseSeeder)
| Email | Password | Role |
|---|---|---|
| admin@deadcenter.co.za | password | admin |
| paul@charsley.co.za | password | admin |
| test@example.com | password | member |

## Server Deployment Commands
```bash
cd /opt/deadcenter
git pull origin master
docker compose build --no-cache app
docker compose up -d --force-recreate app scheduler queue
```
To seed users on the server:
```bash
docker compose exec app php artisan db:seed
```

## IMMEDIATE TASKS (pick up from here)

### 1. Fix logo text on login page (BUG)
The `<x-app-logo>` component (`resources/views/components/app-logo.blade.php`) uses `text-slate-900 dark:text-white/90` for the "DEAD" text. On the login page (dark background), the dark: variant may not be applying because Tailwind CSS 4 defaults to `prefers-color-scheme` media queries, not class-based dark mode. The `<html>` element has `class="dark"` but that may not be enough for TW4.

**Fix options:**
- Configure Tailwind CSS 4 for class-based dark mode (check `resources/css/app.css` for `@custom-variant` or `@variant` config)
- Or just hardcode the "DEAD" text color to `text-white/90` since the entire site is dark-themed anyway (simplest fix)

The component is at: `resources/views/components/app-logo.blade.php`

### 2. Seed users on the production server
Run `docker compose exec app php artisan db:seed` on the server so the admin and dev accounts exist. The seeder uses `firstOrCreate` so it's safe to run multiple times.

### 3. Fix Smart Time Input (FEATURE CHANGE)
**Current (wrong):** In `resources/js/scoring-app/views/PrsScoringFlow.vue`, the `digitsToSeconds()` function treats the last 2 digits as centiseconds: typing "105" becomes 1.05 seconds. This is wrong -- nobody completes a stage in 1.05 seconds.

**Correct behavior:** When someone types "105" they mean **105 seconds** (1 min 45 sec). The input should accept whole seconds. If they need decimal precision (2 decimal places for centiseconds from a timer), they should type a dot/period explicitly (e.g. "105.23" = 105.23 seconds). No implied decimal.

**Files to change:**
- `resources/js/scoring-app/views/PrsScoringFlow.vue` -- `digitsToSeconds()` function (line ~323), `onDigitInput()`, `secondsToRawDigits()`, and the label text that says "Type digits from timer (e.g. 105 = 1.05s)"
- `resources/views/welcome.blade.php` -- landing page mentions "Smart time input: type '105' and it reads as 1.05 seconds" (line ~370). Update or remove this claim.

**Key details from user:** "there is no way someone got 1.05 seconds to complete a stage." Times are plain seconds. They do need 2 decimal places (centiseconds) because timers show them, but the input should be a normal number field, not implied-decimal magic.

### 4. Logo Consistency (PARTIALLY DONE)
A `<x-app-logo>` Blade component was created and deployed to most places. It uses a crosshair SVG + "DEAD" (white in dark mode) + "CENTER" (red). Files already updated:
- `components/layouts/app.blade.php` (sidebar) -- DONE
- `components/layouts/auth.blade.php` (login/register) -- DONE
- `welcome.blade.php` (landing nav + footer) -- DONE
- `scoring.blade.php` (PWA shell) -- has favicon reference, DONE
- `public/favicon.svg` -- DONE (crosshair SVG)

The Vue scoring app (`resources/js/scoring-app/views/MatchSelect.vue`) still uses `<img :src="'/logo.png'" ...>` and plain text "DeadCenter". This needs to be updated to use the crosshair SVG inline (since it's a Vue component, can't use Blade). Copy the SVG markup from the `app-logo.blade.php` component into the Vue template.

### 5. Rebuild & Deploy
After all fixes, rebuild locally and push:
```powershell
# In c:\laragon\www\deadcenter
docker compose build --no-cache app
docker compose up -d --force-recreate app scheduler queue
git add -A
git commit -m "description"
git push origin master
```
Then on server:
```bash
cd /opt/deadcenter
git pull origin master
docker compose build --no-cache app
docker compose up -d --force-recreate app scheduler queue
docker compose exec app php artisan db:seed
```

## KEY ARCHITECTURE NOTES
- **Scoring types:** "standard" (round robin / gong-style) and "prs" (Precision Rifle Series, hit/miss/shot-not-taken per target)
- **Divisions** = equipment classes (single-select per shooter): Open, Factory, Limited
- **Categories** = demographics (multi-select): Overall, Ladies, Junior (U21 centrefire / U18 rimfire as of Jan 1), Senior (55+)
- **Side Bet** = optional for standard matches, ranks by smallest gong hits with distance tiebreaker
- **Match ownership:** Only the creator can edit/delete a match. Anyone can register.
- **Scoring auth:** Match director logs into tablets, locks each to a squad
- **Offline sync:** Vue scoring app uses IndexedDB (Dexie.js) for offline persistence, syncs when connected
- **HTTPS:** Production forces HTTPS via `URL::forceScheme('https')` in `AppServiceProvider` when `APP_ENV=production`. Proxy trust configured in `bootstrap/app.php` with `trustProxies(at: '*')`.
- **Junior age rule:** Under 21 for centrefire, under 18 for rimfire. They stay junior until Dec 31 of the year they turn 18/21 (Jan 1 cutoff, calendar year season).

## FILE MAP (key files)
```
deadcenter/
├── app/
│   ├── Enums/MatchStatus.php
│   ├── Http/Controllers/Api/
│   │   ├── ScoreboardController.php    (leaderboard + side bet logic)
│   │   └── ScoreController.php         (score CRUD API)
│   ├── Models/
│   │   ├── ShootingMatch.php
│   │   ├── Shooter.php, Squad.php, Score.php
│   │   ├── MatchDivision.php, MatchCategory.php
│   │   ├── Organization.php, MatchRegistration.php
│   │   └── Gong.php, TargetSet.php, StageTime.php
│   └── Providers/AppServiceProvider.php (HTTPS force)
├── resources/
│   ├── views/
│   │   ├── components/
│   │   │   ├── app-logo.blade.php      (reusable logo)
│   │   │   └── layouts/
│   │   │       ├── app.blade.php       (main app layout w/ sidebar)
│   │   │       ├── auth.blade.php      (login/register layout)
│   │   │       ├── portal.blade.php    (white-label org portal)
│   │   │       └── scoreboard.blade.php
│   │   ├── pages/
│   │   │   ├── member/ (dashboard, matches, match-detail, organizations)
│   │   │   ├── admin/ (dashboard, matches, settings, organizations)
│   │   │   ├── org/ (dashboard, matches, settings, clubs, admins)
│   │   │   ├── auth/ (login, register)
│   │   │   ├── portal/ (landing, matches, match-detail, leaderboard)
│   │   │   ├── scoreboard.blade.php, live.blade.php, leaderboard.blade.php
│   │   └── welcome.blade.php          (landing page)
│   └── js/scoring-app/
│       ├── views/
│       │   ├── PrsScoringFlow.vue      (PRS scoring + time input)
│       │   ├── ScoringFlow.vue         (standard scoring)
│       │   ├── Scoreboard.vue, MatchSelect.vue, MatchOverview.vue
│       │   └── SquadSelect.vue
│       ├── stores/ (scoringStore.js, matchStore.js)
│       └── db/index.js (Dexie/IndexedDB)
├── database/
│   ├── seeders/DatabaseSeeder.php
│   └── migrations/
├── docker-compose.yml, Dockerfile, docker/entrypoint.sh
└── routes/web.php, routes/api.php
```
