# NowScrobbling WordPress Plugin

**Version 1.3.0** - Stabile, performante und saubere Version für Last.fm und Trakt.tv Integration

## 🎯 Übersicht

NowScrobbling ist ein WordPress-Plugin, das Last.fm und Trakt.tv Daten per Shortcode darstellt. Die Version 1.3.0 bringt erhebliche Performance-Verbesserungen, bessere Caching-Strategien und eine moderne Benutzeroberfläche.

## ✨ Neue Features in Version 1.3.0

### 🚀 Performance & UX
- **Server-Side Rendering (SSR)**: Inhalte werden sofort beim Laden aus dem Cache angezeigt
- **Intelligentes AJAX-Update**: Nur bei Änderungen im Inhalt wird der DOM ersetzt (Hash-Vergleich)
- **Sanfte DOM-Updates**: Kein Flackern beim Aktualisieren der Inhalte
- **Automatisches Polling**: "Now Playing"-Inhalte werden automatisch in festen Intervallen aktualisiert
- **Konditionales Laden**: CSS/JS werden nur geladen, wenn Shortcodes auf der Seite verwendet werden

### 🗄️ Caching & Hintergrund-Refresh
- **Smart Caching**: Kurze Aktualisierung für "Now Playing" (20-30s) und längere Intervalle für andere Inhalte
- **Cronjob-Integration**: Regelmäßige Cache-Aktualisierung im Hintergrund, auch ohne Besucher
- **ETag-Support**: Optional für Trakt-API, um Datenlast zu senken
- **Fallback-Caching**: Bei API-Fehlern werden letzte Cache-Stände angezeigt

### 🔒 Sicherheit & Code-Qualität
- **Striktes Sanitizing**: Alle Parameter in Shortcodes und AJAX-Requests werden validiert
- **Whitelist-System**: Nur erlaubte Shortcodes können per AJAX aufgerufen werden
- **Nonce-Protection**: Sicherheits-Tokens für alle AJAX-Endpunkte
- **Debug-Logging**: Optimiertes Logging-System (nur bei aktivierter Debug-Option)

### 🎨 Optik & Konsistenz
- **CSS-Variablen**: Einfache Anpassung der Optik über CSS-Variablen
- **Moderne Animationen**: Sanfte Übergänge und visuelle Feedback-Effekte
- **Responsive Design**: Optimiert für mobile Geräte
- **Dark Mode Support**: Automatische Anpassung an System-Präferenzen

## 📋 Anforderungen

- WordPress 5.0 oder höher
- PHP 7.0 oder höher
- Last.fm API-Schlüssel (optional)
- Trakt.tv Client ID (optional)

## 🚀 Installation

1. Lade das Plugin in den `/wp-content/plugins/nowscrobbling/` Ordner hoch
2. Aktiviere das Plugin über das WordPress Admin-Panel
3. Gehe zu "Einstellungen > NowScrobbling" und konfiguriere deine API-Schlüssel
4. Verwende die verfügbaren Shortcodes in deinen Beiträgen oder Seiten

## ⚙️ Konfiguration

### API-Schlüssel einrichten

1. **Last.fm API-Schlüssel**:
   - Besuche [Last.fm API](https://www.last.fm/api/account/create)
   - Erstelle eine neue API-Anwendung
   - Kopiere den API-Schlüssel in die Plugin-Einstellungen

2. **Trakt.tv Client ID**:
   - Besuche [Trakt.tv API](https://trakt.tv/oauth/applications/new)
   - Erstelle eine neue OAuth-Anwendung
   - Kopiere die Client ID in die Plugin-Einstellungen

### Cache-Einstellungen

- **Last.fm Cache-Dauer**: 1-60 Minuten (Standard: 1 Minute)
- **Trakt Cache-Dauer**: 1-60 Minuten (Standard: 5 Minuten)
- **Allgemeine Cache-Dauer**: 1-60 Minuten (Standard: 60 Minuten)

## 📝 Verfügbare Shortcodes

### Last.fm Shortcodes

#### `[nowscr_lastfm_indicator]`
Zeigt den aktuellen Status der Last.fm Aktivität an.

#### `[nowscr_lastfm_history max_length="45"]`
Zeigt die letzten Scrobbles von Last.fm an.
- `max_length`: Maximale Zeichenlänge (Standard: 45)

#### `[nowscr_lastfm_top_artists period="7day" max_length="15"]`
Zeigt die letzten Top-Künstler von Last.fm an.
- `period`: Zeitraum (7day, 1month, 3month, 6month, 12month, overall)
- `max_length`: Maximale Zeichenlänge (Standard: 15)

#### `[nowscr_lastfm_top_albums period="7day" max_length="45"]`
Zeigt die letzten Top-Alben von Last.fm an.
- `period`: Zeitraum (7day, 1month, 3month, 6month, 12month, overall)
- `max_length`: Maximale Zeichenlänge (Standard: 45)

#### `[nowscr_lastfm_top_tracks period="7day" max_length="45"]`
Zeigt die letzten Top-Titel von Last.fm an.
- `period`: Zeitraum (7day, 1month, 3month, 6month, 12month, overall)
- `max_length`: Maximale Zeichenlänge (Standard: 45)

#### `[nowscr_lastfm_lovedtracks max_length="45"]`
Zeigt die letzten Lieblingslieder von Last.fm an.
- `max_length`: Maximale Zeichenlänge (Standard: 45)

### Trakt.tv Shortcodes

#### `[nowscr_trakt_indicator]`
Zeigt den aktuellen Status der Trakt Aktivität an.

#### `[nowscr_trakt_history show_year="true" show_rating="true" show_rewatch="true"]`
Zeigt die letzten Aktivitäten von Trakt an.
- `show_year`: Jahr anzeigen (true/false)
- `show_rating`: Bewertung anzeigen (true/false)
- `show_rewatch`: Rewatch-Count anzeigen (true/false)

#### `[nowscr_trakt_last_movie show_year="true" show_rating="true" show_rewatch="true"]`
Zeigt die letzten Filme von Trakt an.
- `show_year`: Jahr anzeigen (true/false)
- `show_rating`: Bewertung anzeigen (true/false)
- `show_rewatch`: Rewatch-Count anzeigen (true/false)

#### `[nowscr_trakt_last_show show_year="true" show_rating="true" show_rewatch="true"]`
Zeigt die letzten Serien von Trakt an.
- `show_year`: Jahr anzeigen (true/false)
- `show_rating`: Bewertung anzeigen (true/false)
- `show_rewatch`: Rewatch-Count anzeigen (true/false)

#### `[nowscr_trakt_last_episode show_year="true" show_rating="true" show_rewatch="true"]`
Zeigt die letzten Episoden von Trakt an.
- `show_year`: Jahr anzeigen (true/false)
- `show_rating`: Bewertung anzeigen (true/false)
- `show_rewatch`: Rewatch-Count anzeigen (true/false)

## 🎨 Anpassung der Optik

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
```

## 🔧 Debugging

### Debug-Log aktivieren

1. Gehe zu "Einstellungen > NowScrobbling"
2. Aktiviere "Debug-Log aktivieren"
3. Überprüfe das Debug-Log in den Plugin-Einstellungen

### Cache leeren

- Verwende den "Alle Caches leeren" Button in den Plugin-Einstellungen
- Oder rufe `nowscrobbling_clear_all_caches()` in deinem Theme auf

### API-Verbindungen testen

- Verwende den "API-Verbindungen testen" Button in den Plugin-Einstellungen
- Überprüfe die Ergebnisse in der Status-Übersicht

## 🐛 Bekannte Probleme

- Bei sehr hoher Last können API-Limits erreicht werden
- Einige Themes können CSS-Konflikte verursachen
- Bei deaktiviertem JavaScript fallen AJAX-Updates aus

## 🔄 Changelog

### Version 1.3.0
- ✨ Server-Side Rendering (SSR) implementiert
- 🚀 Intelligentes Caching mit Fallback-System
- 🔒 Verbesserte Sicherheit mit Whitelist und Nonces
- 🎨 Moderne CSS-Variablen und Animationen
- 📱 Responsive Design und Dark Mode Support
- 🗄️ Cronjob-Integration für Hintergrund-Updates
- 🔧 Erweiterte Admin-Oberfläche mit Status-Übersicht
- 🐛 Bugfixes und Performance-Optimierungen

### Version 1.3.0
- Erste stabile Version
- Grundlegende Shortcode-Funktionalität
- AJAX-Updates
- Einfaches Caching

## 🤝 Beitragen

1. Forke das Repository
2. Erstelle einen Feature-Branch (`git checkout -b feature/AmazingFeature`)
3. Committe deine Änderungen (`git commit -m 'Add some AmazingFeature'`)
4. Push zum Branch (`git push origin feature/AmazingFeature`)
5. Öffne einen Pull Request

## 📄 Lizenz

Dieses Projekt ist unter der GPL v2 oder später lizenziert - siehe die [LICENSE](LICENSE) Datei für Details.

## 👨‍💻 Autor

**Robin Will** - [robinwill.de](https://robinwill.de/)

## 🙏 Danksagungen

- Last.fm für die API
- Trakt.tv für die API
- WordPress Community für die Unterstützung
