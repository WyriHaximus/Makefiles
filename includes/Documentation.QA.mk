IMAGE_MARKDOWNLINT := davidanson/markdownlint-cli2:v0.17.2
IMAGE_LYCHEE := lycheeverse/lychee:0.24.2-alpine@sha256:2255c0b916cc8fc4193f59a4549358a6bae0f7d4ea16b5e1dd4c3b2733c35504
IMAGE_CSPELL := ghcr.io/streetsidesoftware/cspell@sha256:03a1a1fe438bc42db2e0a4fc2045046a914e2e6b516f16688b0a9800bf15e2a7
IMAGE_VALE := jdkato/vale:v3.23.0@sha256:d87d6355dc8992f92ec39c4c862a388e56e30302a771fd4512c02660fb25cdf3

documentation-markdownlint: ## Lint markdown structure ##*K*##
	$(DOCKER_RUN_DOCUMENTATION) $(IMAGE_MARKDOWNLINT) --config etc/qa/documentation.markdownlint-cli2.yaml

documentation-links: ## Check documentation links ##*K*##
	$(DOCKER_RUN_DOCUMENTATION) $(IMAGE_LYCHEE) --config etc/qa/lychee.toml .

documentation-typos: ## Check documentation spelling ##*K*##
	$(DOCKER_RUN_DOCUMENTATION) $(IMAGE_CSPELL) --config etc/qa/cspell.json --no-progress --no-must-find-files $$(find . -name "*.md" -not -path "./var/*" -not -path "./vendor/*" -not -path "./.git/*" -not -path "./.idea/*" -not -path "./etc/qa/vale/*")

documentation-vale: ## Lint documentation prose (Vale) ##*K*##
	$(DOCKER_RUN_DOCUMENTATION) $(IMAGE_VALE) sync --config etc/qa/vale.ini
	$(DOCKER_RUN_DOCUMENTATION) $(IMAGE_VALE) --config etc/qa/vale.ini --no-global README.md AGENTS.md CONTRIBUTING.md $$(find etc/ai -name '*.md' 2>/dev/null)

documentation-qa: documentation-markdownlint documentation-links documentation-typos documentation-vale ## Run all documentation QA checks ##*E*##
