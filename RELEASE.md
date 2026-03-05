# Release And Deploy Checklist

Stand: 5. Maerz 2026

## Ziel

Ein reproduzierbarer Ablauf fuer Release und Deploy mit lokalen, privaten Checks (OrbStack/Docker).

## Vorbereitung

1. Sicherstellen, dass der Arbeitsbranch aktuell ist.
2. Changelog/README/ROADMAP auf aktuellen Stand bringen.
3. Gewuenschte Version in `nowscrobbling/nowscrobbling.php` und `NowScrobbling\\Plugin::VERSION` setzen.

## Release-Checks

```bash
# aus dem Repo-Root
make release-check
```

Das beinhaltet aktuell:
- PHPUnit (`composer test` im Container)
- inkrementelles PHPCS fuer geaenderte Produktions-PHP-Dateien

## Commit Und Push

```bash
git add -A
git commit -m "chore: release vX.Y.Z"
git push github HEAD
```

## Tagging

```bash
git tag -a vX.Y.Z -m "Release vX.Y.Z"
git push github vX.Y.Z
```

## Deploy

Deploy wird ueber einen expliziten Entry-Point ausgefuehrt:

```bash
NS_DEPLOY_CMD='your-real-deploy-command' make deploy
```

Alternativ kann der Befehl einmalig in einer privaten lokalen Datei hinterlegt werden:

```bash
cp .deploy.env.example .deploy.env
# .deploy.env anpassen
make deploy
```

Hinweise:
- `make deploy` fuehrt zuerst `make release-check` aus.
- Ohne `NS_DEPLOY_CMD` (direkt oder aus `.deploy.env`) bricht der Deploy bewusst ab.
- Der reale Deploy-Befehl bleibt bewusst projektspezifisch und privat.

## Rollback

1. Vorherigen stabilen Tag identifizieren.
2. Falls erforderlich auf vorherigen Stand deployen.
3. Problem in `CHANGELOG.md` dokumentieren und Hotfix-Branch erstellen.
