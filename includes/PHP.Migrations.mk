migrations-php-make-sure-var-exists: #### Make sure var/ exists ##*I*##
	($(DOCKER_RUN) mkdir var || true)

migrations-php-make-sure-var-gitkeep-exists: #### Make sure var/.gitkeep exists ##*I*##
	($(DOCKER_RUN) touch var/.gitkeep || true)

migrations-php-make-sure-etc-exists: #### Make sure etc/ exists ##*I*##
	($(DOCKER_RUN) mkdir etc || true)

migrations-php-make-sure-etc-ci-exists: #### Make sure etc/ci/ exists ##*I*##
	($(DOCKER_RUN) mkdir etc/ci || true)

migrations-php-make-sure-etc-qa-exists: #### Make sure etc/qa/ exists ##*I*##
	($(DOCKER_RUN) mkdir etc/qa || true)

migrations-php-move-psalm-xml-config-to-etc: #### Move psalm.xml to etc/qa/psalm.xml ##*I*##
	($(DOCKER_RUN) mv psalm.xml etc/qa/psalm.xml || true)

migrations-php-remove-psalm-xml-config: #### Make sure we remove etc/qa/psalm.xml ##*I*##
	($(DOCKER_RUN) rm etc/qa/psalm.xml || true)

migrations-php-remove-old-phpunit-xml-dist-config: #### Make sure we remove phpunit.xml.dist ##*I*##
	($(DOCKER_RUN) rm phpunit.xml.dist || true)

migrations-php-ensure-etc-ci-markdown-link-checker-json-exists: #### Make sure we have etc/ci/markdown-link-checker.json ##*I*##
	($(DOCKER_RUN) php -r '$$markdownLinkCheckerFile = "etc/ci/markdown-link-checker.json"; $$json = json_decode("{\"httpHeaders\": [{\"urls\": [\"https://docs.github.com/\"],\"headers\": {\"Accept-Encoding\": \"zstd, br, gzip, deflate\"}}]}"); if (file_exists($$markdownLinkCheckerFile)) {exit;} file_put_contents($$markdownLinkCheckerFile, json_encode($$json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\r\n");' || true)

migrations-php-move-infection-config-to-etc: #### Move infection.json.dist to etc/qa/infection.json5 ##*I*##
	($(DOCKER_RUN) mv infection.json.dist etc/qa/infection.json5 || true)

migrations-php-infection-create-config-if-not-exists: #### Create Infection config file if it doesn't exists at etc/qa/infection.json5 ##*I*##
	($(DOCKER_RUN) php -r '$$infectionFile = "etc/qa/infection.json5"; $$infectionConfig = base64_decode("ewogICAgInRpbWVvdXQiOiAxMjAsCiAgICAic291cmNlIjogewogICAgICAgICJkaXJlY3RvcmllcyI6IFsKICAgICAgICAgICAgInNyYyIKICAgICAgICBdCiAgICB9LAogICAgImxvZ3MiOiB7CiAgICAgICAgInRleHQiOiAiLi4vLi4vdmFyL2luZmVjdGlvbi5sb2ciLAogICAgICAgICJzdW1tYXJ5IjogIi4uLy4uL3Zhci9pbmZlY3Rpb24tc3VtbWFyeS5sb2ciLAogICAgICAgICJqc29uIjogIi4uLy4uL3Zhci9pbmZlY3Rpb24uanNvbiIsCiAgICAgICAgInBlck11dGF0b3IiOiAiLi4vLi4vdmFyL2luZmVjdGlvbi1wZXItbXV0YXRvci5tZCIsCiAgICAgICAgImdpdGh1YiI6IHRydWUKICAgIH0sCiAgICAibWluTXNpIjogMTAwLAogICAgIm1pbkNvdmVyZWRNc2kiOiAxMDAsCiAgICAiaWdub3JlTXNpV2l0aE5vTXV0YXRpb25zIjogdHJ1ZSwKICAgICJtdXRhdG9ycyI6IHsKICAgICAgICAiQGRlZmF1bHQiOiB0cnVlCiAgICB9Cn0K"); if (file_exists($$infectionFile)) {exit;} file_put_contents($$infectionFile, $$infectionConfig);' || true)

migrations-php-remove-phpunit-config-dir-from-infection: #### Drop XXX from etc/qa/infection.json5 ##*I*##
	($(DOCKER_RUN) php -r '$$infectionFile = "etc/qa/infection.json5"; if (!file_exists($$infectionFile)) {exit;} $$json = json_decode(file_get_contents($$infectionFile), true); if (!is_array($$json)) {exit;}  if (!array_key_exists("phpUnit", $$json)) {exit;} unset($$json["phpUnit"]); file_put_contents($$infectionFile, json_encode($$json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\r\n");' || true)

migrations-php-fix-logs-relative-paths-for-infection: #### Fix logs paths in etc/qa/infection.json5 ##*I*##
	($(DOCKER_RUN) php -r '$$infectionFile = "etc/qa/infection.json5"; if (!file_exists($$infectionFile)) {exit;} $$json = json_decode(file_get_contents($$infectionFile), true); if (!is_array($$json)) {exit;}  if (!array_key_exists("logs", $$json)) {exit;} foreach ($$json["logs"] as $$logsKey => $$logsPath) { if (is_string($$json["logs"][$$logsKey]) && str_starts_with($$json["logs"][$$logsKey], "./var/infection")) { $$json["logs"][$$logsKey] = str_replace("./var/infection", "../../var/infection", $$json["logs"][$$logsKey]); } } file_put_contents($$infectionFile, json_encode($$json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\r\n");' || true)

migrations-php-add-github-true-to-for-infection: #### Ensure we configure infection to emit logs to GitHub in etc/qa/infection.json5 ##*I*##
	($(DOCKER_RUN) php -r '$$infectionFile = "etc/qa/infection.json5"; if (!file_exists($$infectionFile)) {exit;} $$json = json_decode(file_get_contents($$infectionFile), true); if (!is_array($$json)) {exit;}  if (!array_key_exists("logs", $$json)) {exit;} if (array_key_exists("github", $$json["logs"])) {exit;} $$json["logs"]["github"] = true; file_put_contents($$infectionFile, json_encode($$json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\r\n");' || true)

migrations-php-set-phpunit-ensure-config-file-exists: #### Make sure we have a PHPUnit config file at etc/qa/phpunit.xml ##*I*##
	($(DOCKER_RUN) php -r '$$phpUnitConfigFIle = "etc/qa/phpunit.xml"; if (file_exists($$phpUnitConfigFIle)) {exit;} file_put_contents($$phpUnitConfigFIle, base64_decode("PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KPHBocHVuaXQgeG1sbnM6eHNpPSJodHRwOi8vd3d3LnczLm9yZy8yMDAxL1hNTFNjaGVtYS1pbnN0YW5jZSIgYm9vdHN0cmFwPSIuLi8uLi92ZW5kb3IvYXV0b2xvYWQucGhwIiBjb2xvcnM9InRydWUiIHhzaTpub05hbWVzcGFjZVNjaGVtYUxvY2F0aW9uPSIuLi8uLi92ZW5kb3IvcGhwdW5pdC9waHB1bml0L3BocHVuaXQueHNkIiBjYWNoZURpcmVjdG9yeT0iLi4vLi4vdmFyL3BocHVuaXQvY2FjaGUiPgogICAgPHRlc3RzdWl0ZXM+CiAgICAgICAgPHRlc3RzdWl0ZSBuYW1lPSJUZXN0IFN1aXRlIj4KICAgICAgICAgICAgPGRpcmVjdG9yeT4uLi8uLi90ZXN0cy88L2RpcmVjdG9yeT4KICAgICAgICA8L3Rlc3RzdWl0ZT4KICAgIDwvdGVzdHN1aXRlcz4KICAgIDxzb3VyY2U+CiAgICAgICAgPGluY2x1ZGU+CiAgICAgICAgICAgIDxkaXJlY3Rvcnkgc3VmZml4PSIucGhwIj4uLi8uLi9zcmMvPC9kaXJlY3Rvcnk+CiAgICAgICAgPC9pbmNsdWRlPgogICAgPC9zb3VyY2U+CiAgICA8ZXh0ZW5zaW9ucz4KICAgICAgICA8Ym9vdHN0cmFwIGNsYXNzPSJFcmdlYm5pc1xQSFBVbml0XFNsb3dUZXN0RGV0ZWN0b3JcRXh0ZW5zaW9uIi8+CiAgICA8L2V4dGVuc2lvbnM+CjwvcGhwdW5pdD4K"));' || true)

migrations-php-set-phpunit-xsd-path-to-local: #### Ensure that the PHPUnit XDS referred in etc/qa/phpunit.xml points to vendor/phpunit/phpunit/phpunit.xsd so we don't go over the network ##*I*##
	($(DOCKER_RUN) php -r '$$phpUnitConfigFIle = "etc/qa/phpunit.xml"; if (!file_exists($$phpUnitConfigFIle)) {exit;} $$xml = file_get_contents($$phpUnitConfigFIle); if (!is_string($$xml)) {exit;} for ($$major = 0; $$major < 23; $$major++) { for ($$minor = 0; $$minor < 23; $$minor++) { $$xml = str_replace("https://schema.phpunit.de/" . $$major . "." . $$minor . "/phpunit.xsd", "../../vendor/phpunit/phpunit/phpunit.xsd", $$xml); } } file_put_contents($$phpUnitConfigFIle, $$xml);' || true)

migrations-php-move-phpstan: #### Move phpstan.neon to etc/qa/phpstan.neon ##*I*##
	($(DOCKER_RUN) mv phpstan.neon etc/qa/phpstan.neon || true)

migrations-php-set-phpstan-ensure-config-file-exists: #### Make sure we have a PHPStan config file at etc/qa/phpstan.neon ##*I*##
	($(DOCKER_RUN) php -r '$$phpStanConfigFIle = "etc/qa/phpstan.neon"; if (file_exists($$phpStanConfigFIle)) {exit;} file_put_contents($$phpStanConfigFIle, "#parameters:");' || true)

migrations-php-set-phpstan-uncomment-parameters: #### Ensure PHPStan config as parameters not commented out in etc/qa/phpstan.neon ##*I*##
	($(DOCKER_RUN) php -r '$$phpStanConfigFIle = "etc/qa/phpstan.neon"; if (!file_exists($$phpStanConfigFIle)) {exit;} $$neon = file_get_contents($$phpStanConfigFIle); if (!is_string($$neon)) {exit;} if (!str_starts_with($$neon, "#parameters:")) {exit;} $$neon = str_replace("#parameters:", "parameters:", $$neon); file_put_contents($$phpStanConfigFIle, $$neon);' || true)

migrations-php-set-phpstan-add-parameters-if-it-isnt-present-in-the-config-file: #### Add parameters to PHPStan config file at etc/qa/phpstan.neon if it's not present ##*I*##
	($(DOCKER_RUN) php -r '$$phpStanConfigFIle = "etc/qa/phpstan.neon"; if (!file_exists($$phpStanConfigFIle)) {exit;} $$neon = file_get_contents($$phpStanConfigFIle); if (!is_string($$neon)) {exit;} if (strpos($$neon, "parameters:") !== false) {exit;} file_put_contents($$phpStanConfigFIle, "parameters:", FILE_APPEND);' || true)

migrations-php-set-phpstan-paths-in-config: #### Ensure PHPStan config has the etc, src, and tests paths set in etc/qa/phpstan.neon ##*I*##
	($(DOCKER_RUN) php -r '$$phpStanConfigFIle = "etc/qa/phpstan.neon"; $$pathsString = "\n\tpaths:\n\t\t- ../../etc\n\t\t- ../../src\n\t\t- ../../tests"; if (!file_exists($$phpStanConfigFIle)) {exit;} $$neon = file_get_contents($$phpStanConfigFIle); if (!is_string($$neon)) {exit;} if (strpos($$neon, $$pathsString) !== false) {exit;} $$neon = str_replace("parameters:", "parameters:" . $$pathsString, $$neon); file_put_contents($$phpStanConfigFIle, $$neon);' || true)

migrations-php-set-phpstan-level-max-in-config: #### Ensure PHPStan config has level set to max in etc/qa/phpstan.neon ##*I*##
	($(DOCKER_RUN) php -r '$$phpStanConfigFIle = "etc/qa/phpstan.neon"; $$levelString = "\n\tlevel: max"; if (!file_exists($$phpStanConfigFIle)) {exit;} $$neon = file_get_contents($$phpStanConfigFIle); if (!is_string($$neon)) {exit;} if (strpos($$neon, $$levelString) !== false) {exit;} $$neon = str_replace("parameters:", "parameters:" . $$levelString, $$neon); file_put_contents($$phpStanConfigFIle, $$neon);' || true)

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

migrations-php-composer-unused-drop-commented-out-line-scattered-across-my-repos: #### Update Composer Unused config file dropping a commented out line that is scattered cross my repos ##*I*##
	($(DOCKER_RUN) php -r '$$composerUnusedConfigFile = "etc/qa/composer-unused.php"; if (!file_exists($$composerUnusedConfigFile)) {exit;} $$php = file_get_contents($$composerUnusedConfigFile); if (!is_string($$php)) {exit;} $$php = str_replace(base64_decode("Ly8gICAgICAgIC0+YWRkTmFtZWRGaWx0ZXIoTmFtZWRGaWx0ZXI6OmZyb21TdHJpbmcoJ3d5cmloYXhpbXVzL3BocHN0YW4tcnVsZXMtd3JhcHBlcicpKTs="), "", $$php); file_put_contents($$composerUnusedConfigFile, $$php);' || true)

migrations-php-move-phpcs: #### Move phpcs.xml.dist to etc/qa/phpcs.xml ##*I*##
	($(DOCKER_RUN) mv phpcs.xml.dist etc/qa/phpcs.xml || true)

migrations-php-set-phpcs-ensure-config-file-exists: #### Make sure we have a PHPCS config file at etc/qa/phpcs.xml ##*I*##
	($(DOCKER_RUN) php -r '$$phpcsConfigFile = "etc/qa/phpcs.xml"; if (file_exists($$phpcsConfigFile)) {exit;} file_put_contents($$phpcsConfigFile, base64_decode("PD94bWwgdmVyc2lvbj0iMS4wIj8+CjxydWxlc2V0PgogICAgPGFyZyBuYW1lPSJiYXNlcGF0aCIgdmFsdWU9Ii4uLy4uLyIgLz4KICAgIDxhcmcgbmFtZT0iZXh0ZW5zaW9ucyIgdmFsdWU9InBocCIgLz4gPCEtLSB3aGljaCBleHRlbnNpb25zIHRvIGxvb2sgZm9yIC0tPgogICAgPGFyZyBuYW1lPSJjb2xvcnMiIC8+CiAgICA8YXJnIG5hbWU9ImNhY2hlIiB2YWx1ZT0iLi4vLi4vdmFyLy5waHBjcy5jYWNoZSIgLz4gPCEtLSBjYWNoZSB0aGUgcmVzdWx0cyBhbmQgZG9uJ3QgY29tbWl0IHRoZW0gLS0+CiAgICA8YXJnIHZhbHVlPSJucCIgLz4gPCEtLSBuID0gaWdub3JlIHdhcm5pbmdzLCBwID0gc2hvdyBwcm9ncmVzcyAtLT4KCiAgICA8ZmlsZT4uLi8uLi9ldGM8L2ZpbGU+CiAgICA8ZmlsZT4uLi8uLi9zcmM8L2ZpbGU+CiAgICA8ZmlsZT4uLi8uLi90ZXN0czwvZmlsZT4KCiAgICA8cnVsZSByZWY9Ild5cmlIYXhpbXVzLU9TUyIgLz4KPC9ydWxlc2V0Pgo="));' || true)

migrations-php-phpcs-make-basepath-is-correct-relatively: #### Make sure PHPCS base path is has ../../ and not . ##*I*##
	($(DOCKER_RUN) php -r '$$phpcsConfigFile = "etc/qa/phpcs.xml"; if (!file_exists($$phpcsConfigFile)) {exit;} $$xml = file_get_contents($$phpcsConfigFile); if (!is_string($$xml)) {exit;} $$xml = str_replace("<arg name=\"basepath\" value=\".\" />", "<arg name=\"basepath\" value=\"../../\" />", $$xml); file_put_contents($$phpcsConfigFile, $$xml);' || true)

migrations-php-phpcs-make-cache-is-correct-relatively: #### Make sure PHPCS cache path is has ../../var/.phpcs.cache and not .phpcs.cache ##*I*##
	($(DOCKER_RUN) php -r '$$phpcsConfigFile = "etc/qa/phpcs.xml"; if (!file_exists($$phpcsConfigFile)) {exit;} $$xml = file_get_contents($$phpcsConfigFile); if (!is_string($$xml)) {exit;} $$xml = str_replace("<arg name=\"cache\" value=\".phpcs.cache\" />", "<arg name=\"cache\" value=\"../../var/.phpcs.cache\" />", $$xml); file_put_contents($$phpcsConfigFile, $$xml);' || true)

migrations-php-phpcs-make-sure-config-has-correct-relative-path-for-etc: #### Make sure PHPCS has ../../ prefixing etc/ to ensure correct relative path ##*I*##
	($(DOCKER_RUN) php -r '$$phpcsConfigFile = "etc/qa/phpcs.xml"; if (!file_exists($$phpcsConfigFile)) {exit;} $$xml = file_get_contents($$phpcsConfigFile); if (!is_string($$xml)) {exit;} $$xml = str_replace("<file>etc</file>", "<file>../../etc/</file>", $$xml); file_put_contents($$phpcsConfigFile, $$xml);' || true)

migrations-php-phpcs-make-sure-etc-has-no-trailing-slash: #### Make sure PHPCS has no tailing / on etc ##*I*##
	($(DOCKER_RUN) php -r '$$phpcsConfigFile = "etc/qa/phpcs.xml"; if (!file_exists($$phpcsConfigFile)) {exit;} $$xml = file_get_contents($$phpcsConfigFile); if (!is_string($$xml)) {exit;} $$xml = str_replace("<file>../../etc/</file>", "<file>../../etc</file>", $$xml); file_put_contents($$phpcsConfigFile, $$xml);' || true)

migrations-php-phpcs-make-sure-config-has-correct-relative-path-for-src: #### Make sure PHPCS has ../../ prefixing src/ to ensure correct relative path ##*I*##
	($(DOCKER_RUN) php -r '$$phpcsConfigFile = "etc/qa/phpcs.xml"; if (!file_exists($$phpcsConfigFile)) {exit;} $$xml = file_get_contents($$phpcsConfigFile); if (!is_string($$xml)) {exit;} $$xml = str_replace("<file>src</file>", "<file>../../src/</file>", $$xml); file_put_contents($$phpcsConfigFile, $$xml);' || true)

migrations-php-phpcs-make-sure-src-has-no-trailing-slash: #### Make sure PHPCS has no tailing / on src ##*I*##
	($(DOCKER_RUN) php -r '$$phpcsConfigFile = "etc/qa/phpcs.xml"; if (!file_exists($$phpcsConfigFile)) {exit;} $$xml = file_get_contents($$phpcsConfigFile); if (!is_string($$xml)) {exit;} $$xml = str_replace("<file>../../src/</file>", "<file>../../src</file>", $$xml); file_put_contents($$phpcsConfigFile, $$xml);' || true)

migrations-php-phpcs-make-sure-config-has-correct-relative-path-for-tests: #### Make sure PHPCS has ../../ prefixing tests/ to ensure correct relative path ##*I*##
	($(DOCKER_RUN) php -r '$$phpcsConfigFile = "etc/qa/phpcs.xml"; if (!file_exists($$phpcsConfigFile)) {exit;} $$xml = file_get_contents($$phpcsConfigFile); if (!is_string($$xml)) {exit;} $$xml = str_replace("<file>tests</file>", "<file>../../tests/</file>", $$xml); file_put_contents($$phpcsConfigFile, $$xml);' || true)

migrations-php-phpcs-make-sure-tests-has-no-trailing-slash: #### Make sure PHPCS has no tailing / on tests ##*I*##
	($(DOCKER_RUN) php -r '$$phpcsConfigFile = "etc/qa/phpcs.xml"; if (!file_exists($$phpcsConfigFile)) {exit;} $$xml = file_get_contents($$phpcsConfigFile); if (!is_string($$xml)) {exit;} $$xml = str_replace("<file>../../tests/</file>", "<file>../../tests</file>", $$xml); file_put_contents($$phpcsConfigFile, $$xml);' || true)

migrations-php-phpcs-make-sure-etc-is-ran-through: #### Make sure PHPCS runs through etc ##*I*##
	($(DOCKER_RUN) php -r '$$phpcsConfigFile = "etc/qa/phpcs.xml"; if (!file_exists($$phpcsConfigFile)) {exit;} $$xml = file_get_contents($$phpcsConfigFile); if (!is_string($$xml)) {exit;} if (strpos($$xml, "<file>../../etc</file>") !== false) {exit;} $$xml = str_replace("<file>../../src</file>", "<file>../../etc</file>\n    <file>../../src</file>", $$xml); file_put_contents($$phpcsConfigFile, $$xml);' || true)

migrations-php-move-composer-require-checker: #### Move composer-require-checker.json to etc/qa/composer-require-checker.json ##*I*##
	($(DOCKER_RUN) mv composer-require-checker.json etc/qa/composer-require-checker.json || true)

migrations-php-composer-require-checker-create-config-if-not-exists: #### Create Composer Require Checker config file if it doesn't exists at etc/qa/composer-require-checker.json ##*I*##
	($(DOCKER_RUN) php -r '$$composerRequireCheckerConfigFile = "etc/qa/composer-require-checker.json"; $$composerRequireCheckerConfig = base64_decode("ewogICJzeW1ib2wtd2hpdGVsaXN0IiA6IFsKICAgICJudWxsIiwgInRydWUiLCAiZmFsc2UiLAogICAgInN0YXRpYyIsICJzZWxmIiwgInBhcmVudCIsCiAgICAiYXJyYXkiLCAic3RyaW5nIiwgImludCIsICJmbG9hdCIsICJib29sIiwgIml0ZXJhYmxlIiwgImNhbGxhYmxlIiwgInZvaWQiLCAib2JqZWN0IgogIF0sCiAgInBocC1jb3JlLWV4dGVuc2lvbnMiIDogWwogICAgIkNvcmUiLAogICAgImRhdGUiLAogICAgInBjcmUiLAogICAgIlBoYXIiLAogICAgIlJlZmxlY3Rpb24iLAogICAgIlNQTCIsCiAgICAic3RhbmRhcmQiCiAgXSwKICAic2Nhbi1maWxlcyIgOiBbXQp9Cg=="); if (file_exists($$composerRequireCheckerConfigFile)) {exit;} file_put_contents($$composerRequireCheckerConfigFile, $$composerRequireCheckerConfig);' || true)

migrations-php-make-sure-gitignore-exists: #### Make sure .gitignore ##*I*##
	($(DOCKER_RUN) touch .gitignore || true)

migrations-php-make-sure-gitignore-ignores-var: #### Make sure .gitignore ignores var/* ##*I*##
	($(DOCKER_RUN) php -r '$$gitignoreFile = ".gitignore"; if (!file_exists($$gitignoreFile)) {exit;} $$txt = file_get_contents($$gitignoreFile); if (!is_string($$txt)) {exit;} if (strpos($$txt, "var/*") !== false) {exit;} file_put_contents($$gitignoreFile, "var/*\n", FILE_APPEND);' || true)

migrations-php-make-sure-gitignore-excludes-var-gitkeep: #### Make sure .gitignore excludes var/.gitkeep ##*I*##
	($(DOCKER_RUN) php -r '$$gitignoreFile = ".gitignore"; if (!file_exists($$gitignoreFile)) {exit;} $$txt = file_get_contents($$gitignoreFile); if (!is_string($$txt)) {exit;} if (strpos($$txt, "!var/.gitkeep") !== false) {exit;} file_put_contents($$gitignoreFile, "!var/.gitkeep\n", FILE_APPEND);' || true)
