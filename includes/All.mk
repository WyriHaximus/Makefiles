all: ## Runs everything ####
	@grep -E '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sed 's/[^:]*://' | grep -v "####" | awk 'BEGIN {FS = ":.*?## "}; {printf "%s\n", $$1}' | xargs --open-tty -I % bash -c 'cd $$PWD && $(MAKE) % || exit 255'
