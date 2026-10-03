migrations-git-enforce-agents-md-contents: #### Enforce `AGENTS.md` contents ##*I*##
	($(DOCKER_RUN) php -r 'file_put_contents("AGENTS.md", base64_decode("base64(AGENTS-md)"));' || true)

migrations-ai-append-etc-agents-md-to-agents-md: #### Append `etc/AGENTS.md` to `AGENTS.md` when present ##*I*##
	($(DOCKER_RUN) php -r '$$etcAgentsFile = "etc/AGENTS.md"; $$agentsFile = "AGENTS.md"; if (!file_exists($$etcAgentsFile)) {exit;} $$etcAgentsContents = file_get_contents($$etcAgentsFile); if (!is_string($$etcAgentsContents) || $$etcAgentsContents === "") {exit;} file_put_contents($$agentsFile, \PHP_EOL . $$etcAgentsContents, FILE_APPEND);' || true)
