migrations-git-enforce-gitattributes-contents: #### Enforce .gitattributes contents ##*I*##
	($(DOCKER_RUN) php -r 'file_put_contents(".gitattributes", base64_decode("IyBTZXQgdGhlIGRlZmF1bHQgYmVoYXZpb3IsIGluIGNhc2UgcGVvcGxlIGRvbid0IGhhdmUgY29yZS5hdXRvY3JsZiBzZXQuCiogdGV4dCBlb2w9bGYKCiMgVGhlc2UgZmlsZXMgYXJlIGJpbmFyeSBhbmQgc2hvdWxkIGJlIGxlZnQgdW50b3VjaGVkCiMgKGJpbmFyeSBpcyBhIG1hY3JvIGZvciAtdGV4dCAtZGlmZikKKi5wbmcgYmluYXJ5CiouanBnIGJpbmFyeQoqLmpwZWcgYmluYXJ5CiouZ2lmIGJpbmFyeQoqLmljbyBiaW5hcnkKKi53ZWJwIGJpbmFyeQoqLmJtcCBiaW5hcnkKKi50dGYgYmluYXJ5CiouYmxwIGJpbmFyeQoKIyBJZ25vcmluZyBmaWxlcyBmb3IgZGlzdHJpYnV0aW9uIGFyY2hpZXZlcwouZ2l0aHViLyBleHBvcnQtaWdub3JlCmV0Yy9jaS8gZXhwb3J0LWlnbm9yZQpldGMvZGV2LWFwcC8gZXhwb3J0LWlnbm9yZQpldGMvcWEvIGV4cG9ydC1pZ25vcmUKZXhhbXBsZXMvIGV4cG9ydC1pZ25vcmUKdGVzdHMvIGV4cG9ydC1pZ25vcmUKdmFyLyBleHBvcnQtaWdub3JlCi5kZXZjb250YWluZXIuanNvbiBleHBvcnQtaWdub3JlCi5lZGl0b3Jjb25maWcgZXhwb3J0LWlnbm9yZQouZ2l0YXR0cmlidXRlcyBleHBvcnQtaWdub3JlCi5naXRpZ25vcmUgZXhwb3J0LWlnbm9yZQpDT05UUklCVVRJTkcubWQgZXhwb3J0LWlnbm9yZQpjb21wb3Nlci5sb2NrIGV4cG9ydC1pZ25vcmUKTWFrZWZpbGUgZXhwb3J0LWlnbm9yZQpSRUFETUUubWQgZXhwb3J0LWlnbm9yZQoKIyBEaWZmaW5nCioucGhwIGRpZmY9cGhwCg=="));' || true)

migrations-git-make-sure-gitignore-exists: #### Make sure .gitignore exists ##*I*##
	($(DOCKER_RUN) touch .gitignore || true)

migrations-git-make-sure-gitignore-ignores-var: #### Make sure .gitignore ignores var/* ##*I*##
	($(DOCKER_RUN) php -r '$$gitignoreFile = ".gitignore"; if (!file_exists($$gitignoreFile)) {exit;} $$txt = file_get_contents($$gitignoreFile); if (!is_string($$txt)) {exit;} if (strpos($$txt, "var/*") !== false) {exit;} file_put_contents($$gitignoreFile, "var/*\n", FILE_APPEND);' || true)

migrations-git-make-sure-gitignore-excludes-var-gitkeep: #### Make sure .gitignore excludes var/.gitkeep ##*I*##
	($(DOCKER_RUN) php -r '$$gitignoreFile = ".gitignore"; if (!file_exists($$gitignoreFile)) {exit;} $$txt = file_get_contents($$gitignoreFile); if (!is_string($$txt)) {exit;} if (strpos($$txt, "!var/.gitkeep") !== false) {exit;} file_put_contents($$gitignoreFile, "!var/.gitkeep\n", FILE_APPEND);' || true)
