all: ## Runs everything ####
ifeq ("$(ALL_HAS_DIRECT_DOCKER_TASKS)","TRUE")
	$(MAKE) all-raw
else
	$(DOCKER_RUN_WITH_SOCKET) make all-raw
endif
all-raw: ## The real runs everything, but due to sponge it has to be ran inside DOCKER_RUN ##U##
	make-list(all)
