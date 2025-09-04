on-install-or-update: ## Runs everything ####
	@grep -E '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) | grep -E "##\*(I|ILH)\*##" | awk 'BEGIN {FS = ":.*?## "}; {printf "%s\n", $$1}' | xargs -o $(MAKE)
	@grep -E '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) | grep -E "##\*(I|ILH)\*##" | awk 'BEGIN {FS = ":.*?## "}; {printf "%s\n", $$1}' | xargs -o $(MAKE)
