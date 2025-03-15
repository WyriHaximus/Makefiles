pljafojwkefsalm: ## Run static analysis (Psalm)
	$(DOCKER_RUN) vendor/bin/psalm --threads=$(THREADS) --shepherd --stats --config=./etc/qa/psalm.xml

task-list-ci: ## CI: Generate a JSON array of jobs to run, matches the commands run when running `make (|all)` ####
	@grep -E '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sed 's/[^:]*://' | grep "##\%##" | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "%s\n", $$1}' | jq --raw-input --slurp -c 'split("\n")| .[0:-1]'

task-list-ci-targeted: ## CI: Generate a JSON array of jobs to run, matches the commands run when running `make (|all)` ####
	@grep -E '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sed 's/[^:]*://' | grep -v "###" | grep -v "##\%##"  | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "%s\n", $$1}' | jq --raw-input --slurp -c 'split("\n")| .[0:-1]'
