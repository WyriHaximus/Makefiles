migrations-renovate-move-config: #### Move renovate.json to .github/renovate.json ##*I*##
	($(DOCKER_RUN) mv renovate.json .github/renovate.json || true)

migrations-renovate-point-at-correct-config: #### Ensure .github/renovate.json points at github>WyriHaximus/renovate-config:php-package instead of local>WyriHaximus/renovate-config ##*I*##
	($(DOCKER_RUN) php -r '$$renovateFIle = ".github/renovate.json"; if (!file_exists($$renovateFIle)) {exit;} file_put_contents($$renovateFIle, str_replace("local>WyriHaximus/renovate-config", "github>WyriHaximus/renovate-config:php-package", file_get_contents($$renovateFIle)));' || true)
