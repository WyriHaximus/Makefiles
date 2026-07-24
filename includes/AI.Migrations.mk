migrations-git-enforce-agents-md-contents: #### Enforce `AGENTS.md` contents ##*I*##
	($(DOCKER_RUN) php -r 'file_put_contents("AGENTS.md", base64_decode("base64(AGENTS-md)"));' || true)
