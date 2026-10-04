documentation-wordlist-sync: #### Sync etc/base64 cspell and vale vocab from etc/wordlist.txt ##*IE*##
	$(DOCKER_RUN) php -r 'require getcwd() . "/vendor/autoload.php"; \WyriHaximus\Makefiles\Documentation\WordlistDocumentationConfigSync::sync(getcwd());'

after-renovate: ## Tasks to run after Renovate updates dependencies; ensuring Makefile is updated with the latest changes ####
	$(DOCKER_RUN) composer run-script pre-autoload-dump
