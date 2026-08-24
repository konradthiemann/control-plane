# Control Plane

Admin/Ops-Backend über den anderen Apps im Workspace (Doewe, Pokekon, Foto-Challenge, …). Spricht per API mit ihnen, ersetzt sie nicht. Details/Architekturentscheidungen: `docs/control-plane-backend-plan.md` im Workspace-Root (`../docs/control-plane-backend-plan.md`).

## Stack
PHP 8.5 · Symfony 7 (Controller + Twig, **kein** API Platform) · Doctrine ORM/Postgres · Symfony UX (Twig/Live Components) · FrankenPHP · Docker Compose (lokal) · Railway (Deploy, Projekt `control-plane`, Region EU West).

## Deploy (Railway)
Auto-Deploy bei Push auf `main` mit grüner CI (`deploy`-Job in `.github/workflows/ci.yml`, `RAILWAY_TOKEN`-Secret). Dockerfile ist multi-stage — Railway baut ohne `--target` automatisch die letzte Stage (`frankenphp_prod`), passt ohne Zusatzkonfiguration. `PORT=8080`/`SERVER_NAME=:8080` sind fest gesetzt (Railway terminiert TLS selbst, FrankenPHPs Auto-HTTPS ist deaktiviert). `DATABASE_URL` referenziert `${{Postgres.DATABASE_URL}}` **plus** `?serverVersion=<major>&charset=utf8` — ohne den Parameter wirft Doctrine „Invalid platform version“, weil es den Server ohne expliziten Hinweis nicht autodetektiert. Migrationen laufen automatisch beim Container-Start (`frankenphp/docker-entrypoint.sh`), kein separates `preDeployCommand` nötig.

**Bekannter offener Punkt:** Der `frankenphp_prod`-Container erreichte Postgres anfangs nicht über Railways privates Netzwerk (`postgres.railway.internal`) — Ursache war am Ende die fehlende `serverVersion`, nicht das Netzwerk selbst (Verwechslung während der Fehlersuche, da der generische Retry-Loop-Fehlertext beides gleich aussehen lässt). Funktioniert jetzt stabil rein privat, ohne öffentlichen TCP-Proxy.

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
