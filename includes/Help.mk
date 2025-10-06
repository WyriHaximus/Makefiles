help: ## Show this help ####
	@printf "\033[33mUsage:\033[0m\n  make [target]\n\n\033[33mTargets:\033[0m\n"
	@grep -E '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) | grep -v "##U##" | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[32m%-32s\033[0m %s\n", $$1, $$2}' | tr -d '#'
