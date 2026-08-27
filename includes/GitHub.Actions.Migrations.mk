migrations-php-make-sure-github-workflows-exists: #### Make sure `.github/workflows` exists ##*I*##
	($(DOCKER_RUN) mkdir .github/workflows || true)

migrations-github-actions-remove-composer-diff: #### Remove `composer-diff.yaml` it has been folded into centralized workflows through `ci.yaml` ##*I*##
	($(DOCKER_RUN) rm .github/workflows/composer-diff.yaml || true)

migrations-github-actions-remove-markdown-check-links: #### Remove `markdown-check-links.yaml` it has been folded into centralized workflows through `ci.yaml` ##*I*##
	($(DOCKER_RUN) rm .github/workflows/markdown-check-links.yaml || true)

migrations-github-actions-remove-markdown-craft-release: #### Remove `craft-release.yaml` it has been folded into centralized workflows through `release-management.yaml` ##*I*##
	($(DOCKER_RUN) rm .github/workflows/craft-release.yaml || true)

migrations-github-actions-remove-set-milestone-on-pr: #### Remove `set-milestone-on-pr.yaml` it has been folded into centralized workflows through `release-management.yaml` ##*I*##
	($(DOCKER_RUN) rm .github/workflows/set-milestone-on-pr.yaml || true)

migrations-github-actions-move-ci: #### Move `.github/workflows/ci.yml` to `.github/workflows/ci.yaml` ##*I*##
	($(DOCKER_RUN) mv .github/workflows/ci.yml .github/workflows/ci.yaml || true)

migrations-github-actions-remove-ci-if-its-old-style-php-ci-workflow: #### Remove CI Workflow if its the old style PHP CI Workflow ##*I*##
	($(DOCKER_RUN) php -r '$$ciWorkflowFile = ".github/workflows/ci.yaml"; if (!file_exists($$ciWorkflowFile)) {exit;} $$yaml = file_get_contents($$ciWorkflowFile); if (!is_string($$yaml)) {exit;} if (strpos($$yaml, "composer: [lowest, locked, highest]") !== false || strpos($$yaml, "composer: [lowest, current, highest]") !== false || strpos($$yaml, "- run: make ${{ matrix.check }}") !== false || strpos($$yaml, trim(base64_decode("base64(if-matrix-check-backward-compatibility-check)"))) !== false) { unlink($$ciWorkflowFile); }' || true)

migrations-github-actions-create-ci-if-not-exists: #### Create CI Workflow if it doesn't exists at `.github/workflows/ci.yaml` ##*I*##
	($(DOCKER_RUN) php -r '$$ciWorkflowFile = ".github/workflows/ci.yaml"; $$ciWorkflowContents = base64_decode("base64(ci.yaml)"); if (file_exists($$ciWorkflowFile)) {exit;} file_put_contents($$ciWorkflowFile, $$ciWorkflowContents);' || true)

migrations-github-actions-move-release-management: #### Move `.github/workflows/release-managment.yaml` to `.github/workflows/release-management.yaml` ##*I*##
	($(DOCKER_RUN) mv .github/workflows/release-managment.yaml .github/workflows/release-management.yaml || true)

migrations-github-actions-fix-management-in-release-management-referenced-workflow-file: #### Fix management in release-management referenced workflow file ##*I*##
	($(DOCKER_RUN) sed -i -e 's/release-managment.yaml/release-management.yaml/g' .github/workflows/release-management.yaml || true)

migrations-github-actions-create-release-management-if-not-exists: #### Create Release Management Workflow if it doesn't exists at `.github/workflows/release-management.yaml` ##*I*##
	($(DOCKER_RUN) php -r '$$releaseManagementWorkflowFile = ".github/workflows/release-management.yaml"; $$releaseManagementWorkflowContents = base64_decode("base64(release-management.yaml)"); if (file_exists($$releaseManagementWorkflowFile)) {exit;} file_put_contents($$releaseManagementWorkflowFile, $$releaseManagementWorkflowContents);' || true)

migrations-github-actions-ensure-runs-on-is-the-only-runs-on-variant-in-utils-yaml: #### Ensure `runsOn` is the only `runsOn` variant in `.github/workflows/utils.yaml` ##*I*##
	($(DOCKER_RUN) php -r '$$utilsWorkflowFile = ".github/workflows/utils.yaml"; if (!file_exists($$utilsWorkflowFile)) {exit;} $$yaml = file_get_contents($$utilsWorkflowFile); if (!is_string($$yaml)) {exit;} $$yaml = preg_replace("#(\s+)runsOn[A-Za-z0-9_]+:#", "$$1runsOn:", $$yaml); file_put_contents($$utilsWorkflowFile, $$yaml);' || true)
