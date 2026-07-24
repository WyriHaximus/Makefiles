shell: ## Provides Shell access in the expected environment ####
	$(DOCKER_INTERACTIVE_SHELL) bash

run: ## Provides access in the expected environment to run a single command and then return ####
	$(DOCKER_RUN) $(wordlist 2,$(words $(MAKECMDGOALS)),$(MAKECMDGOALS))
