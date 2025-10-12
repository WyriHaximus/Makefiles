migrations-github-actions-remove-composer-diff: #### Remove composer-diff.yaml it has been folded into centralized workflows through ci.yaml ##*I*##
	($(DOCKER_RUN) rm .github/workflows/composer-diff.yaml || true)

migrations-github-actions-remove-markdown-check-links: #### Remove markdown-check-links.yaml it has been folded into centralized workflows through ci.yaml ##*I*##
	($(DOCKER_RUN) rm .github/workflows/markdown-check-links.yaml || true)

migrations-github-actions-move-ci: #### Move .github/workflows/ci.yml to .github/workflows/ci.yaml ##*I*##
	($(DOCKER_RUN) mv .github/workflows/ci.yml .github/workflows/ci.yaml || true)

migrations-github-actions-move-release-management: #### Move .github/workflows/release-managment.yaml to .github/workflows/release-management.yaml ##*I*##
	($(DOCKER_RUN) mv .github/workflows/release-managment.yaml .github/workflows/release-management.yaml || true)

migrations-github-actions-fix-management-in-release-management-referenced-workflow-file: #### Fix management in release-management referenced workflow file ##*I*##
	($(DOCKER_RUN) sed -i -e 's/release-managment.yaml/release-management.yaml/g' .github/workflows/release-management.yaml || true)
