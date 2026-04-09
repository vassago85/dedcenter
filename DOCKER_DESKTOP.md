# DeadCenter on Docker Desktop

Local full stack: **PHP 8.3 + Nginx**, **MySQL 8**, **Redis**, **queue worker**, **scheduler** — same shape as production, with debug-friendly defaults.

## Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (enable **WSL 2** backend on Windows).
- Project path: clone or copy the `deadcenter` repo to a folder Docker can access (WSL filesystem is faster than `C:\` for bind mounts, but both work).

## One-time setup

1. **Environment file**

   ```powershell
   cd path\to\deadcenter
   Copy-Item .env.docker.example .env
   ```

2. **Application key**

   `.env.docker.example` already includes a **dev-only** `APP_KEY`. To generate your own:

   ```powershell
   docker compose -f docker-compose.local.yml run --rm --no-deps app sh -c "composer install --no-interaction && php artisan key:generate"
   ```

   (The first time, the empty `vendor` volume needs Composer before Artisan can run.)

3. **Build and start**

   ```powershell
   docker compose -f docker-compose.local.yml up --build -d
   ```

   First start can take several minutes (Composer + NPM run inside the app entrypoint when `vendor` / `public/build` volumes are empty).

4. **Open the app**

   - Site: [http://localhost:8091](http://localhost:8091) (or the host port you set in `APP_PORT` in `.env`).
   - Badges preview: [http://localhost:8091/badges-preview](http://localhost:8091/badges-preview)

5. **Optional seed data**

   ```powershell
   docker compose -f docker-compose.local.yml exec app php artisan db:seed
   ```

## Daily commands

| Task | Command |
|------|---------|
| Logs (follow) | `docker compose -f docker-compose.local.yml logs -f app` |
| Artisan | `docker compose -f docker-compose.local.yml exec app php artisan …` |
| Stop | `docker compose -f docker-compose.local.yml down` |
| Reset DB volume | `docker compose -f docker-compose.local.yml down -v` (deletes MySQL data) |

## MySQL from the host

- Host: `127.0.0.1`
- Port: `3307` (default `DB_HOST_PORT`; avoids clashing with a local MySQL on 3306)
- Database / user / password: match `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` in `.env`.

## How it differs from `docker-compose.yml`

| File | Purpose |
|------|---------|
| `docker-compose.yml` | Production-style image (`Dockerfile`), minimal mounts. |
| `docker-compose.local.yml` | **Local dev**: `Dockerfile.dev` (Composer **with** dev packages), bind mount of source, named volumes for `vendor/` and `node_modules/`, `APP_DEBUG=true`, worker entrypoints that do **not** start a second Nginx. |

## Troubleshooting

- **Port already in use:** Change `APP_PORT` in `.env` (e.g. `8092`) and `APP_URL` to match.
- **Permission errors on `storage`:** From the project root, `docker compose -f docker-compose.local.yml exec app chown -R www-data:www-data storage bootstrap/cache` (usually the entrypoint already fixes this).
- **Stale assets after UI changes:** Run `docker compose -f docker-compose.local.yml exec app npm run build` or delete the `deadcenter-local-node` volume and restart so the entrypoint rebuilds front-end assets.
- **Composer changes:** After editing `composer.json`, run `docker compose -f docker-compose.local.yml exec app composer update` (or `install`).

## Production deploy

Server workflow is unchanged: use `docker-compose.yml` and `Dockerfile` on the Ubuntu host (see your deployment notes / `docker/entrypoint.sh`).
