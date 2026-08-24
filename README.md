# Control Plane

Zentrales Admin/Ops-Backend für Konrads Apps (Doewe, Pokekon, Foto-Challenge, …) — aggregiert Einnahmen, GitHub-Issues und App-Analytics an einer Stelle. Spricht mit den anderen Apps per API (Service-Token-Pattern), ersetzt sie nicht.

Architekturplan: [`../docs/control-plane-backend-plan.md`](../docs/control-plane-backend-plan.md).

## Stack

PHP 8.5 · Symfony 7 · Doctrine ORM/Postgres · Symfony UX · FrankenPHP · Docker Compose

## Setup

```bash
cp .env .env.local   # echte Secrets hier eintragen (gitignored)
docker compose up -d --wait
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction
ADMIN_EMAIL=you@example.com ADMIN_PASSWORD=changeme docker compose exec -e ADMIN_EMAIL -e ADMIN_PASSWORD php bin/console app:seed-admin
```

Danach unter `https://localhost` einloggen.

## Tests

```bash
docker compose exec php vendor/bin/phpunit
```

## Deploy

Läuft auf Railway (Projekt `control-plane`, FrankenPHP-Prod-Image, eigene Postgres-Instanz). Bei jedem Push auf `main` mit grüner CI deployt der `deploy`-Job in `.github/workflows/ci.yml` automatisch (`railway up --service control-plane`).

```bash
railway logs --service control-plane   # Live-Logs
railway variables --service control-plane   # Env-Vars
railway up --service control-plane --detach   # manueller Deploy
```

`DATABASE_URL` referenziert Railways privates Netzwerk (`${{Postgres.DATABASE_URL}}`) und braucht zwingend `?serverVersion=<major>` in der URL — Doctrine kann den Postgres-Server sonst nicht autodetektieren (Fehler „Invalid platform version“).
