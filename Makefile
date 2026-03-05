DC = docker compose -f docker-compose.local-ci.yml

.PHONY: ci-install ci-test ci-phpcs ci-phpcs-changed ci-check ci-all

ci-install:
	$(DC) run --rm php-ci sh -lc "composer install --no-interaction --prefer-dist"

ci-test:
	$(DC) run --rm php-ci sh -lc "composer install --no-interaction --prefer-dist && composer test"

ci-phpcs:
	$(DC) run --rm php-ci sh -lc "composer install --no-interaction --prefer-dist && composer phpcs"

ci-phpcs-changed:
	$(DC) run --rm php-ci sh -lc "composer install --no-interaction --prefer-dist && BASE_REF=$${BASE_REF:-github/main} sh ./bin/phpcs-changed.sh"

ci-check:
	$(DC) run --rm php-ci sh -lc "composer install --no-interaction --prefer-dist && composer test && BASE_REF=$${BASE_REF:-github/main} sh ./bin/phpcs-changed.sh"

ci-all: ci-check
