contrib: ## Runs a subset of everything (all) ####
ifeq ("$(CONTRIB_HAS_DIRECT_DOCKER_TASKS)","TRUE")
	$(MAKE) contrib-raw
else
	$(DOCKER_RUN_WITH_SOCKET) make contrib-raw
endif
contrib-raw: ## The real runs everything, but due to sponge it has to be ran inside DOCKER_RUN ##U##
	make-list(contrib)
