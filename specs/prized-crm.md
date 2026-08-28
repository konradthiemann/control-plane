# Spec: Prized-CRM & Nutzungs-Auswertung

## Problem/Ziel

Prized (`Pok-mon-TCG-Prize-Checker`) ist im control-plane bisher nur als
leerer `AppEntry` registriert (`slug: 'prized'`, keine Flags). Analog zu
Doewes CRM-Board (`hasDoeweCrm`) und Knips' Analytics soll die
`/apps/prized`-Seite zeigen: wie viele Accounts es gibt, wer sie sind, mit
Bearbeitung von außen (sperren/löschen) — plus, in einem zweiten Schritt,
Nutzungs-Kennzahlen, um die App gezielt zu verbessern.

Voraussetzung: die neuen `/api/admin/*`-Endpoints in Prized
(`Pok-mon-TCG-Prize-Checker/specs/admin-api.md`) müssen existieren und
deployed sein, bevor dieser Teil funktioniert.

## User Stories

Siehe `Pok-mon-TCG-Prize-Checker/specs/admin-api.md` — gleiche Stories, hier
die control-plane-seitige UI/Anbindung.

## Akzeptanzkriterien

**Phase A — Accounts-CRM**

1. `AppRegistry` bekommt einen neuen Flag `hasPrizedCrm: true` auf dem
   `prized`-Eintrag (analog `hasDoeweCrm`).
2. Neuer `PrizedAnalyticsClient` (analog `DoeweAnalyticsClient`) — Bearer-Auth
   über `PRIZED_SERVICE_TOKEN`, Base-URL über `PRIZED_BASE_URL`
   (`https://prized.konradthiemann.de`), Methoden: `fetchStats()`,
   `fetchUsers(page, pageSize)`, `suspendUser(id, bool)`, `deleteUser(id)`,
   `fetchUsage(days)`, `fetchDecksSummary()`. Jede Methode fängt
   `ExceptionInterface` ab und gibt `null`/`false` zurück (Prized nicht
   erreichbar ≠ Fehlerseite im control-plane).
3. Neue Twig-Komponente `PrizedCrmBoard` (analog `DoeweCrmBoard`,
   `#[AsLiveComponent]`): zeigt die Accounts-Tabelle
   (E-Mail, registriert am, zuletzt aktiv, Decks, Runden, Status),
   `#[LiveAction]`-Methoden `suspendUser`/`deleteUser` mit Bestätigungsdialog
   vor dem Löschen (Frontend, `confirm()`-artig wie beim Doewe-Board — dort
   nachschauen, wie das dort gelöst ist, und gleich lösen).
   Given kein gültiger Service-Token/Prized nicht erreichbar,
   When die Seite geladen wird,
   Then zeigt das Board einen Hinweis statt eines Fehlers (`lastError`-Pattern
   wie bei `DoeweCrmBoard`).
4. `AppController::show()` lädt bei `hasPrizedCrm` die Stats-Kachel
   (`totalUsers`, `newUsersLast7d/30d`) genau wie bei Doewe üblich, das Board
   selbst holt seine Daten live/selbst (siehe `DoeweCrmBoard`-Docblock:
   "fetches ... itself on every render").
5. `templates/app/show.html.twig` bindet `<twig:PrizedCrmBoard />` ein, wenn
   `appEntry.hasPrizedCrm` true ist (analog Doewe-Block dort).

**Phase B — Nutzungs-Auswertung**

6. `AppEntry` bekommt zusätzlich `hasPrizedAnalytics: true`.
7. `DashboardChartFactory` bekommt neue Chart-Methoden für Prized:
   `prizedRoundsPerDayChart`, `prizedAvgDurationChart`,
   `prizedAccuracyChart`, `prizedActiveUsersChart` (aus `fetchUsage()`) und
   `prizedTopDecksChart` (aus `fetchDecksSummary()`) — gleiches Muster wie
   die bestehenden `knips*`/`doewe*`-Methoden dort (gleiche Chart-Bibliothek/
   -Konventionen wiederverwenden, nicht neu erfinden).
8. `/apps/prized` zeigt bei `hasPrizedAnalytics`: Spiele/Tag, Ø Spieldauer,
   Ø Genauigkeit, aktive Nutzer/Tag (letzte 30 Tage) und Top-Decks — als
   Kachel-Reihe + Charts, im gleichen visuellen Stil wie der bestehende
   Knips-/Doewe-Abschnitt der Seite.
9. **Kein** Funnel-/Drop-off-Chart in dieser Phase — die Datenbasis dafür
   existiert nicht (siehe "Out of Scope" in der Prized-Spec). Stattdessen als
   Annäherung: "Tage seit letzter Runde"-Verteilung über alle Accounts (aus
   `fetchUsers()`s `lastRoundAt`) als grobe Aktivitäts-/Churn-Sicht — klar
   als Annäherung beschriftet, nicht als echtes Funnel-Tracking verkauft.

## Out of Scope

- Echtes Funnel-/Session-Drop-off-Tracking (siehe Prized-Spec).
- Bearbeiten von Decks/Runden-Inhalten von außen — nur Account-Ebene
  (sperren/löschen), keine Dateneingriffe in einzelne Decks.
- E-Mail-Versand an Prized-Nutzer aus dem control-plane heraus.

## Offene Fragen

- Reihenfolge: Phase A und B können in einem PR oder getrennt umgesetzt
  werden — Vorschlag: getrennte PRs (A zuerst, B danach), damit die
  Accounts-Ansicht schnell nutzbar ist, bevor die Analytics-Charts fertig
  sind. Reihenfolge in `Pok-mon-TCG-Prize-Checker` entsprechend spiegeln
  (Endpoints 1–4 zuerst, 5–6 danach).
- Env-Vars `PRIZED_SERVICE_TOKEN`/`PRIZED_BASE_URL` müssen nach Merge manuell
  in control-planes Railway-Projekt gesetzt werden (Platzhalter in `.env`
  committen, echten Wert nur in Railway-Variablen).
