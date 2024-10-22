syntax-php: ## Lint PHP syntax
	$(DOCKER_RUN) vendor/bin/parallel-lint --exclude vendor .

cs-fix: ## Fix any automatically fixable code style issues ###
	$(DOCKER_RUN) vendor/bin/phpcbf --parallel=$(THREADS) --cache=./var/.phpcs.cache.json --standard=./etc/qa/phpcs.xml || $(DOCKER_RUN) vendor/bin/phpcbf --parallel=$(THREADS) --cache=./var/.phpcs.cache.json --standard=./etc/qa/phpcs.xml || $(DOCKER_RUN) vendor/bin/phpcbf --parallel=$(THREADS) --cache=./var/.phpcs.cache.json --standard=./etc/qa/phpcs.xml -vvvv

cs: ## Check the code for code style issues
	$(DOCKER_RUN) vendor/bin/phpcs --parallel=$(THREADS) --cache=./var/.phpcs.cache.json --standard=./etc/qa/phpcs.xml

stan: ## Run static analysis (PHPStan)
	$(DOCKER_RUN) vendor/bin/phpstan analyse src tests --level max --ansi -c ./etc/qa/phpstan.neon

psalm: ## Run static analysis (Psalm)
	$(DOCKER_RUN) vendor/bin/psalm --threads=$(THREADS) --shepherd --stats --config=./etc/qa/psalm.xml

unit-testing: ## Run tests
	$(DOCKER_RUN) vendor/bin/phpunit --colors=always -c ./etc/qa/phpunit.xml --coverage-text --coverage-html ./var/tests-unit-coverage-html --coverage-clover ./var/tests-unit-clover-coverage.xml
	$(DOCKER_RUN) test -n "$(COVERALLS_REPO_TOKEN)" && test -n "$(COVERALLS_RUN_LOCALLY)" && test -f ./var/tests-unit-clover-coverage.xml && vendor/bin/php-coveralls -v --coverage_clover ./build/logs/clover.xml --json_path ./var/tests-unit-clover-coverage-upload.json || true

unit-testing-raw: ## Run tests ####
	php vendor/phpunit/phpunit/phpunit --colors=always -c ./etc/qa/phpunit.xml --coverage-text --coverage-html ./var/tests-unit-coverage-html --coverage-clover ./var/tests-unit-clover-coverage.xml
	test -n "$(COVERALLS_REPO_TOKEN)" && test -n "$(COVERALLS_RUN_LOCALLY)" && test -f ./var/tests-unit-clover-coverage.xml && ./vendor/bin/php-coveralls -v --coverage_clover ./build/logs/clover.xml --json_path ./var/tests-unit-clover-coverage-upload.json || true

mutation-testing: ## Run mutation testing
	$(DOCKER_RUN) vendor/bin/roave-infection-static-analysis-plugin --ansi --log-verbosity=all --threads=$(THREADS) --psalm-config etc/qa/psalm.xml || (cat ./var/infection.log && false)

mutation-testing-raw: ## Run mutation testing ####
	php vendor/roave/infection-static-analysis-plugin/bin/roave-infection-static-analysis-plugin --ansi --log-verbosity=all --threads=$(THREADS) --psalm-config etc/qa/psalm.xml || (cat ./var/infection.log && false)

composer-require-checker: ## Ensure we require every package used in this package directly
	$(DOCKER_RUN) vendor/bin/composer-require-checker --ignore-parse-errors --ansi -vvv --config-file=./etc/qa/composer-require-checker.json

composer-unused: ## Ensure we don't require any package we don't use in this package directly
	$(DOCKER_RUN) vendor/bin/composer-unused --ansi --configuration=./etc/qa/composer-unused.php

libyear: ## Calculate how many libyear this package is behind with dependencies
	$(DOCKER_RUN) vendor/bin/libyear

libyear-number: ## Calculate how many libyear this package is behind with dependencies - number only ####
	$(DOCKER_RUN) vendor/bin/libyear | tail -n1 | sed 's/[^0-9.]*//g'

backward-compatibility-check: ## Check code for backwards incompatible changes
	$(MAKE) backward-compatibility-check-raw || true

backward-compatibility-check-raw: ## Check code for backwards incompatible changes, doesn't ignore the failure ###
	$(DOCKER_RUN) vendor/bin/roave-backward-compatibility-check

install: ## Install dependencies ####
	$(DOCKER_RUN) composer install

update: ## Update dependencies ####
	$(DOCKER_RUN) composer update -W

outdated: ## Show outdated dependencies ####
	$(DOCKER_RUN) composer outdated
