migrations-git-enforce-gitattributes-contents: #### Enforce .gitattributes contents ##*I*##
	($(DOCKER_RUN) php -r 'file_put_contents(".gitattributes", base64_decode("IyBTZXQgdGhlIGRlZmF1bHQgYmVoYXZpb3IsIGluIGNhc2UgcGVvcGxlIGRvbid0IGhhdmUgY29yZS5hdXRvY3JsZiBzZXQuCiogdGV4dCBlb2w9bGYKCiMgSWdub3JpbmcgZmlsZXMgZm9yIGRpc3RyaWJ1dGlvbiBhcmNoaWV2ZXMKLmdpdGh1Yi8gZXhwb3J0LWlnbm9yZQpldGMvY2kvIGV4cG9ydC1pZ25vcmUKZXRjL3FhLyBleHBvcnQtaWdub3JlCmV4YW1wbGVzLyBleHBvcnQtaWdub3JlCnRlc3RzLyBleHBvcnQtaWdub3JlCnZhci8gZXhwb3J0LWlnbm9yZQouZGV2Y29udGFpbmVyLmpzb24gZXhwb3J0LWlnbm9yZQouZWRpdG9yY29uZmlnIGV4cG9ydC1pZ25vcmUKLmdpdGF0dHJpYnV0ZXMgZXhwb3J0LWlnbm9yZQouZ2l0aWdub3JlIGV4cG9ydC1pZ25vcmUKQ09OVFJJQlVUSU5HLm1kIGV4cG9ydC1pZ25vcmUKY29tcG9zZXIubG9jayBleHBvcnQtaWdub3JlCk1ha2VmaWxlIGV4cG9ydC1pZ25vcmUKUkVBRE1FLm1kIGV4cG9ydC1pZ25vcmUKCiMgRGlmZmluZwoqLnBocCBkaWZmPXBocAo="));' || true)

migrations-git-make-sure-gitignore-exists: #### Make sure .gitignore exists ##*I*##
	($(DOCKER_RUN) touch .gitignore || true)

migrations-git-make-sure-gitignore-ignores-var: #### Make sure .gitignore ignores var/* ##*I*##
	($(DOCKER_RUN) php -r '$$gitignoreFile = ".gitignore"; if (!file_exists($$gitignoreFile)) {exit;} $$txt = file_get_contents($$gitignoreFile); if (!is_string($$txt)) {exit;} if (strpos($$txt, "var/*") !== false) {exit;} file_put_contents($$gitignoreFile, "var/*\n", FILE_APPEND);' || true)

migrations-git-make-sure-gitignore-excludes-var-gitkeep: #### Make sure .gitignore excludes var/.gitkeep ##*I*##
	($(DOCKER_RUN) php -r '$$gitignoreFile = ".gitignore"; if (!file_exists($$gitignoreFile)) {exit;} $$txt = file_get_contents($$gitignoreFile); if (!is_string($$txt)) {exit;} if (strpos($$txt, "!var/.gitkeep") !== false) {exit;} file_put_contents($$gitignoreFile, "!var/.gitkeep\n", FILE_APPEND);' || true)
