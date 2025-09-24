migrations-github-codeowners: #### Ensure a CODEOWNERS file is present, create only if it doesn't exist yet ##*I*##
	($(DOCKER_RUN) php -r '$$codeOwnersFile = ".github/CODEOWNERS"; if (file_exists($$codeOwnersFile)) {exit;} file_put_contents($$codeOwnersFile, "*       @WyriHaximus" . PHP_EOL);' || true)
