all: ## Runs everything ####
	$(DOCKER_SHELL) make all-raw
all-raw: ## The real runs everything, but due to sponge it has to be ran inside DOCKER_RUN ##U##
	make-list(all)
