migrations-php-make-sure-etc-exists: #### Make sure etc/ exists ##*I*##
	($(DOCKER_RUN) mkdir etc || true)

migrations-php-make-sure-etc-ci-exists: #### Make sure etc/ci/ exists ##*I*##
	($(DOCKER_RUN) mkdir etc/ci || true)

migrations-php-make-sure-etc-qa-exists: #### Make sure etc/qa/ exists ##*I*##
	($(DOCKER_RUN) mkdir etc/qa || true)

migrations-php-remove-psalm-xml-config: #### Make sure we remove etc/qa/psalm.xml ##*I*##
	($(DOCKER_RUN) rm etc/qa/psalm.xml || true)

migrations-php-move-infection: #### Move infection.json.dist to etc/qa/infection.json5 ##*I*##
	($(DOCKER_RUN) mv infection.json.dist etc/qa/infection.json5 || true)

migrations-php-remove-phpunit-config-dir-from-infection: #### Drop XXX from etc/qa/infection.json5 ##*I*##
	($(DOCKER_RUN) php -r '$$infectionFile = "etc/qa/infection.json5"; if (!file_exists($$infectionFile)) {exit;} $$json = json_decode(file_get_contents($$infectionFile), true); if (!is_array($$json)) {exit;}  if (!array_key_exists("phpUnit", $$json)) {exit;} unset($$json["phpUnit"]); file_put_contents($$infectionFile, json_encode($$json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\r\n");' || true)

migrations-php-fix-logs-relative-paths-for-infection: #### Fix logs paths in etc/qa/infection.json5 ##*I*##
	($(DOCKER_RUN) php -r '$$infectionFile = "etc/qa/infection.json5"; if (!file_exists($$infectionFile)) {exit;} $$json = json_decode(file_get_contents($$infectionFile), true); if (!is_array($$json)) {exit;}  if (!array_key_exists("logs", $$json)) {exit;} foreach ($$json["logs"] as $$logsKey => $$logsPath) { if (is_string($$json["logs"][$$logsKey]) && str_starts_with($$json["logs"][$$logsKey], "./var/infection")) { $$json["logs"][$$logsKey] = str_replace("./var/infection", "../../var/infection", $$json["logs"][$$logsKey]); } } file_put_contents($$infectionFile, json_encode($$json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\r\n");' || true)

migrations-php-add-github-true-to-for-infection: #### Ensure we configure infection to emit logs to GitHub in etc/qa/infection.json5 ##*I*##
	($(DOCKER_RUN) php -r '$$infectionFile = "etc/qa/infection.json5"; if (!file_exists($$infectionFile)) {exit;} $$json = json_decode(file_get_contents($$infectionFile), true); if (!is_array($$json)) {exit;}  if (!array_key_exists("logs", $$json)) {exit;} if (array_key_exists("github", $$json["logs"])) {exit;} $$json["logs"]["github"] = true; file_put_contents($$infectionFile, json_encode($$json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\r\n");' || true)

migrations-php-set-phpunit-xsd-path-to-local: #### Ensure that the PHPUnit XDS referred in etc/qa/phpunit.xml points to vendor/phpunit/phpunit/phpunit.xsd so we don't go over the network ##*I*##
	($(DOCKER_RUN) php -r '$$phpUnitConfigFIle = "etc/qa/phpunit.xml"; if (!file_exists($$phpUnitConfigFIle)) {exit;} $$xml = file_get_contents($$phpUnitConfigFIle); if (!is_string($$xml)) {exit;} for ($$major = 0; $$major < 23; $$major++) { for ($$minor = 0; $$minor < 23; $$minor++) { $$xml = str_replace("https://schema.phpunit.de/" . $$major . "." . $$minor . "/phpunit.xsd", "../../vendor/phpunit/phpunit/phpunit.xsd", $$xml); } } file_put_contents($$phpUnitConfigFIle, $$xml);' || true)

migrations-php-set-phpstan-paths-in-config: #### Ensure PHPStan config has the etc, src, and tests paths set in etc/qa/phpstan.neon ##*I*##
	($(DOCKER_RUN) php -r '$$phpStanConfigFIle = "etc/qa/phpstan.neon"; $$pathsString = "\n\tpaths:\n\t\t- ../../etc\n\t\t- ../../src\n\t\t- ../../tests"; if (!file_exists($$phpStanConfigFIle)) {exit;} $$neon = file_get_contents($$phpStanConfigFIle); if (!is_string($$neon)) {exit;} if (strpos($$neon, $$pathsString) !== false) {exit;} $$neon = str_replace("parameters:", "parameters:" . $$pathsString, $$neon); file_put_contents($$phpStanConfigFIle, $$neon);' || true)

migrations-php-set-phpstan-level-max-in-config: #### Ensure PHPStan config has level set to max in etc/qa/phpstan.neon ##*I*##
	($(DOCKER_RUN) php -r '$$phpStanConfigFIle = "etc/qa/phpstan.neon"; $$levelString = "\n\tlevel: max"; if (!file_exists($$phpStanConfigFIle)) {exit;} $$neon = file_get_contents($$phpStanConfigFIle); if (!is_string($$neon)) {exit;} if (strpos($$neon, $$levelString) !== false) {exit;} $$neon = str_replace("parameters:", "parameters:" . $$levelString, $$neon); file_put_contents($$phpStanConfigFIle, $$neon);' || true)

migrations-php-set-phpstan-uncomment-parameters: #### Ensure PHPStan config as parameters not commented out in etc/qa/phpstan.neon ##*I*##
	($(DOCKER_RUN) php -r '$$phpStanConfigFIle = "etc/qa/phpstan.neon"; if (!file_exists($$phpStanConfigFIle)) {exit;} $$neon = file_get_contents($$phpStanConfigFIle); if (!is_string($$neon)) {exit;} if (!str_starts_with($$neon, "#parameters:")) {exit;} $$neon = str_replace("#parameters:", "parameters:", $$neon); file_put_contents($$phpStanConfigFIle, $$neon);' || true)

migrations-php-set-phpstan-resolve-ergebnis-noExtends-classesAllowedToBeExtended: #### Ensure PHPStan config uses ergebnis.noExtends.classesAllowedToBeExtended not ergebnis.classesAllowedToBeExtended ##*I*##
	($(DOCKER_RUN) php -r '$$phpStanConfigFIle = "etc/qa/phpstan.neon"; if (!file_exists($$phpStanConfigFIle)) {exit;} $$neon = file_get_contents($$phpStanConfigFIle); if (!is_string($$neon)) {exit;} $$neon = str_replace("\tergebnis:\n\t\tclassesAllowedToBeExtended:\n", "\tergebnis:\n\t\tnoExtends:\n\t\t\tclassesAllowedToBeExtended:\n", $$neon); file_put_contents($$phpStanConfigFIle, $$neon);' || true)

migrations-php-set-phpstan-drop-checkGenericClassInNonGenericObjectType: #### Ensure PHPStan config doesn't contain checkGenericClassInNonGenericObjectType as it's no longer a valid config option ##*I*##
	($(DOCKER_RUN) php -r '$$phpStanConfigFIle = "etc/qa/phpstan.neon"; if (!file_exists($$phpStanConfigFIle)) {exit;} $$neon = file_get_contents($$phpStanConfigFIle); if (!is_string($$neon)) {exit;} $$neon = str_replace("\tcheckGenericClassInNonGenericObjectType: false\n", "", $$neon); file_put_contents($$phpStanConfigFIle, $$neon);' || true)

migrations-php-set-phpstan-drop-include-test-utilities-rules: #### Ensure PHPStan config doesn't contain include for wyrihaximus/async-utilities/rules.neon as it's now an extension ##*I*##
	($(DOCKER_RUN) php -r '$$phpStanConfigFIle = "etc/qa/phpstan.neon"; if (!file_exists($$phpStanConfigFIle)) {exit;} $$neon = file_get_contents($$phpStanConfigFIle); if (!is_string($$neon)) {exit;} $$neon = str_replace("\nincludes:\n\t- ../../vendor/wyrihaximus/test-utilities/rules.neon\n", "", $$neon); file_put_contents($$phpStanConfigFIle, $$neon);' || true)

migrations-php-set-phpstan-drop-include-async-test-utilities-rules: #### Ensure PHPStan config doesn't contain include for wyrihaximus/async-test-utilities/rules.neon as it's now an extension ##*I*##
	($(DOCKER_RUN) php -r '$$phpStanConfigFIle = "etc/qa/phpstan.neon"; if (!file_exists($$phpStanConfigFIle)) {exit;} $$neon = file_get_contents($$phpStanConfigFIle); if (!is_string($$neon)) {exit;} $$neon = str_replace("\nincludes:\n\t- ../../vendor/wyrihaximus/async-test-utilities/rules.neon\n", "", $$neon); file_put_contents($$phpStanConfigFIle, $$neon);' || true)

migrations-php-set-rector-create-config-if-not-exists: #### Create Rector config file if it doesn't exists at etc/qa/rector.php ##*I*##
	($(DOCKER_RUN) php -r '$$rectorConfigFile = "etc/qa/rector.php"; $$defaultRectorConfig = "<?php declare(strict_types=1); use WyriHaximus\TestUtilities\RectorConfig; return RectorConfig::configure(dirname(__DIR__, 2));"; if (file_exists($$rectorConfigFile)) {exit;} file_put_contents($$rectorConfigFile, $$defaultRectorConfig);' || true)

migrations-php-composer-unused-create-config-if-not-exists: #### Create Composer Unused config file if it doesn't exists at etc/qa/composer-unused.php ##*I*##
	($(DOCKER_RUN) php -r '$$composerUnusedConfigFile = "etc/qa/composer-unused.php"; $$composerUnusedConfig = "<?php declare(strict_types=1); use ComposerUnused\ComposerUnused\Configuration\Configuration; return static function (Configuration \$$config): Configuration {return \$$config;};"; if (file_exists($$composerUnusedConfigFile)) {exit;} file_put_contents($$composerUnusedConfigFile, $$composerUnusedConfig);' || true)

migrations-php-make-sure-etc-is-ran-through-phpcs: #### Make sure PHPCS runs through etc ##*I*##
	($(DOCKER_RUN) php -r '$$phpcsConfigFile = "etc/qa/phpcs.xml"; if (!file_exists($$phpcsConfigFile)) {exit;} $$xml = file_get_contents($$phpcsConfigFile); if (!is_string($$xml)) {exit;} if (strpos($$xml, "<file>../../etc</file>") !== false) {exit;} $$xml = str_replace("<file>../../src</file>", "<file>../../etc</file>\n    <file>../../src</file>", $$xml); file_put_contents($$phpcsConfigFile, $$xml);' || true)
