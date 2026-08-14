composer-validate: ## Ensure we don't require any package we don't use in this package directly ##*IC*##
	$(DOCKER_SHELL) composer validate

syntax-php: ## Lint PHP syntax ##*ILH*##
	$(DOCKER_RUN) vendor/bin/parallel-lint --exclude vendor .

composer-normalize: ## Normalize composer.json ##*I*##
	$(DOCKER_RUN) composer normalize
	$(MAKE) update-lock

rector-upgrade: ## Upgrade any automatically upgradable old code ##*I*##^code-style^##
	$(DOCKER_RUN) vendor/bin/rector -c ./etc/qa/rector.php

cs-fix: ## Fix any automatically fixable code style issues ##*EI*##^code-style^##
	$(DOCKER_RUN) vendor/bin/phpcbf --parallel=1 --cache=./var/.phpcs.cache.json --standard=./etc/qa/phpcs.xml || $(MAKE) cs

cs-fix-debug: ## Fix any automatically fixable code style issues, but with debugging output ####^code-style^##
	$(DOCKER_RUN) vendor/bin/phpcbf --parallel=1 --cache=./var/.phpcs.cache.json --standard=./etc/qa/phpcs.xml -vvvv

cs: ## Check the code for code style issues ##*ELCH*##^code-style^##
	$(DOCKER_SHELL) vendor/bin/phpcs --parallel=1 --cache=./var/.phpcs.cache.json --standard=./etc/qa/phpcs.xml

stan: ## Run static analysis (PHPStan) ##*LCH*##^static-analysis^##
	$(DOCKER_SHELL) vendor/bin/phpstan analyse --ansi --configuration=./etc/qa/phpstan.neon

unit-testing: ## Run tests ##*AE*##^unit-tests^##
	service_start(before-unit-tests-service)
	$(DOCKER_RUN_WITH_SOCKET) vendor/bin/phpunit --colors=always -c ./etc/qa/phpunit.xml --coverage-text --coverage-html ./var/phpunit/coverage --coverage-clover ./var/phpunit/coverage/clover.xml
	$(MAKE) coverage-guard
	service_cleanup(after-unit-tests-service)

unit-testing-filter: ## Run tests with specified filter ####^unit-tests^##
	service_start(before-unit-tests-service)
	$(DOCKER_RUN_WITH_SOCKET) vendor/bin/phpunit --colors=always --filter=$(wordlist 2,$(words $(MAKECMDGOALS)),$(MAKECMDGOALS)) -c ./etc/qa/phpunit.xml --coverage-text --coverage-html ./var/phpunit/coverage --coverage-clover ./var/phpunit/coverage/clover.xml
	service_cleanup(after-unit-tests-service)

unit-testing-raw: ## Run tests ##*D*##^unit-tests^##
	php vendor/phpunit/phpunit/phpunit --colors=always -c ./etc/qa/phpunit.xml --coverage-text --coverage-html ./var/phpunit/coverage --coverage-clover ./var/phpunit/coverage/clover.xml
	$(MAKE) coverage-guard-raw

coverage-guard: ## Enforce code coverage rules ####
	$(DOCKER_RUN) vendor/bin/coverage-guard check ./var/phpunit/coverage/clover.xml --config=./etc/qa/coverage-guard.php

coverage-guard-raw: ## Enforce code coverage rules ####
	php vendor/bin/coverage-guard check ./var/phpunit/coverage/clover.xml --config=./etc/qa/coverage-guard.php

mutation-testing: ## Run mutation testing ##*LCH*##^static-analysis|unit-tests^##
	service_start(before-unit-tests-service)
	$(DOCKER_RUN_WITH_SOCKET) vendor/bin/infection --ansi --log-verbosity=all --ignore-msi-with-no-mutations --configuration=./etc/qa/infection.json5 --static-analysis-tool=phpstan --static-analysis-tool-options="--memory-limit=-1" --threads=$(THREADS)
	service_cleanup(after-unit-tests-service)

mutation-testing-raw: ## Run mutation testing ####^static-analysis|unit-tests^##
	vendor/bin/infection --ansi --log-verbosity=all --ignore-msi-with-no-mutations --configuration=./etc/qa/infection.json5 --static-analysis-tool=phpstan --static-analysis-tool-options="--memory-limit=-1" --threads=$(THREADS)

composer-require-checker: ## Ensure we require every package used in this package directly ##*EC*##^composer-dependency-checkers^##
	$(DOCKER_SHELL) vendor/bin/composer-require-checker --ignore-parse-errors --ansi -vvv --config-file=./etc/qa/composer-require-checker.json

composer-unused: ## Ensure we don't require any package we don't use in this package directly ##*EC*##^composer-dependency-checkers^##
	$(DOCKER_SHELL) vendor/bin/composer-unused --ansi --configuration=./etc/qa/composer-unused.php

backward-compatibility-check: ## Check code for backwards incompatible changes ##*C*##
	$(MAKE) backward-compatibility-check-raw || true

backward-compatibility-check-raw: ## Check code for backwards incompatible changes, doesn't ignore the failure ###
	$(DOCKER_SHELL) vendor/bin/roave-backward-compatibility-check

install: ### Install dependencies ####
ifeq ("$(ON_INSTALL_OR_UPDATE_HAS_DIRECT_DOCKER_TASKS)","TRUE")
	$(DOCKER_SHELL) composer install --no-scripts
	$(MAKE) on-install-or-update
else
	$(DOCKER_SHELL) composer install
endif

composer-require: ### Require passed dependencies ####
	$(DOCKER_INTERACTIVE_SHELL) composer require -W $(wordlist 2,$(words $(MAKECMDGOALS)),$(MAKECMDGOALS))

composer-why: ### Show why a specific dependency is loaded ####
	$(DOCKER_INTERACTIVE_SHELL) composer why $(wordlist 2,$(words $(MAKECMDGOALS)),$(MAKECMDGOALS))

composer-outdated: ### Show outdated packages ####
	$(DOCKER_SHELL) composer outdated

update: ### Update dependencies ####
ifeq ("$(ON_INSTALL_OR_UPDATE_HAS_DIRECT_DOCKER_TASKS)","TRUE")
	$(DOCKER_SHELL) composer update -W --no-scripts
	$(MAKE) on-install-or-update
else
	$(DOCKER_SHELL) composer update -W
endif

update-lock: ### Update lockfile ####
	$(DOCKER_RUN_WITHOUT_NETWORK_FOR_COMPOSER) composer update --lock --no-scripts || $(DOCKER_RUN) composer update --lock --no-scripts

outdated: ### Show outdated dependencies ####
	$(DOCKER_SHELL) composer outdated

composer-show: ### Show dependencies ####
	$(DOCKER_SHELL) composer show
