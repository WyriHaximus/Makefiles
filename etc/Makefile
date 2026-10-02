documentation-wordlist-sync: #### Sync etc/base64 cspell and vale vocab from etc/wordlist.txt ##*IE*##
	$(DOCKER_RUN) php -r 'require getcwd() . "/vendor/autoload.php"; \WyriHaximus\Makefiles\Documentation\WordlistDocumentationConfigSync::sync(getcwd());'
