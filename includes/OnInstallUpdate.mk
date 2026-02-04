on-install-or-update: ## Tasks, like migrations, that specifically have be run after composer install or update. These will also run by self hosted Renovate ####
	$(DOCKER_RUN) make-list(on-install-or-update)
