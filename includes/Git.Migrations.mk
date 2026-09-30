migrations-git-enforce-gitattributes-contents: #### Enforce `.gitattributes` contents ##*I*##
	($(DOCKER_RUN) php -r 'file_put_contents(".gitattributes", base64_decode("base64(DOTgitattributes)"));' || true)

migrations-git-enforce-editorconfig-contents: #### Enforce `.editorconfig` contents ##*I*##
	($(DOCKER_RUN) php -r 'file_put_contents(".editorconfig", base64_decode("base64(DOTeditorconfig)"));' || true)

migrations-git-make-sure-gitignore-exists: #### Make sure `.gitignore` exists ##*I*##
	($(DOCKER_RUN) touch .gitignore || true)

migrations-git-make-sure-gitignore-ignores-var: #### Make sure `.gitignore` ignores `var/*` ##*I*##
	($(DOCKER_RUN) php -r '$$gitignoreFile = ".gitignore"; if (!file_exists($$gitignoreFile)) {exit;} $$txt = file_get_contents($$gitignoreFile); if (!is_string($$txt)) {exit;} if (strpos($$txt, "var/*") !== false) {exit;} file_put_contents($$gitignoreFile, "var/*\n", FILE_APPEND);' || true)

migrations-git-make-sure-gitignore-excludes-var-gitkeep: #### Make sure `.gitignore` excludes `var/.gitkeep` ##*I*##
	($(DOCKER_RUN) php -r '$$gitignoreFile = ".gitignore"; if (!file_exists($$gitignoreFile)) {exit;} $$txt = file_get_contents($$gitignoreFile); if (!is_string($$txt)) {exit;} if (strpos($$txt, "!var/.gitkeep") !== false) {exit;} file_put_contents($$gitignoreFile, "!var/.gitkeep\n", FILE_APPEND);' || true)

migrations-git-make-sure-gitignore-ignores-vale-styles-except-vocab: #### Ignore Vale styles except WyriHaximus vocab symlink ##*I*##
	($(DOCKER_RUN) php -r '$$gitignoreFile = ".gitignore"; if (!file_exists($$gitignoreFile)) {exit;} $$txt = file_get_contents($$gitignoreFile); if (!is_string($$txt)) {exit;} if (strpos($$txt, "etc/qa/vale/**") !== false) {exit;} file_put_contents($$gitignoreFile, "etc/qa/vale/**\n!etc/qa/vale/styles/\n!etc/qa/vale/styles/config/\n!etc/qa/vale/styles/config/vocabularies/\n!etc/qa/vale/styles/config/vocabularies/WyriHaximus/\n!etc/qa/vale/styles/config/vocabularies/WyriHaximus/accept.txt\n", FILE_APPEND);' || true)
