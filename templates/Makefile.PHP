# set all to phony
SHELL=bash

.PHONY: *

DOCKER_AVAILABLE=$(shell ((command -v docker >/dev/null 2>&1) && echo 0 || echo 1))
CONTAINER_REGISTRY_REPO="ghcr.io/wyrihaximusnet/php"
SLIM_DOCKER_IMAGE=when_in_requirements(["ext-gd","ext-vips"], "", "-slim")
NTS_OR_ZTS_DOCKER_IMAGE=when_in_requirements(["ext-parallel"], "zts", "nts")
NEEDS_DOCKER_SOCKET=when_in_requirements(["testcontainers/testcontainers"], TRUE, FALSE)
PHP_VERSION=lowest_cleaned_version_in_tree_from_file("composer.json", "config.platform.php")
CONTAINER_NAME=$(shell echo "${CONTAINER_REGISTRY_REPO}:${PHP_VERSION}-${NTS_OR_ZTS_DOCKER_IMAGE}-alpine${SLIM_DOCKER_IMAGE}-dev")
COMPOSER_CACHE_DIR=$(shell (command -v composer >/dev/null 2>&1) && composer config --global cache-dir -q 2>/dev/null || echo ${HOME}/.composer-php/cache)
COMPOSER_CONTAINER_CACHE_DIR=$(shell ((command -v docker >/dev/null 2>&1) && docker run --rm -it ${CONTAINER_NAME} composer config --global cache-dir -q) || echo ${HOME}/.composer-php/cache)

ifneq ("$(wildcard /.you-are-in-a-wyrihaximus.net-php-docker-image)","")
    IN_DOCKER=TRUE
else
    IN_DOCKER=FALSE
endif

ifeq ("$(IN_DOCKER)","TRUE")
	DOCKER_RUN:=
	DOCKER_RUN_WITH_SOCKET:=
	DOCKER_SHELL:=
else
    ifeq ($(DOCKER_AVAILABLE),0)
        DOCKER_COMMON_OPS:=-v "`pwd`:`pwd`" -w "`pwd`" -v "${COMPOSER_CACHE_DIR}:${COMPOSER_CONTAINER_CACHE_DIR}" -e OTEL_PHP_FIBERS_ENABLED="true"
        ifeq ("$(NEEDS_DOCKER_SOCKET)","TRUE")
            ifneq ("$(wildcard /var/run/docker.sock)","")
                DOCKER_SOCKET_OPS:=-v "/var/run/docker.sock:/var/run/docker.sock"
                DOCKER_SOCKET_CONTAINER_NAME_SUFFIX:=-root
            else
                DOCKER_SOCKET_OPS:=
                DOCKER_SOCKET_CONTAINER_NAME_SUFFIX:=
            endif
        else
            DOCKER_SOCKET_OPS:=
            DOCKER_SOCKET_CONTAINER_NAME_SUFFIX:=
        endif
        DOCKER_RUN:=docker run --rm -i ${DOCKER_COMMON_OPS} "${CONTAINER_NAME}"
        DOCKER_RUN_WITH_SOCKET:=docker run --rm -i ${DOCKER_COMMON_OPS} ${DOCKER_SOCKET_OPS} "${CONTAINER_NAME}${DOCKER_SOCKET_CONTAINER_NAME_SUFFIX}"
        DOCKER_SHELL:=docker run --rm -it ${DOCKER_COMMON_OPS} "${CONTAINER_NAME}"
    else
        DOCKER_RUN:=
        DOCKER_RUN_WITH_SOCKET:=
        DOCKER_SHELL:=
    endif
endif

ifneq (,$(findstring icrosoft,$(shell cat /proc/version)))
    THREADS=1
else
    THREADS=$(shell nproc)
endif

## Run everything extra point
include includes/All.mk

## Temporary set of migrations to get all my repos in shape
include includes/Git.Migrations.mk
include includes/PHP.Migrations.mk
include includes/GitHub.Migrations.mk
include includes/GitHub.Actions.Migrations.mk
include includes/Renovate.Migrations.mk

## Our default jobs
include includes/EXTRA.mk
include includes/OnInstallUpdate.mk
include includes/PHP.mk
include includes/Shell.mk
include includes/Help.mk
include includes/TaskFinders.mk
