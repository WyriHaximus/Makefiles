migrations-docs-update-readme-copyright-c-year-to-current: #### Update readme copyright year to current ##*I*##
	($(DOCKER_RUN) php -r '$$readmeFile = "README.md"; $$copyRight = "Copyright (c) "; $$currentYear = date("Y"); if (!file_exists($$readmeFile)) {exit;} $$readmeContents = file_get_contents($$readmeFile); foreach (range(2000, 2100) as $$year) { $$readmeContents = str_replace($$copyRight . $$year,  $$copyRight . $$currentYear, $$readmeContents); } file_put_contents($$readmeFile, $$readmeContents); ' || true)

migrations-docs-update-readme-copyright-year-to-current: #### Update readme copyright year to current ##*I*##
	($(DOCKER_RUN) php -r '$$readmeFile = "README.md"; $$copyRight = "Copyright "; $$currentYear = date("Y"); if (!file_exists($$readmeFile)) {exit;} $$readmeContents = file_get_contents($$readmeFile); foreach (range(2000, 2100) as $$year) { $$readmeContents = str_replace($$copyRight . $$year,  $$copyRight . $$currentYear, $$readmeContents); } file_put_contents($$readmeFile, $$readmeContents); ' || true)

migrations-docs-update-etc-readme-template-copyright-c-year-to-current: #### Update readme template in etc/ copyright year to current ##*I*##
	($(DOCKER_RUN) php -r '$$readmeFile = "etc/README.md.twig"; $$copyRight = "Copyright (c) "; $$currentYear = date("Y"); if (!file_exists($$readmeFile)) {exit;} $$readmeContents = file_get_contents($$readmeFile); foreach (range(2000, 2100) as $$year) { $$readmeContents = str_replace($$copyRight . $$year,  $$copyRight . $$currentYear, $$readmeContents); } file_put_contents($$readmeFile, $$readmeContents); ' || true)

migrations-docs-update-etc-readme-template-copyright-year-to-current: #### Update readme template in etc/ copyright year to current ##*I*##
	($(DOCKER_RUN) php -r '$$readmeFile = "etc/README.md.twig"; $$copyRight = "Copyright "; $$currentYear = date("Y"); if (!file_exists($$readmeFile)) {exit;} $$readmeContents = file_get_contents($$readmeFile); foreach (range(2000, 2100) as $$year) { $$readmeContents = str_replace($$copyRight . $$year,  $$copyRight . $$currentYear, $$readmeContents); } file_put_contents($$readmeFile, $$readmeContents); ' || true)

migrations-docs-create-license-when-it-doesnt-exists: #### Create license when it doesn't exists ##*I*##
	($(DOCKER_RUN) php -r '$$licenseFile = "LICENSE"; $$composerFIle = "composer.json"; if (file_exists($$licenseFile)) {exit;} if (file_exists($$composerFIle)) {$$json = json_decode(file_get_contents($$composerFIle), true); if (array_key_exists("license", $$json)) {if ($$json["license"] === "proprietary") {exit;}}}  file_put_contents($$licenseFile, base64_decode("base64(LICENSE)"));' || true)

migrations-docs-update-license-copyright-c-year-to-current: #### Update license copyright year to current ##*I*##
	($(DOCKER_RUN) php -r '$$licenseFile = "LICENSE"; $$copyRight = "Copyright (c) "; $$currentYear = date("Y"); if (!file_exists($$licenseFile)) {exit;} $$licenseContents = file_get_contents($$licenseFile); foreach (range(2000, 2100) as $$year) { $$licenseContents = str_replace($$copyRight . $$year,  $$copyRight . $$currentYear, $$licenseContents); } file_put_contents($$licenseFile, $$licenseContents); ' || true)

migrations-docs-update-license-copyright-year-to-current: #### Update license copyright year to current ##*I*##
	($(DOCKER_RUN) php -r '$$licenseFile = "LICENSE"; $$copyRight = "Copyright "; $$currentYear = date("Y"); if (!file_exists($$licenseFile)) {exit;} $$licenseContents = file_get_contents($$licenseFile); foreach (range(2000, 2100) as $$year) { $$licenseContents = str_replace($$copyRight . $$year,  $$copyRight . $$currentYear, $$licenseContents); } file_put_contents($$licenseFile, $$licenseContents); ' || true)

migrations-docs-enforce-contributing-md-contents: #### Enforce CONTRIBUTING.md contents ##*I*##
	($(DOCKER_RUN) php -r '$$contributingFile = "CONTRIBUTING.md"; $$contributingContents = base64_decode("base64(CONTRIBUTING-md)"); file_put_contents($$contributingFile, str_replace(["[repo]"], [basename(__DIR__)], $$contributingContents)); ' || true)

migrations-docs-create-documentation-markdownlint-config-if-not-exists: #### Create `etc/qa/documentation.markdownlint-cli2.yaml` when missing ##*I*##
	($(DOCKER_RUN) php -r '$$configFile = "etc/qa/documentation.markdownlint-cli2.yaml"; if (file_exists($$configFile)) {exit;} if (!is_dir("etc/qa")) {mkdir("etc/qa", 0777, true);} file_put_contents($$configFile, base64_decode("base64(documentation.markdownlint-cli2.yaml)"));' || true)

migrations-docs-create-lychee-config-if-not-exists: #### Create `etc/qa/lychee.toml` when missing ##*I*##
	($(DOCKER_RUN) php -r '$$configFile = "etc/qa/lychee.toml"; if (file_exists($$configFile)) {exit;} if (!is_dir("etc/qa")) {mkdir("etc/qa", 0777, true);} file_put_contents($$configFile, base64_decode("base64(lychee.toml)"));' || true)

migrations-docs-enforce-cspell-config-contents: #### Enforce `etc/qa/cspell.json` contents ##*I*##
	($(DOCKER_RUN) php -r '$$configFile = "etc/qa/cspell.json"; if (!is_dir("etc/qa")) {mkdir("etc/qa", 0777, true);} file_put_contents($$configFile, base64_decode("base64(cspell.json)"));' || true)

migrations-docs-enforce-vale-config-contents: #### Enforce `etc/qa/vale.ini` contents ##*I*##
	($(DOCKER_RUN) php -r '$$configFile = "etc/qa/vale.ini"; if (!is_dir("etc/qa")) {mkdir("etc/qa", 0777, true);} file_put_contents($$configFile, base64_decode("base64(vale.ini)"));' || true)

migrations-docs-enforce-vale-vocab-contents: #### Enforce `etc/qa/vale-vocab.txt` contents ##*I*##
	($(DOCKER_RUN) php -r '$$configFile = "etc/qa/vale-vocab.txt"; if (!is_dir("etc/qa")) {mkdir("etc/qa", 0777, true);} file_put_contents($$configFile, base64_decode("base64(vale-vocab.txt)"));' || true)

migrations-docs-create-vale-vocab-symlink-if-not-exists: #### Create WyriHaximus Vale vocab symlink when missing ##*I*##
	($(DOCKER_RUN) php -r '$$link = "etc/qa/vale/styles/config/vocabularies/WyriHaximus/accept.txt"; if (is_link($$link)) {exit;} if (!is_dir("etc/qa")) {mkdir("etc/qa", 0777, true);} if (!is_dir(dirname($$link))) {mkdir(dirname($$link), 0777, true);} symlink("../../../../../vale-vocab.txt", $$link);' || true)
