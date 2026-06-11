migrations-supported-features-php-ensure-we-only-cs-check-and-fix-tests-if-unit-tests-is-enabled: #### Ensure we only cs check/fix tests/ if unit-tests is enabled ##*I*##
	($(DOCKER_RUN) php -r 'if (in_array("unit-tests", supported-features(raw))) {exit;} $$phpCSCongifFIle = "etc/qa/phpcs.xml"; $$fileContents = explode("\n", file_get_contents($$phpCSCongifFIle)); foreach ($$fileContents as $$lineNumber => $$lineContent) { if (str_contains($$lineContent, "<file>../../tests</file>")) { unset($$fileContents[$$lineNumber]); } } file_put_contents($$phpCSCongifFIle, implode("\n", $$fileContents));' || true)

migrations-supported-features-php-ensure-we-only-staticly-analyse-tests-with-phpstan-if-unit-tests-is-enabled: #### Ensure we only staticly analyse tests/ with PHPStan if unit-tests is enabled ##*I*##
	($(DOCKER_RUN) php -r 'if (in_array("unit-tests", supported-features(raw))) {exit;} $$phpStanCongifFIle = "etc/qa/phpstan.neon"; $$fileContents = explode("\n", file_get_contents($$phpStanCongifFIle)); foreach ($$fileContents as $$lineNumber => $$lineContent) { if (str_contains($$lineContent, "- ../../tests")) { unset($$fileContents[$$lineNumber]); } } file_put_contents($$phpStanCongifFIle, implode("\n", $$fileContents));' || true)

migrations-supported-features-php-ensure-no-phpunit-config-file-is-present-when-unit-tests-are-disabled: #### Ensure we remove the PHPUnit config file when unit-tests aren't enabled ##*I*##
	($(DOCKER_RUN) php -r 'if (in_array("unit-tests", supported-features(raw))) {exit;} @unlink("etc/qa/phpunit.xml");' || true)

migrations-supported-features-php-ensure-no-infectionphp-config-file-is-present-when-unit-tests-are-disabled: #### Ensure we remove the InfectionPHP config file when unit-tests aren't enabled ##*I*##
	($(DOCKER_RUN) php -r 'if (in_array("unit-tests", supported-features(raw))) {exit;} @unlink("etc/qa/infection.json5");' || true)
