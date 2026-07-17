contrib: ## Runs a subset of everything ####
	$(DOCKER_RUN_WITH_SOCKET) make contrib-raw
contrib-raw: ## The real runs everything, but due to sponge it has to be ran inside DOCKER_RUN ##U##
	make-list(contrib)
