migrations-dev-add-devcontainer: #### Enforce `.devcontainer.json` exists ##*I*##
	($(DOCKER_RUN) php -r '$$devContainerFile = ".devcontainer.json"; file_put_contents($$devContainerFile, json_encode(["name" => "dev-container", "image" => "${CONTAINER_NAME}",], \JSON_PRETTY_PRINT) . PHP_EOL);' || true)
