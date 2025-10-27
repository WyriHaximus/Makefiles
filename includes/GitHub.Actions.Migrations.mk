migrations-php-make-sure-github-workflows-exists: #### Make sure .github/workflows exists ##*I*##
	($(DOCKER_RUN) mkdir .github/workflows || true)

migrations-github-actions-remove-composer-diff: #### Remove composer-diff.yaml it has been folded into centralized workflows through ci.yaml ##*I*##
	($(DOCKER_RUN) rm .github/workflows/composer-diff.yaml || true)

migrations-github-actions-remove-markdown-check-links: #### Remove markdown-check-links.yaml it has been folded into centralized workflows through ci.yaml ##*I*##
	($(DOCKER_RUN) rm .github/workflows/markdown-check-links.yaml || true)

migrations-github-actions-remove-markdown-craft-release: #### Remove craft-release.yaml it has been folded into centralized workflows through release-management.yaml ##*I*##
	($(DOCKER_RUN) rm .github/workflows/craft-release.yaml || true)

migrations-github-actions-remove-set-milestone-on-pr: #### Remove set-milestone-on-pr.yaml it has been folded into centralized workflows through release-management.yaml ##*I*##
	($(DOCKER_RUN) rm .github/workflows/set-milestone-on-pr.yaml || true)

migrations-github-actions-move-ci: #### Move .github/workflows/ci.yml to .github/workflows/ci.yaml ##*I*##
	($(DOCKER_RUN) mv .github/workflows/ci.yml .github/workflows/ci.yaml || true)

migrations-github-actions-remove-ci-if-its-old-style-php-ci-workflow: #### Remove CI Workflow if its the old style PHP CI Workflow ##*I*##
	($(DOCKER_RUN) php -r '$$ciWorkflowFile = ".github/workflows/ci.yaml"; if (!file_exists($$ciWorkflowFile)) {exit;} $$yaml = file_get_contents($$ciWorkflowFile); if (strpos($$yaml, "composer: [lowest, locked, highest]") !== false || strpos($$yaml, "composer: [lowest, current, highest]") !== false || strpos($$yaml, "- run: make ${{ matrix.check }}") !== false || strpos($$yaml, "if: matrix.check == 'backward-compatibility-check'") !== false) { unlink($$ciWorkflowFile); }' || true)

migrations-github-actions-create-ci-if-not-exists: #### Create CI Workflow if it doesn't exists at .github/workflows/ci.yaml ##*I*##
	($(DOCKER_RUN) php -r '$$ciWorkflowFile = ".github/workflows/ci.yaml"; $$ciWorkflowContents = base64_decode("bmFtZTogQ29udGludW91cyBJbnRlZ3JhdGlvbgpvbjoKICBwdXNoOgogICAgYnJhbmNoZXM6CiAgICAgIC0gJ21haW4nCiAgICAgIC0gJ21hc3RlcicKICAgICAgLSAncmVmcy9oZWFkcy92WzAtOV0rLlswLTldKy5bMC05XSsnCiAgcHVsbF9yZXF1ZXN0OgojIyBUaGlzIHdvcmtmbG93IG5lZWRzIHRoZSBgcHVsbC1yZXF1ZXN0YCBwZXJtaXNzaW9ucyB0byB3b3JrIGZvciB0aGUgcGFja2FnZSBkaWZmaW5nCiMjIFJlZnM6IGh0dHBzOi8vZG9jcy5naXRodWIuY29tL2VuL2FjdGlvbnMvcmVmZXJlbmNlL3dvcmtmbG93LXN5bnRheC1mb3ItZ2l0aHViLWFjdGlvbnMjcGVybWlzc2lvbnMKcGVybWlzc2lvbnM6CiAgcHVsbC1yZXF1ZXN0czogd3JpdGUKICBjb250ZW50czogcmVhZApqb2JzOgogIGNpOgogICAgbmFtZTogQ29udGludW91cyBJbnRlZ3JhdGlvbgogICAgdXNlczogV3lyaUhheGltdXMvZ2l0aHViLXdvcmtmbG93cy8uZ2l0aHViL3dvcmtmbG93cy9wYWNrYWdlLnlhbWxAbWFpbgo="); if (file_exists($$ciWorkflowFile)) {exit;} file_put_contents($$ciWorkflowFile, $$ciWorkflowContents);' || true)

migrations-github-actions-move-release-management: #### Move .github/workflows/release-managment.yaml to .github/workflows/release-management.yaml ##*I*##
	($(DOCKER_RUN) mv .github/workflows/release-managment.yaml .github/workflows/release-management.yaml || true)

migrations-github-actions-fix-management-in-release-management-referenced-workflow-file: #### Fix management in release-management referenced workflow file ##*I*##
	($(DOCKER_RUN) sed -i -e 's/release-managment.yaml/release-management.yaml/g' .github/workflows/release-management.yaml || true)

migrations-github-actions-create-release-management-if-not-exists: #### Create Release Management Workflow if it doesn't exists at .github/workflows/release-management.yaml ##*I*##
	($(DOCKER_RUN) php -r '$$releaseManagementWorkflowFile = ".github/workflows/release-management.yaml"; $$releaseManagementWorkflowContents = base64_decode("bmFtZTogUmVsZWFzZSBNYW5hZ2VtZW50Cm9uOgogIHB1bGxfcmVxdWVzdDoKICAgIHR5cGVzOgogICAgICAtIG9wZW5lZAogICAgICAtIGxhYmVsZWQKICAgICAgLSB1bmxhYmVsZWQKICAgICAgLSBzeW5jaHJvbml6ZQogICAgICAtIHJlb3BlbmVkCiAgICAgIC0gbWlsZXN0b25lZAogICAgICAtIGRlbWlsZXN0b25lZAogICAgICAtIHJlYWR5X2Zvcl9yZXZpZXcKICBtaWxlc3RvbmU6CiAgICB0eXBlczoKICAgICAgLSBjbG9zZWQKcGVybWlzc2lvbnM6CiAgY29udGVudHM6IHdyaXRlCiAgaXNzdWVzOiB3cml0ZQogIHB1bGwtcmVxdWVzdHM6IHdyaXRlCmpvYnM6CiAgcmVsZWFzZS1tYW5hZ21lbnQ6CiAgICBuYW1lOiBSZWxlYXNlIE1hbmFnZW1lbnQKICAgIHVzZXM6IFd5cmlIYXhpbXVzL2dpdGh1Yi13b3JrZmxvd3MvLmdpdGh1Yi93b3JrZmxvd3MvcGFja2FnZS1yZWxlYXNlLW1hbmFnZW1lbnQueWFtbEBtYWluCiAgICB3aXRoOgogICAgICBtaWxlc3RvbmU6ICR7eyBnaXRodWIuZXZlbnQubWlsZXN0b25lLnRpdGxlIH19CiAgICAgIGRlc2NyaXB0aW9uOiAke3sgZ2l0aHViLmV2ZW50Lm1pbGVzdG9uZS50aXRsZSB9fQo="); if (file_exists($$releaseManagementWorkflowFile)) {exit;} file_put_contents($$releaseManagementWorkflowFile, $$releaseManagementWorkflowContents);' || true)
