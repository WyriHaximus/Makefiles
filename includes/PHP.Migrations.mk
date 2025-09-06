php-migrations-move-infection: #### Move infection.json.dist to etc/qa/infection.json5 ##*I*##
	($(DOCKER_RUN) mv infection.json.dist etc/qa/infection.json5 || true)

php-migrations-remove-phpunit-config-dir-from-infection: #### Drop XXX from etc/qa/infection.json5 ##*I*##
	($(DOCKER_RUN) php -r '$$infectionFile = "etc/qa/infection.json5"; if (!file_exists($$infectionFile)) {exit;} $$json = json_decode(file_get_contents($$infectionFile), true); if (!is_array($$json)) {exit;}  if (!array_key_exists("phpUnit", $$json)) {exit;} unset($$json["phpUnit"]); file_put_contents($$infectionFile, json_encode($$json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\r\n");' || true)

php-migrations-create-empty-placeholder-old-infection-config-file-path: #### Create empty infection.json.dist to fool Renovate ##*I*##
	($(DOCKER_RUN) touch infection.json.dist || true)
