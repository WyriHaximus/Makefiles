documentation-wordlist-sync: #### Sync etc/base64 cspell and vale vocab from etc/wordlist.txt ##*IE*##
	$(DOCKER_RUN) php -r 'require getcwd() . "/vendor/autoload.php"; \WyriHaximus\Makefiles\Documentation\WordlistDocumentationConfigSync::sync(getcwd());'

after-renovate: ## Tasks to run after Renovate updates dependencies; ensuring Makefile is updated with the latest changes ####
	$(DOCKER_RUN) sh -ec 'GIT_CONFIG_COUNT=1 GIT_CONFIG_KEY_0=safe.directory GIT_CONFIG_VALUE_0="$$(pwd)" composer install --no-scripts && composer run-script pre-autoload-dump && make documentation-wordlist-sync'
