# NowScrobbling Roadmap

Stand: 5. Maerz 2026

## Zielbild

NowScrobbling soll ein wartbares, performantes WordPress-Plugin bleiben, das Last.fm- und Trakt-Daten stabil ausliefert, auch bei API-Fehlern und Cache-/Hosting-Sonderfaellen.

## Prioritaeten

### P0 - Direkt als naechstes (1-2 Wochen)

1. Release-Readiness fuer v2.0.x herstellen: Dokumentation konsistent halten, Release-Checkliste definieren, Plugin in sauberer Test-WP-Instanz gegenpruefen (Aktivierung, Settings, alle 11 Shortcodes).
2. Testabdeckung fuer kritische Flows erhoehen: Unit-Tests fuer `Api/*Client`-Fehlerszenarien, `RestController`-Sanitization/Allowlist, `Shortcodes/*` bei leeren/ungueltigen API-Antworten.
3. Caching- und Diagnosefluss absichern: Cache-Metriken unter Last pruefen (Hit-Rate, Fallback-Nutzung) und Debug-Logging auf sinnvolle Defaults/Groessenlimit absichern.

### P1 - Kurzfristig danach (2-6 Wochen)

1. CI einrichten (GitHub Actions): `composer test`, `composer phpcs`, optional Matrix fuer mehrere PHP-Versionen >= 8.2.
2. Multisite-Kompatibilitaet konzipieren und umsetzen: Option-Storage je Site vs. Network und Cache-Invalidierung in Multisite pruefen.
3. Admin UX schrittweise verbessern: klarere Hinweise bei fehlenden API-Credentials, bessere Fehlermeldungen bei 429/5xx, vereinfachter Quick-Check im Diagnostics-Tab.

### P2 - Mittelfristig (6+ Wochen)

1. Erweiterbarkeit verbessern: klares Extension-Muster fuer weitere Dienste (z. B. Spotify) und dokumentierte Hook-/Filterpunkte.
2. Paketierung/Distribution professionalisieren: reproduzierbarer Release-Build ohne Dev-Abhaengigkeiten und validierter Deploy-Prozess.
3. Observability ausbauen: strukturierte Debug-Ausgaben und einfache Export-/Reset-Funktionen fuer Diagnosedaten.

## Nicht-Ziele (aktuell)

- Re-Design der kompletten Frontend-Ausgabe
- Einfuehrung eines JS-Frameworks
- API-Proxy als externer Dienst

## Konkreter naechster Sprint (Vorschlag)

1. Testluecken in REST- und Shortcode-Layern schliessen.
2. Lokale CI (OrbStack/Docker) fuer Test + PHPCS aufsetzen.
3. v2.0.1 als Stabilitaetsrelease schneiden.
