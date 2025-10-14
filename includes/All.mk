all: ## Runs everything ####
	$(DOCKER_RUN) make all-raw
all-raw: ## The real runs everything, but due to sponge it has to be ran inside DOCKER_RUN ##U##
	((shell command -v sponge >/dev/null 2>&1) && (sh -c '$(shell printf "%s %s" $(MAKE) $(shell cat $(MAKEFILE_LIST) | grep -E '^[a-zA-Z0-9_-]+:.*?## .*$$' | grep -v "##*I*##" | grep -v "####" | grep -v "##U##" | awk 'BEGIN {FS = ":.*?## "}; {printf "%s\n", $$1}' | sponge | tr '\r\n' '_') | tr '_' ' ')') || (grep -E '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) | grep -v "##*I*##" | grep -v "####" | grep -v "##U##" | awk 'BEGIN {FS = ":.*?## "}; {printf "%s\n", $$1}' | xargs -o $(MAKE)))
