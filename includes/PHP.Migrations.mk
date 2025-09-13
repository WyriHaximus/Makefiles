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
