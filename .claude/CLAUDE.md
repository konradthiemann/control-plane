# Control Plane

Admin/Ops-Backend über den anderen Apps im Workspace (Doewe, Pokekon, Foto-Challenge, …). Spricht per API mit ihnen, ersetzt sie nicht. Details/Architekturentscheidungen: `docs/control-plane-backend-plan.md` im Workspace-Root (`../docs/control-plane-backend-plan.md`).

## Stack
PHP 8.5 · Symfony 7 (Controller + Twig, **kein** API Platform) · Doctrine ORM/Postgres · Symfony UX (Twig/Live Components) · FrankenPHP · Docker Compose (lokal) · Railway (Deploy, Projekt `control-plane`, Region EU West).

## Deploy (Railway)
Auto-Deploy bei Push auf `main` mit grüner CI (`deploy`-Job in `.github/workflows/ci.yml`, `RAILWAY_TOKEN`-Secret). Dockerfile ist multi-stage — Railway baut ohne `--target` automatisch die letzte Stage (`frankenphp_prod`), passt ohne Zusatzkonfiguration. `PORT=8080`/`SERVER_NAME=:8080` sind fest gesetzt (Railway terminiert TLS selbst, FrankenPHPs Auto-HTTPS ist deaktiviert). `DATABASE_URL` referenziert `${{Postgres.DATABASE_URL}}` **plus** `?serverVersion=<major>&charset=utf8` — ohne den Parameter wirft Doctrine „Invalid platform version“, weil es den Server ohne expliziten Hinweis nicht autodetektiert. Migrationen laufen automatisch beim Container-Start (`frankenphp/docker-entrypoint.sh`), kein separates `preDeployCommand` nötig.

**Bekannter offener Punkt:** Der `frankenphp_prod`-Container erreichte Postgres anfangs nicht über Railways privates Netzwerk (`postgres.railway.internal`) — Ursache war am Ende die fehlende `serverVersion`, nicht das Netzwerk selbst (Verwechslung während der Fehlersuche, da der generische Retry-Loop-Fehlertext beides gleich aussehen lässt). Funktioniert jetzt stabil rein privat, ohne öffentlichen TCP-Proxy.

**Railway-CLI-Stolperstein (`railway variables --set`):** Ohne explizit verlinkten Service (`railway service`) landen per CLI gesetzte Variablen nirgends — `railway status` zeigt dann `Linked service: None`, der Befehl läuft aber ohne Fehlermeldung durch. Ergebnis: eine neue `#[Autowire(env: '...')]`-Variable fehlt zur Laufzeit und wirft `EnvNotFoundException` (500, nur im Railway-Log sichtbar, nicht im Container-Compile-Check). Passiert bei jeder neuen Drittsystem-Anbindung (Prized-CRM-Einführung hat das live erwischt). Vor jedem `railway variables --set`: erst `railway service` ausführen und den Service explizit auswählen, danach mit `railway run bash -c 'test -n "$VAR" && echo SET'` verifizieren (zeigt nur, ob gesetzt, nicht den Wert) — nicht nur `railway status` prüfen, das meldet den Fehlzustand nicht proaktiv.

**Debugging ohne Login:** Für Bugs auf per-Login-geschützten Seiten (z. B. `/apps/*`) lässt sich der exakte Controller-Code-Pfad ohne Session/Passwort direkt gegen die echten Railway-Env-Vars ausführen: `railway run php -r '...'` (instanziiert z. B. einen Service direkt) oder `railway run bash -c 'php bin/console ...'`. Läuft mit denselben Env-Vars wie der Live-Container, ohne dass Claude/ein Agent je Zugangsdaten braucht.

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

## Live Components (Symfony UX)
`symfony/ux-live-component` 3.4 hat CSRF-Tokens entfernt — Schutz läuft über
Same-Origin + Pflicht-Header `X-Requested-With`. Bei neuen `#[LiveAction]`-
Methoden **kein** `csrf_token()`/`isCsrfTokenValid()` nachrüsten (das ist
nur für klassische Symfony-Forms wie `templates/security/login.html.twig`
relevant) — der Schutz ist schon da.

## Twig `date`-Filter mit Millisekunden-Timestamps

Kommt ein Wert von einer Drittsystem-API als Millisekunden-Timestamp (z. B.
Prizeds `lastRoundAt`), **nicht** mit normaler Division `/1000` an den
`date`-Filter übergeben — der Rest-Bruchteil macht daraus einen Float
(`1787164145.403`), den Twig als Datums-*String* statt als Unix-Timestamp zu
parsen versucht und mit `DateMalformedStringException` crasht (500, nur im
Log sichtbar). Stattdessen Ganzzahl-Division: `(wert // 1000)|date(...)`.

## Admin-User
`ADMIN_EMAIL` / `ADMIN_PASSWORD` als Env setzen, dann `bin/console app:seed-admin` — kein volles User-Management, Solo-Betreiber.
