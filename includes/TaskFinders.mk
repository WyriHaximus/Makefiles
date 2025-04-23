task-list-ci: ## CI: Generate a JSON array of jobs to run, matches the commands run when running `make (|all)` ####
	@grep -E '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) | grep -v "###" | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "%s\n", $$1}' | jq --raw-input --slurp -c 'split("\n")| .[0:-1]'

task-list-ci-all: ## CI: Generate a JSON array of jobs to run, matches the commands run when running `make (|all)` ####
	@grep -E '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) | grep -E "##\*A\*##" | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "%s\n", $$1}' | jq --raw-input --slurp -c 'split("\n")| .[0:-1]'

task-list-ci-low: ## CI: Generate a JSON array of jobs to run, matches the commands run when running `make (|all)` ####
	@grep -E '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) | grep -E "##\*[L]{1,3}\*##" | grep -v "###" | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "%s\n", $$1}' | jq --raw-input --slurp -c 'split("\n")| .[0:-1]'

task-list-ci-locked: ## CI: Generate a JSON array of jobs to run, matches the commands run when running `make (|all)` ####
	@grep -E '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) | grep -E "##\*[C]{1,3}\*##" | grep -v "###" | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "%s\n", $$1}' | jq --raw-input --slurp -c 'split("\n")| .[0:-1]'

task-list-ci-high: ## CI: Generate a JSON array of jobs to run, matches the commands run when running `make (|all)` ####
	@grep -E '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) | grep -E "##\*[H]{1,3}\*##" | grep -v "###" | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "%s\n", $$1}' | jq --raw-input --slurp -c 'split("\n")| .[0:-1]'

#task-list-ci: ## CI: Generate a JSON array of jobs to run, matches the commands run when running `make (|all)` ####
#	@grep -E '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sed 's/[^:]*://' | grep "##\%##" | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "%s\n", $$1}' | jq --raw-input --slurp -c 'split("\n")| .[0:-1]'
#
#task-list-ci-targeted: ## CI: Generate a JSON array of jobs to run, matches the commands run when running `make (|all)` ####
#	@grep -E '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sed 's/[^:]*://' | grep -v "###" | grep -v "##\%##"  | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "%s\n", $$1}' | jq --raw-input --slurp -c 'split("\n")| .[0:-1]'
