task-list-ci-all: ## CI: Generate a JSON array of jobs to run on all variations
	task-list(ci-all)

task-list-ci-dos: ## CI: Generate a JSON array of jobs to run Directly on the OS variations
	task-list(ci-dos)

task-list-ci-low: ## CI: Generate a JSON array of jobs to run against the lowest dependencies on the primary threading target
	task-list(ci-low)

task-list-ci-locked: ## CI: Generate a JSON array of jobs to run against the locked dependencies on the primary threading target
	task-list(ci-locked)

task-list-ci-high: ## CI: Generate a JSON array of jobs to run against the highest dependencies on the primary threading target
	task-list(ci-high)
