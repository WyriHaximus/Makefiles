# Documentation QA: pinned Docker tool image; CI once via ci-locked (K).

IMAGE_MARKDOWNLINT := davidanson/markdownlint-cli2:v0.17.2
DOCKER_RUN_MARKDOWNLINT=docker run --rm -i $(DOCKER_DEFAULT_SECURITY_OPS) $(DOCKER_WORKSPACE_BIND_OPS) $(IMAGE_MARKDOWNLINT)

documentation-markdownlint: ## Lint markdown structure ##*K*##
	$(DOCKER_RUN_MARKDOWNLINT) --config etc/qa/documentation.markdownlint-cli2.yaml
