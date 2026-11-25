IMAGE_MARKDOWNLINT := davidanson/markdownlint-cli2:v0.17.1
IMAGE_LYCHEE := lycheeverse/lychee:0.24.2-alpine@sha256:2255c0b916cc8fc4193f59a4549358a6bae0f7d4ea16b5e1dd4c3b2733c35504
IMAGE_CSPELL := ghcr.io/streetsidesoftware/cspell@sha256:d16ebc7f7b81414c500f279b0f96c43a8c1bfa04279b9b8d98d13c300e2b7095
IMAGE_VALE := jdkato/vale:v3.28.1@sha256:42b74f7f3ea7dd60554181037a8899a190516b9d48b67d157b09e5df791f9115
DOC_MD_FILES := $(shell find . -name "*.md" -not -name "LICENSE" -not -path "./var/*" -not -path "./vendor/*" -not -path "./.*/*" -not -path "./etc/qa/vale/*")
DOC_SRC_EXTS := php js ts yaml yml json5
DOC_SRC_FIND_NAMES := -name "*.$(firstword $(DOC_SRC_EXTS))" $(foreach ext,$(wordlist 2,$(words $(DOC_SRC_EXTS)),$(DOC_SRC_EXTS)),-o -name "*.$(ext)")
DOC_SRC_FILES := $(shell find src tests -type f \( $(DOC_SRC_FIND_NAMES) \) 2>/dev/null -not -path "*/TestUtilities/*")

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
	'OUTPUT=$$`vale --config etc/qa/vale.ini --no-global $(DOC_MD_FILES) $(DOC_SRC_FILES) 2>&1`; \
	EXIT_CODE=$$?; \
	echo "$$OUTPUT"; \
	if [ $$EXIT_CODE -ne 0 ]; then exit $$EXIT_CODE; fi; \
	if echo "$$OUTPUT" | grep -qiE "\bwarning\b|\berror\b"; then exit 1; fi'

documentation-qa: documentation-markdownlint documentation-links documentation-typos documentation-vale ## Run all documentation QA checks ##*E*##
