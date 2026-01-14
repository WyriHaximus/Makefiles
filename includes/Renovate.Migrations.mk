migrations-renovate-remove-dependabot-config: #### Make sure we remove .github/dependabot.yml ##*I*##
	($(DOCKER_RUN) rm .github/dependabot.yml || true)
	($(DOCKER_RUN) rm .github/dependabot.yaml || true)

migrations-renovate-move-config: #### Move renovate.json to .github/renovate.json ##*I*##
	($(DOCKER_RUN) mv renovate.json .github/renovate.json || true)

migrations-renovate-create-config-if-not-exists: #### Create Renovate Config if it doesn't exists at .github/renovate.json ##*I*##
	($(DOCKER_RUN) php -r '$$renovateConfigFile = ".github/renovate.json"; $$renovateConfigContents = base64_decode("ewogICIkc2NoZW1hIjogImh0dHBzOi8vZG9jcy5yZW5vdmF0ZWJvdC5jb20vcmVub3ZhdGUtc2NoZW1hLmpzb24iLAogICJleHRlbmRzIjogWwogICAgImdpdGh1Yj5XeXJpSGF4aW11cy9yZW5vdmF0ZS1jb25maWc6cGhwLXBhY2thZ2UiCiAgXQp9Cg=="); if (file_exists($$renovateConfigFile)) {exit;} file_put_contents($$renovateConfigFile, $$renovateConfigContents);' || true)

migrations-renovate-point-at-correct-config: #### Ensure .github/renovate.json points at github>WyriHaximus/renovate-config:php-package instead of local>WyriHaximus/renovate-config ##*I*##
	($(DOCKER_RUN) php -r '$$renovateFIle = ".github/renovate.json"; if (!file_exists($$renovateFIle)) {exit;} file_put_contents($$renovateFIle, str_replace("local>WyriHaximus/renovate-config", "github>WyriHaximus/renovate-config:php-package", file_get_contents($$renovateFIle)));' || true)

migration-renovate-set-php-constraint: #### Always keep renovate's constraints.php in sync with composer.json's config.platform.php ##*I*##
	($(DOCKER_RUN) php -r '$$composerFIle = "composer.json"; if (!file_exists($$composerFIle)) {exit;} $$json = json_decode(file_get_contents($$composerFIle), true); if (!array_key_exists("config", $$json)) {exit;} if (!array_key_exists("platform", $$json["config"])) {exit;} if (!array_key_exists("php", $$json["config"]["platform"])) {exit;} $$phpVersionConstraint = str_replace(".13", ".x", $$json["config"]["platform"]["php"]); $$renovateFIle = ".github/renovate.json"; if (!file_exists($$renovateFIle)) {exit;} $$json = json_decode(file_get_contents($$renovateFIle), true); if (!is_array($$json)) {exit;}  if (!array_key_exists("constraints", $$json)) {$$json["constraints"] = [];} $$json["constraints"]["php"] = $$phpVersionConstraint; file_put_contents($$renovateFIle, json_encode($$json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\r\n");' || true)
