on-install-or-update: ## Tasks, like migrations, that specifically have be run after composer install or update. These will also run by self hosted Renovate ####
ifeq ("$(ON_INSTALL_OR_UPDATE_HAS_DIRECT_DOCKER_TASKS)","TRUE")
	$(DOCKER_RUN_WITH_SOCKET) make-list(on-install-or-update)
else
	$(DOCKER_RUN_WITH_SOCKET) make-list(on-install-or-update)
endif
