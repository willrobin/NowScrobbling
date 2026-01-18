# NowScrobbling WordPress Plugin

**Version 2.0.0** - Komplette Neuimplementierung mit moderner Architektur

## Übersicht

NowScrobbling ist ein WordPress-Plugin, das Last.fm und Trakt.tv Daten per Shortcode darstellt. Version 2.0 ist eine vollständige Neuimplementierung mit moderner PHP 8.2+ Architektur, PSR-4 Autoloading, Multi-Layer-Caching und REST API.

## Neue Features in Version 2.0.0

### Architektur (Komplett neu)
- **PSR-4 Autoloading**: Composer-basiertes Autoloading für saubere Klassenstruktur
- **Dependency Injection**: Leichtgewichtiger DI-Container für lose Kopplung
- **Modularität**: Saubere Trennung von Concerns (Cache, API, Shortcodes, Admin, Security)
- **Keine externen Abhängigkeiten**: Reines PHP, kein jQuery für Frontend

### Multi-Layer Caching
- **In-Memory Layer**: Per-Request-Cache für schnellen Zugriff
- **Transient Layer**: WordPress Transients als primärer Cache (1-60 Min)
- **Fallback Layer**: 7-Tage Notfall-Cache bei API-Fehlern
- **ETag Layer**: HTTP Conditional Requests für Bandbreitenersparnis
- **Stale-While-Revalidate**: Alte Daten anzeigen während neue geladen werden
- **Thundering Herd Protection**: Verhindert gleichzeitige API-Anfragen

### Umgebungserkennung
- **Cloudflare**: Automatische Header-Optimierung
- **LiteSpeed**: Cache-Purge Integration
- **Nginx**: FastCGI Cache Support
- **Object Cache**: Redis/Memcached/APCu Erkennung

### REST API (Neu)
- `GET /wp-json/nowscrobbling/v1/render/{shortcode}` - Shortcode rendern
- `GET /wp-json/nowscrobbling/v1/status` - Plugin-Status (Admin)
- `POST /wp-json/nowscrobbling/v1/cache/clear` - Cache leeren (Admin)

### Sicherheit
- **Granulare Nonces**: Separate Tokens pro Endpunkt
- **Zentraler Sanitizer**: Korrekte Reihenfolge (unslash → sanitize → validate)
- **Rate Limiting**: API-Missbrauchsschutz
- **Capability Checks**: Admin-Aktionen nur mit `manage_options`

### Admin-Interface
- **Tab-basierte Einstellungen**: Credentials, Caching, Display, Diagnostics
- **API-Verbindungstest**: Live-Test der API-Credentials
- **Cache-Statistiken**: Detaillierte Metriken und Hit-Raten
- **Debug-Logging**: Umfassendes Logging bei aktiviertem Debug-Modus

### JavaScript (Vanilla)
- **Kein jQuery**: Reines ES6+ JavaScript
- **IntersectionObserver**: Lazy Loading für sichtbare Shortcodes
- **Hash-basiertes Diffing**: Kein DOM-Flackern bei Updates
- **Exponential Backoff**: Intelligentes Polling bei Fehlern
- **Visibility API**: Pausiert Polling bei inaktivem Tab

### Migration
- **Automatische Migration**: Optionen von v1.3 werden automatisch migriert
- **Rollback-Funktion**: Möglichkeit zur Rückkehr auf v1.3 Optionen
- **Keine Datenverluste**: Alle bestehenden Einstellungen bleiben erhalten

---

## Anforderungen

- **WordPress 6.0** oder höher
- **PHP 8.2** oder höher
- **Composer** (für Installation aus Git)
- Last.fm API-Schlüssel (optional)
- Trakt.tv Client ID (optional)

## Installation

### Aus WordPress Repository
1. Suche nach "NowScrobbling" im Plugin-Verzeichnis
2. Klicke auf "Installieren" und dann "Aktivieren"
3. Gehe zu "Einstellungen > NowScrobbling" und konfiguriere deine API-Schlüssel

### Aus Git (Entwicklung)
```bash
cd wp-content/plugins/
git clone https://github.com/willrobin/NowScrobbling.git
cd NowScrobbling/nowscrobbling
composer install --no-dev --optimize-autoloader
```

Dann aktiviere das Plugin im WordPress Admin-Panel.

## Konfiguration

### API-Schlüssel einrichten

1. **Last.fm API-Schlüssel**:
   - Besuche [Last.fm API](https://www.last.fm/api/account/create)
   - Erstelle eine neue API-Anwendung
   - Kopiere den API-Schlüssel in die Plugin-Einstellungen (Tab: Credentials)

2. **Trakt.tv Client ID**:
   - Besuche [Trakt.tv API](https://trakt.tv/oauth/applications/new)
   - Erstelle eine neue OAuth-Anwendung
   - Kopiere die Client ID in die Plugin-Einstellungen (Tab: Credentials)

### Cache-Einstellungen (Tab: Caching)

- **Last.fm Cache-Dauer**: 1-60 Minuten (Standard: 1 Minute)
- **Trakt Cache-Dauer**: 1-60 Minuten (Standard: 5 Minuten)
- **Allgemeine Cache-Dauer**: 1-60 Minuten (Standard: 60 Minuten)
- **Fallback-Cache**: 7 Tage (fest, für Notfälle)

### Display-Einstellungen (Tab: Display)

- **Anzahl Top Artists/Albums/Tracks**: 5-50 Einträge
- **Anzahl Loved Tracks**: 5-50 Einträge
- **Polling-Intervall**: 20-300 Sekunden

## Verfügbare Shortcodes

### Last.fm Shortcodes

#### `[nowscr_lastfm_indicator]`
Zeigt den aktuellen Status der Last.fm Aktivität an (Now Playing oder Last Played).

#### `[nowscr_lastfm_history max_length="45"]`
Zeigt die letzten Scrobbles von Last.fm an.
- `max_length`: Maximale Zeichenlänge (Standard: 45)

#### `[nowscr_lastfm_top_artists period="7day" max_length="15"]`
Zeigt die Top-Künstler von Last.fm an.
- `period`: Zeitraum (`7day`, `1month`, `3month`, `6month`, `12month`, `overall`)
- `max_length`: Maximale Zeichenlänge (Standard: 15)

#### `[nowscr_lastfm_top_albums period="7day" max_length="45"]`
Zeigt die Top-Alben von Last.fm an.
- `period`: Zeitraum (wie oben)
- `max_length`: Maximale Zeichenlänge (Standard: 45)

#### `[nowscr_lastfm_top_tracks period="7day" max_length="45"]`
Zeigt die Top-Titel von Last.fm an.
- `period`: Zeitraum (wie oben)
- `max_length`: Maximale Zeichenlänge (Standard: 45)

#### `[nowscr_lastfm_lovedtracks max_length="45"]`
Zeigt die Lieblingslieder von Last.fm an.
- `max_length`: Maximale Zeichenlänge (Standard: 45)

### Trakt.tv Shortcodes

#### `[nowscr_trakt_indicator]`
Zeigt den aktuellen Status der Trakt Aktivität an (Currently Watching oder Last Watched).

#### `[nowscr_trakt_history show_year="true" show_rating="true" show_rewatch="true"]`
Zeigt die letzten Aktivitäten von Trakt an.
- `show_year`: Jahr anzeigen (true/false)
- `show_rating`: Bewertung anzeigen (true/false)
- `show_rewatch`: Rewatch-Count anzeigen (true/false)

#### `[nowscr_trakt_last_movie show_year="true" show_rating="true" show_rewatch="true"]`
Zeigt die letzten Filme von Trakt an.

#### `[nowscr_trakt_last_show show_year="true" show_rating="true" show_rewatch="true"]`
Zeigt die letzten Serien von Trakt an.

#### `[nowscr_trakt_last_episode show_year="true" show_rating="true" show_rewatch="true"]`
Zeigt die letzten Episoden von Trakt an.

## Anpassung der Optik

Das Plugin verwendet CSS-Variablen für einfache Anpassungen:

```css
:root {
  --ns-primary-color: #2690ff;
  --ns-primary-light: rgba(38, 144, 255, 0.1);
  --ns-primary-border: rgba(38, 144, 255, 0.25);
  --ns-primary-hover: rgba(38, 144, 255, 0.75);
  --ns-text-color: #1a1a1a;
  --ns-text-muted: #666;
  --ns-border-radius: 5px;
  --ns-transition: 0.3s ease;
}

/* Dark Mode Support */
@media (prefers-color-scheme: dark) {
  :root {
    --ns-text-color: #f0f0f0;
    --ns-text-muted: #999;
  }
}
```

## Debugging

### Debug-Log aktivieren

1. Gehe zu "Einstellungen > NowScrobbling > Tab: Diagnostics"
2. Aktiviere "Debug-Log aktivieren"
3. Überprüfe das Debug-Log im Diagnostics-Tab

### Cache leeren

- Verwende den "Alle Caches leeren" Button im Diagnostics-Tab
- Oder nutze die REST API: `POST /wp-json/nowscrobbling/v1/cache/clear`

### API-Verbindungen testen

- Verwende den "API-Verbindungen testen" Button im Diagnostics-Tab
- Die Ergebnisse zeigen den Status beider APIs

## Migration von v1.3

Bei Aktivierung von v2.0 werden alle v1.3 Einstellungen automatisch migriert:
- API-Credentials
- Cache-Einstellungen
- Display-Präferenzen
- Debug-Optionen

Falls Probleme auftreten, kann die Migration im Diagnostics-Tab überprüft und bei Bedarf zurückgesetzt werden.

## Bekannte Einschränkungen

- **Multisite**: Nicht unterstützt (geplant für v2.1)
- **API-Limits**: Bei sehr hoher Last können API-Limits erreicht werden
- **JavaScript**: AJAX-Updates benötigen JavaScript (SSR funktioniert ohne)

## Entwicklung

### Voraussetzungen
- PHP 8.2+
- Composer
- WordPress 6.0+ Test-Umgebung

### Setup
```bash
git clone https://github.com/willrobin/NowScrobbling.git
cd NowScrobbling/nowscrobbling
composer install
```

### Tests ausführen
```bash
composer test
# oder
./vendor/bin/phpunit
```

### Code-Style prüfen
```bash
composer phpcs
# oder
./vendor/bin/phpcs
```

## Changelog

Siehe [CHANGELOG.md](CHANGELOG.md) für die vollständige Versionshistorie.

## Beitragen

1. Forke das Repository
2. Erstelle einen Feature-Branch (`git checkout -b feature/AmazingFeature`)
3. Committe deine Änderungen (`git commit -m 'Add some AmazingFeature'`)
4. Push zum Branch (`git push origin feature/AmazingFeature`)
5. Öffne einen Pull Request

Siehe [CONTRIBUTING.md](CONTRIBUTING.md) für detaillierte Richtlinien.

## Lizenz

Dieses Projekt ist unter der GPL v2 oder später lizenziert - siehe die [LICENSE](LICENSE) Datei für Details.

## Autor

**Robin Will** - [robinwill.de](https://robinwill.de/)

## Danksagungen

- Last.fm für die API
- Trakt.tv für die API
- WordPress Community für die Unterstützung
