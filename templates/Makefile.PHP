# set all to phony
SHELL=bash

.PHONY: *

ifneq (,$(findstring icrosoft,$(shell cat /proc/version)))
    THREADS?=1
else
    THREADS?=$(shell nproc)
endif
DOCKER_AVAILABLE=$(shell ((command -v docker >/dev/null 2>&1) && echo 0 || echo 1))
TTY_AVAILABLE=$(shell (test -t 1 && echo 0) || echo 1)
CONTAINER_REGISTRY_REPO="ghcr.io/wyrihaximusnet/php"
SLIM_DOCKER_IMAGE=when_in_requirements(["ext-gd","ext-vips"], "", "-slim")
NTS_OR_ZTS_DOCKER_IMAGE=when_in_requirements(["ext-parallel"], "zts", "nts")
OTEL_PHP_FIBERS_ENABLED=when_in_requirements(["ext-parallel"], false, true)
NEEDS_DOCKER_SOCKET=when_in_requirements(["testcontainers/testcontainers"], TRUE, FALSE)
HAS_EXTRA_SERVICES=when_target_exists_in_extra(extra-services-up, TRUE, FALSE)
ALL_HAS_DIRECT_DOCKER_TASKS=when_aggregate_has_direct_docker_tasks(all, TRUE, FALSE)
CONTRIB_HAS_DIRECT_DOCKER_TASKS=when_aggregate_has_direct_docker_tasks(contrib, TRUE, FALSE)
ON_INSTALL_OR_UPDATE_HAS_DIRECT_DOCKER_TASKS=when_aggregate_has_direct_docker_tasks(on-install-or-update, TRUE, FALSE)
PHP_VERSION=lowest_cleaned_version_in_tree_from_file("composer.json", "config.platform.php")
CONTAINER_NAME=$(shell echo "${CONTAINER_REGISTRY_REPO}:${PHP_VERSION}-${NTS_OR_ZTS_DOCKER_IMAGE}-alpine${SLIM_DOCKER_IMAGE}-dev")
CONTAINER_NAME_INTERACTIVE_SHELL=$(shell echo "${CONTAINER_REGISTRY_REPO}:${PHP_VERSION}-zts-alpine-dev")
COMPOSER_CACHE_DIR=$(shell (command -v composer >/dev/null 2>&1) && composer config --global cache-dir -q 2>/dev/null || echo ${HOME}/.composer-php/cache)
COMPOSER_CONTAINER_CACHE_DIR=$(shell ((command -v docker >/dev/null 2>&1) && docker run --rm $(if $(filter 0,$(TTY_AVAILABLE)),-it,-i) ${CONTAINER_NAME} composer config --global cache-dir -q) || echo ${HOME}/.composer-php/cache)

ifneq ("$(wildcard /.you-are-in-a-wyrihaximus.net-php-docker-image)","")
    IN_DOCKER=TRUE
else
    IN_DOCKER=FALSE
endif

ifeq ("$(GITHUB_ACTIONS)","true")
    IN_CI=TRUE
else
    IN_CI=FALSE
endif

ifeq ("$(IN_DOCKER)","TRUE")
	DOCKER_RUN:=
	DOCKER_RUN_WITHOUT_NETWORK_FOR_COMPOSER:=
	DOCKER_RUN_WITH_SOCKET:=
	DOCKER_SHELL:=
	DOCKER_INTERACTIVE_SHELL:=
else
    ifeq ($(DOCKER_AVAILABLE),0)
        DOCKER_DEFAULT_SECURITY_OPS=--cap-drop=ALL --security-opt="no-new-privileges=true" --user="`id -u`:`id -g`"
        DOCKER_COMMON_OPS:=-v "`pwd`:`pwd`" -w "`pwd`" -v "`pwd`/.git:`pwd`/.git:ro" -v "${COMPOSER_CACHE_DIR}:${COMPOSER_CONTAINER_CACHE_DIR}" --ulimit nofile=1000000 -e THREADS="${THREADS}" -e OTEL_PHP_DISABLED_INSTRUMENTATIONS="all"
        DOCKER_COMMON_NON_INTERACTIVE_OPS:=-e OTEL_PHP_FIBERS_ENABLED="${OTEL_PHP_FIBERS_ENABLED}"
        DOCKER_COMMON_INTERACTIVE_OPS:=-e OTEL_PHP_FIBERS_ENABLED="false"
        ifeq ("$(NEEDS_DOCKER_SOCKET)","TRUE")
            ifneq ("$(wildcard /var/run/docker.sock)","")
                DOCKER_SECURITY_OPS:=
                DOCKER_SOCKET_OPS:=-v "/var/run/docker.sock:/var/run/docker.sock"
                DOCKER_SOCKET_CONTAINER_NAME_SUFFIX:=-root
            else
                DOCKER_SECURITY_OPS:=${DOCKER_DEFAULT_SECURITY_OPS}
                DOCKER_SOCKET_OPS:=
                DOCKER_SOCKET_CONTAINER_NAME_SUFFIX:=
            endif
        else
            DOCKER_SECURITY_OPS:=${DOCKER_DEFAULT_SECURITY_OPS}
            DOCKER_SOCKET_OPS:=
            DOCKER_SOCKET_CONTAINER_NAME_SUFFIX:=
        endif
        DOCKER_RUN:=docker run --rm -i ${DOCKER_SECURITY_OPS} ${DOCKER_COMMON_NON_INTERACTIVE_OPS} ${DOCKER_COMMON_OPS} "${CONTAINER_NAME}"
        DOCKER_RUN_WITHOUT_NETWORK_FOR_COMPOSER:=docker run --rm -i ${DOCKER_SECURITY_OPS} ${DOCKER_COMMON_NON_INTERACTIVE_OPS} -e COMPOSER_DISABLE_NETWORK="1" ${DOCKER_COMMON_OPS} "${CONTAINER_NAME}"
        DOCKER_RUN_WITH_SOCKET:=docker run --rm -i ${DOCKER_SECURITY_OPS} ${DOCKER_COMMON_NON_INTERACTIVE_OPS} ${DOCKER_COMMON_OPS} ${DOCKER_SOCKET_OPS} "${CONTAINER_NAME}${DOCKER_SOCKET_CONTAINER_NAME_SUFFIX}"
        ifeq ($(TTY_AVAILABLE),0)
            DOCKER_SHELL:=docker run --rm -it ${DOCKER_SECURITY_OPS} ${DOCKER_COMMON_NON_INTERACTIVE_OPS} ${DOCKER_COMMON_OPS} "${CONTAINER_NAME}"
            DOCKER_INTERACTIVE_SHELL:=docker run --rm -it ${DOCKER_SECURITY_OPS} ${DOCKER_COMMON_INTERACTIVE_OPS} ${DOCKER_COMMON_OPS} "${CONTAINER_NAME_INTERACTIVE_SHELL}"
        else
            DOCKER_SHELL:=$(DOCKER_RUN)
            DOCKER_INTERACTIVE_SHELL:=docker run --rm -i ${DOCKER_SECURITY_OPS} ${DOCKER_COMMON_INTERACTIVE_OPS} ${DOCKER_COMMON_OPS} "${CONTAINER_NAME_INTERACTIVE_SHELL}"
        endif
    else
        DOCKER_RUN:=
        DOCKER_RUN_WITHOUT_NETWORK_FOR_COMPOSER:=
        DOCKER_RUN_WITH_SOCKET:=
        DOCKER_SHELL:=
		DOCKER_INTERACTIVE_SHELL:=
    endif
endif

## Run everything extra points
include includes/All.mk

## Run a subset of everything for those that find everything intimidating
include includes/Contrib.mk

## Temporary set of migrations to get all my repos in shape
include includes/Git.Migrations.mk
include includes/Docs.Migrations.mk
include includes/PHP.Migrations.mk
include includes/PHP.SupportedFeatures.Migrations.mk
include includes/GitHub.Migrations.mk
include includes/GitHub.Actions.Migrations.mk
include includes/Renovate.Migrations.mk
include includes/AI.Migrations.mk

## Our default jobs
include includes/EXTRA.mk
include includes/OnInstallUpdate.mk
include includes/PHP.mk
include includes/ContainerAccess.mk
include includes/Help.mk
include includes/TaskFinders.mk

## Catch-all for targets that pass through extra arguments (e.g. `make run ls`)
include includes/ExtraArguments.mk
