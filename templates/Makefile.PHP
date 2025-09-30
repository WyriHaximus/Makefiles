# set all to phony
SHELL=bash

.PHONY: *

DOCKER_AVAILABLE=$(shell ((command -v docker >/dev/null 2>&1) && echo 0 || echo 1))
CONTAINER_REGISTRY_REPO="ghcr.io/wyrihaximusnet/php"
COMPOSER_SHOW_EXTENSION_LIST_PROD=$(shell (((command -v composer >/dev/null 2>&1) && composer show -t --no-plugins) || docker run --rm -v "`pwd`:`pwd`" -w `pwd` ${CONTAINER_REGISTRY_REPO}:8.4-nts-alpine-slim-dev composer show -t --no-plugins) | grep -o "\-\-\(ext-\).\+" | sort | uniq | cut -d- -f4- | tr -d '\n' | grep . | sed  '/^$$/d' | xargs | sed -e 's/ /, /g' | tr -cd '[:alnum:],' | sed 's/.$$//')
COMPOSER_SHOW_EXTENSION_LIST_DEV=$(shell (((command -v composer >/dev/null 2>&1) && composer show -s --no-plugins) || docker run --rm -v "`pwd`:`pwd`" -w `pwd` ${CONTAINER_REGISTRY_REPO}:8.4-nts-alpine-slim-dev composer show -s --no-plugins) | grep -o "\(ext-\).\+" | sort | uniq | cut -d- -f2- | cut -d" " -f1 | xargs | sed -e 's/ /, /g' | tr -cd '[:alnum:],')
COMPOSER_SHOW_EXTENSION_LIST=$(shell echo "${COMPOSER_SHOW_EXTENSION_LIST_PROD},${COMPOSER_SHOW_EXTENSION_LIST_DEV}")
SLIM_DOCKER_IMAGE=$(shell php -r 'echo count(array_intersect(["gd", "vips"], explode(",", "${COMPOSER_SHOW_EXTENSION_LIST}"))) > 0 ? "" : "-slim";')
NTS_OR_ZTS_DOCKER_IMAGE=$(shell php -r 'echo count(array_intersect(["parallel"], explode(",", "${COMPOSER_SHOW_EXTENSION_LIST}"))) > 0 ? "zts" : "nts";')
PHP_VERSION:=$(shell (((command -v docker >/dev/null 2>&1) && docker run --rm -v "`pwd`:`pwd`" ${CONTAINER_REGISTRY_REPO}:8.4-nts-alpine-slim php -r "echo json_decode(file_get_contents('`pwd`/composer.json'), true)['config']['platform']['php'];") || echo "8.3") | php -r "echo str_replace('|', '.', explode('.', implode('|', explode('.', stream_get_contents(STDIN), 2)), 2)[0]);")
CONTAINER_NAME=$(shell echo "${CONTAINER_REGISTRY_REPO}:${PHP_VERSION}-${NTS_OR_ZTS_DOCKER_IMAGE}-alpine${SLIM_DOCKER_IMAGE}-dev")
COMPOSER_CACHE_DIR=$(shell (command -v composer >/dev/null 2>&1) && composer config --global cache-dir -q || echo ${HOME}/.composer-php/cache)
COMPOSER_CONTAINER_CACHE_DIR=$(shell ((command -v docker >/dev/null 2>&1) && docker run --rm -it ${CONTAINER_NAME} composer config --global cache-dir -q) || echo ${HOME}/.composer-php/cache)

ifneq ("$(wildcard /.you-are-in-a-wyrihaximus.net-php-docker-image)","")
    IN_DOCKER=TRUE
else
    IN_DOCKER=FALSE
endif

ifeq ("$(IN_DOCKER)","TRUE")
	DOCKER_RUN:=
else
    ifeq ($(DOCKER_AVAILABLE),0)
        DOCKER_RUN:=docker run --rm -it \
            -v "`pwd`:`pwd`" \
            -v "${COMPOSER_CACHE_DIR}:${COMPOSER_CONTAINER_CACHE_DIR}" \
            -w "`pwd`" \
            -e OTEL_PHP_FIBERS_ENABLED="true" \
            "${CONTAINER_NAME}"
    else
        DOCKER_RUN:=
    endif
endif

ifneq (,$(findstring icrosoft,$(shell cat /proc/version)))
    THREADS=1
else
    THREADS=$(shell nproc)
endif

include includes/All.mk
include includes/OnInstallUpdate.mk
include includes/PHP.mk
include includes/Shell.mk
include includes/EXTRA.mk
include includes/Help.mk
include includes/TaskFinders.mk

## Temporary set of migrations to get all my repos in shape
include includes/PHP.Migrations.mk
include includes/GitHub.Migrations.mk
include includes/GitHub.Actions.Migrations.mk
include includes/Renovate.Migrations.mk
