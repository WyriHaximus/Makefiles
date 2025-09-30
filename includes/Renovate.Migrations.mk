migrations-renovate-move-config: #### Move renovate.json to .github/renovate.json ##*I*##
	($(DOCKER_RUN) mv renovate.json .github/renovate.json || true)
