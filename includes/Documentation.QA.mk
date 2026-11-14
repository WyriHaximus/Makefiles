IMAGE_MARKDOWNLINT := davidanson/markdownlint-cli2:v0.17.1
IMAGE_LYCHEE := lycheeverse/lychee:0.24.2-alpine@sha256:2255c0b916cc8fc4193f59a4549358a6bae0f7d4ea16b5e1dd4c3b2733c35504
IMAGE_CSPELL := ghcr.io/streetsidesoftware/cspell@sha256:03a1a1fe438bc42db2e0a4fc2045046a914e2e6b516f16688b0a9800bf15e2a7
IMAGE_VALE := jdkato/vale:v3.23.0@sha256:d87d6355dc8992f92ec39c4c862a388e56e30302a771fd4512c02660fb25cdf3
DOC_MD_FILES := $(shell find . -name "*.md" -not -name "LICENSE" -not -path "./var/*" -not -path "./vendor/*" -not -path "./.*/*" -not -path "./etc/qa/vale/*")

documentation-markdownlint: ## Lint markdown structure ##*K*##
	$(DOCKER_RUN_DOCUMENTATION) $(IMAGE_MARKDOWNLINT) --config etc/qa/documentation.markdownlint-cli2.yaml

documentation-links: ## Check documentation links ##*K*##
	$(DOCKER_RUN_DOCUMENTATION) $(IMAGE_LYCHEE) --config etc/qa/lychee.toml .

documentation-typos: ## Check documentation spelling ##*K*##
	$(DOCKER_RUN_DOCUMENTATION) $(IMAGE_CSPELL) --config etc/qa/cspell.json --no-progress --no-must-find-files $(DOC_MD_FILES)

documentation-vale-sync: #### Sync Vale style packages ##*I*##
	$(DOCKER_RUN_DOCUMENTATION) $(IMAGE_VALE) sync --config etc/qa/vale.ini

documentation-vale: ## Lint documentation prose (Vale) ##*K*##
	$(DOCKER_RUN_DOCUMENTATION) --entrypoint /bin/sh $(IMAGE_VALE) -c \
	'OUTPUT=$$`vale --config etc/qa/vale.ini --no-global $(DOC_MD_FILES) 2>&1`; \
	EXIT_CODE=$$?; \
	echo "$$OUTPUT"; \
	if [ $$EXIT_CODE -ne 0 ]; then exit $$EXIT_CODE; fi; \
	if echo "$$OUTPUT" | grep -qiE "\bwarning\b|\berror\b"; then exit 1; fi'

documentation-qa: documentation-markdownlint documentation-links documentation-typos documentation-vale ## Run all documentation QA checks ##*E*##
