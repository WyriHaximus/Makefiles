migrations-git-enforce-gitattributes-contents: #### Enforce .gitattributes contents ##*I*##
	($(DOCKER_RUN) php -r 'file_put_contents(".gitattributes", base64_decode("IyBTZXQgdGhlIGRlZmF1bHQgYmVoYXZpb3IsIGluIGNhc2UgcGVvcGxlIGRvbid0IGhhdmUgY29yZS5hdXRvY3JsZiBzZXQuCiogdGV4dCBlb2w9bGYKCiMgVGhlc2UgZmlsZXMgYXJlIGJpbmFyeSBhbmQgc2hvdWxkIGJlIGxlZnQgdW50b3VjaGVkCiMgKGJpbmFyeSBpcyBhIG1hY3JvIGZvciAtdGV4dCAtZGlmZikKKi5wbmcgYmluYXJ5CiouanBnIGJpbmFyeQoqLmpwZWcgYmluYXJ5CiouZ2lmIGJpbmFyeQoqLmljbyBiaW5hcnkKKi53ZWJwIGJpbmFyeQoqLmJtcCBiaW5hcnkKKi50dGYgYmluYXJ5CgojIElnbm9yaW5nIGZpbGVzIGZvciBkaXN0cmlidXRpb24gYXJjaGlldmVzCi5naXRodWIvIGV4cG9ydC1pZ25vcmUKZXRjL2NpLyBleHBvcnQtaWdub3JlCmV0Yy9kZXYtYXBwLyBleHBvcnQtaWdub3JlCmV0Yy9xYS8gZXhwb3J0LWlnbm9yZQpleGFtcGxlcy8gZXhwb3J0LWlnbm9yZQp0ZXN0cy8gZXhwb3J0LWlnbm9yZQp2YXIvIGV4cG9ydC1pZ25vcmUKLmRldmNvbnRhaW5lci5qc29uIGV4cG9ydC1pZ25vcmUKLmVkaXRvcmNvbmZpZyBleHBvcnQtaWdub3JlCi5naXRhdHRyaWJ1dGVzIGV4cG9ydC1pZ25vcmUKLmdpdGlnbm9yZSBleHBvcnQtaWdub3JlCkNPTlRSSUJVVElORy5tZCBleHBvcnQtaWdub3JlCmNvbXBvc2VyLmxvY2sgZXhwb3J0LWlnbm9yZQpNYWtlZmlsZSBleHBvcnQtaWdub3JlClJFQURNRS5tZCBleHBvcnQtaWdub3JlCgojIERpZmZpbmcKKi5waHAgZGlmZj1waHAK"));' || true)

migrations-git-make-sure-gitignore-exists: #### Make sure .gitignore exists ##*I*##
	($(DOCKER_RUN) touch .gitignore || true)

migrations-git-make-sure-gitignore-ignores-var: #### Make sure .gitignore ignores var/* ##*I*##
	($(DOCKER_RUN) php -r '$$gitignoreFile = ".gitignore"; if (!file_exists($$gitignoreFile)) {exit;} $$txt = file_get_contents($$gitignoreFile); if (!is_string($$txt)) {exit;} if (strpos($$txt, "var/*") !== false) {exit;} file_put_contents($$gitignoreFile, "var/*\n", FILE_APPEND);' || true)

migrations-git-make-sure-gitignore-excludes-var-gitkeep: #### Make sure .gitignore excludes var/.gitkeep ##*I*##
	($(DOCKER_RUN) php -r '$$gitignoreFile = ".gitignore"; if (!file_exists($$gitignoreFile)) {exit;} $$txt = file_get_contents($$gitignoreFile); if (!is_string($$txt)) {exit;} if (strpos($$txt, "!var/.gitkeep") !== false) {exit;} file_put_contents($$gitignoreFile, "!var/.gitkeep\n", FILE_APPEND);' || true)
