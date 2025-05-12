# 🛠️ Technische Notizen

- Last.fm API benötigt `user`, `api_key` → ist GET-basiert
- Rate Limit beachten: 5 Anfragen/Sekunde
- WP Shortcodes mit `add_shortcode()`, Ausgabe über `ob_start()`
- Trakt.tv hat OAuth 2.0 → mehr Aufwand → nur optional

## Stolperfallen
- Kein JS in Shortcode-Ausgabe
- Nur Cache, wenn Daten konsistent

## Code-Stil
- WordPress Coding Standards (Klammern, Tabs, Kommentare)
- Funktionen in `includes/` gliedern