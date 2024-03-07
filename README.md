# Now Playing WordPress Plugin

Das "Now Playing" Plugin ermöglicht die Verwaltung von API-Einstellungen für last.fm und trakt.tv und zeigt deren letzte Aktivitäten an. Es ist eine einfache Lösung, um Musik- und TV-Aktivitäten auf Ihrer WordPress-Seite zu integrieren.

## Funktionen

- Integration mit Last.fm und Trakt.tv, um die letzten Scrobbles bzw. Aktivitäten zu zeigen.
- Einfache Verwaltung von API-Schlüsseln und Benutzernamen direkt im WordPress-Admin-Bereich.
- Anpassbare Einstellungen für die Anzahl der anzuzeigenden Top-Titel, Künstler, Alben, Obsessionen und Scrobbles.
- Steuerung der Cache-Dauer, um die Belastung der APIs zu verringern.
- Verfügbarkeit von Shortcodes, um die Daten flexibel auf Beiträgen, Seiten oder Widgets einzubinden.

## Installation

1. Laden Sie den Plugin-Ordner in das Verzeichnis `/wp-content/plugins/` Ihrer WordPress-Installation hoch.
2. Aktivieren Sie das Plugin über das 'Plugins' Menü in WordPress.

## Konfiguration

Nach der Aktivierung finden Sie die Einstellungsseite unter `Einstellungen > Now Playing`. Dort können Sie Ihre Last.fm und Trakt.tv API-Schlüssel sowie Benutzernamen eingeben und weitere Einstellungen vornehmen.

## Verwendung

Das Plugin stellt mehrere Shortcodes zur Verfügung, die Sie in Ihren Beiträgen, Seiten oder Widgets verwenden können:

- `[lastfm_scrobbles]`: Zeigt die letzten Scrobbles von Last.fm an.
- `[trakt_activities]`: Zeigt die letzten Aktivitäten von Trakt.tv an.
- `[lastfm_last_activity]`: Zeigt die Zeit der letzten Last.fm Aktivität an oder "NOW PLAYING".
- `[trakt_last_activity]`: Zeigt die Zeit der letzten Trakt.tv Aktivität an.
- `[top_tracks]`: Zeigt die Top-Titel von Last.fm an.
- `[top_artists]`: Zeigt die Top-Künstler von Last.fm an.
- `[top_albums]`: Zeigt die Top-Alben von Last.fm an.
- `[obsessions]`: Zeigt die Obsessionen von Last.fm an.
- `[scrobbles]`: Zeigt die Scrobbles von Last.fm an.

## Anpassung

Das Aussehen der angezeigten Daten kann über CSS angepasst werden. Die entsprechenden Klassen sind im Quellcode dokumentiert.

## Support

Für Support-Anfragen besuchen Sie bitte [https://robinwill.de](https://robinwill.de).

## Lizenz

Das Plugin ist lizenziert unter der GPL v2 oder später.
