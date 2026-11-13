documentation-wordlist-sync: #### Sync etc/base64 cspell and vale vocab from etc/wordlist.txt ##*IE*##
	$(DOCKER_RUN) php -r 'require getcwd() . "/vendor/autoload.php"; \WyriHaximus\Makefiles\Documentation\WordlistDocumentationConfigSync::sync(getcwd());'

migrations-docs-update-agents-template-phpunit-version: #### Update PHPUnit version in AGENTS-md template dataproviders link ##*I*##
	($(DOCKER_RUN) php -r '$$agentsFile = "etc/base64/AGENTS-md"; if (!file_exists($$agentsFile)) {exit;} $$agentsContents = file_get_contents($$agentsFile); if (!is_string($$agentsContents)) {exit;} $$versionLine = trim(shell_exec("composer show phpunit/phpunit 2>/dev/null | grep versions")); if (!$$versionLine) {exit;} if (!preg_match("/^\s*versions\s*:\s*\*\s*(\d+)\./", $$versionLine, $$matches)) {exit;} $$majorVersion = $$matches[1]; $$agentsContents = preg_replace_callback("#(https://phpunit\.readthedocs\.io/en/)\d+\.0/writing-tests-for-phpunit\.html\#data-providers#", function($$m) use ($$majorVersion) { return $$m[1] . "$$majorVersion.0/writing-tests-for-phpunit.html#data-providers"; }, $$agentsContents); file_put_contents($$agentsFile, $$agentsContents);' || true)

after-renovate: ## Tasks to run after Renovate updates dependencies; ensuring Makefile is updated with the latest changes ####
	$(DOCKER_RUN) sh -ec 'GIT_CONFIG_COUNT=1 GIT_CONFIG_KEY_0=safe.directory GIT_CONFIG_VALUE_0="$$(pwd)" composer install --no-scripts && composer run-script pre-autoload-dump && make documentation-wordlist-sync'
