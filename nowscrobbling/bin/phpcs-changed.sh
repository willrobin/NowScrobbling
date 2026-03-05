#!/bin/sh
set -eu

BASE_REF="${BASE_REF:-github/main}"

collect_files() {
    if git rev-parse --verify "${BASE_REF}" >/dev/null 2>&1; then
        git diff --name-only --diff-filter=ACMR --relative "${BASE_REF}...HEAD" -- '*.php' || true
    fi

    git diff --name-only --diff-filter=ACMR --relative -- '*.php' || true
    git diff --cached --name-only --diff-filter=ACMR --relative -- '*.php' || true
    git ls-files --others --exclude-standard -- '*.php' || true
}

FILES="$(
    collect_files \
        | awk 'NF' \
        | grep -E '^(src/.*\.php|nowscrobbling\.php|uninstall\.php)$' \
        | sort -u || true
)"

if [ -z "${FILES}" ]; then
    echo "No changed production PHP files to lint."
    exit 0
fi

echo "Running PHPCS on changed files:"
echo "${FILES}"

./vendor/bin/phpcs --standard=.phpcs.xml ${FILES}
