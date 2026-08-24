# Control Plane

Admin/Ops-Backend über den anderen Apps im Workspace (Doewe, Pokekon, Foto-Challenge, …). Spricht per API mit ihnen, ersetzt sie nicht. Details/Architekturentscheidungen: `docs/control-plane-backend-plan.md` im Workspace-Root (`../docs/control-plane-backend-plan.md`).

## Stack
PHP 8.5 · Symfony 7 (Controller + Twig, **kein** API Platform) · Doctrine ORM/Postgres · Symfony UX (Twig/Live Components) · FrankenPHP · Docker Compose (lokal) · Railway (Deploy, noch offen).

## Struktur
- `src/Controller/` — dünne Controller.
- `src/Service/` — Logik, insbesondere Clients für die Drittsystem-APIs (z. B. `KnipsAnalyticsClient`).
- `src/Entity/` + `src/Repository/` — Doctrine.
- `templates/` — Twig, kein SPA-Frontend.
- `migrations/` — Doctrine-Migrations, keine Schema-Sync.

## Auth zu anderen Apps
Service-Token-Pattern (Bearer-Secret pro Zielapp, analog zu Doewes `CRON_SECRET`), kein OAuth2 zwischen den eigenen Apps. Secrets: Platzhalter in `.env` (committed), echte Werte nur in `.env.local` (gitignored) bzw. als Railway-Variable im Deploy.

## Dev-Commands
```
docker compose up -d --wait                                      # FrankenPHP + Postgres
docker compose exec php bin/console doctrine:migrations:migrate  # Schema
docker compose exec php vendor/bin/phpunit                       # Tests
docker compose exec php bin/console lint:yaml config             # Lint
docker compose exec php bin/console lint:twig templates          # Lint
```

## Admin-User
`ADMIN_EMAIL` / `ADMIN_PASSWORD` als Env setzen, dann `bin/console app:seed-admin` — kein volles User-Management, Solo-Betreiber.
