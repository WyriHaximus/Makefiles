# Makefiles

Shared [GNU Make](https://www.gnu.org/software/make/) targets and a [Composer](https://getcomposer.org/) plugin that generates a project `Makefile` for PHP libraries and applications maintained under [WyriHaximus](https://github.com/WyriHaximus).

![Continuous Integration](https://github.com/wyrihaximus/makefiles/workflows/Continuous%20Integration/badge.svg)
[![Latest Stable Version](https://poser.pugx.org/wyrihaximus/makefiles/v/stable.png)](https://packagist.org/packages/wyrihaximus/makefiles)
[![Total Downloads](https://poser.pugx.org/wyrihaximus/makefiles/downloads.png)](https://packagist.org/packages/wyrihaximus/makefiles/stats)
[![Type Coverage](https://shepherd.dev/github/WyriHaximus/makefiles/coverage.svg)](https://shepherd.dev/github/WyriHaximus/makefiles)
[![License](https://poser.pugx.org/wyrihaximus/makefiles/license.png)](https://packagist.org/packages/wyrihaximus/makefiles)

## What it does

When you add this package as a **development** dependency, the Composer plugin runs on `composer install` and `composer update` (during autoload dump) and writes a root **`Makefile`** tailored to your project. That file is generated from [`templates/Makefile.PHP`](templates/Makefile.PHP); you normally edit your project's `etc/Makefile` for project-specific targets instead of hand-maintaining the root file.

The generated `Makefile` wires up:

- **Local or containerized PHP** using [ghcr.io/wyrihaximusnet/php](https://github.com/WyriHaximus/docker-php) images when [Docker](https://www.docker.com/) is available, or direct execution when you are already inside those images or Docker is not installed.
- **Aggregate workflows** such as `make` / `make all` (full QA) and `make contrib` (a smaller subset suited to day-to-day contributions).
- **Discoverable help** via `make help`, `make help-contrib`, and `make help-migrations`.
- **GitHub Actions job lists** as JSON from targets like `make task-list-ci-locked` for matrix CI pipelines.
- **Post-install maintenance** via `make on-install-or-update`, which runs migration targets and hooks used by [Renovate](https://docs.renovatebot.com/) in self-hosted setups.

Composer also registers `post-install-cmd` / `post-update-cmd` scripts that run `make on-install-or-update` when possible, so new checkouts stay aligned with expected config layout under `etc/qa/`, `etc/ci/`, and related paths.

## Installation

Require the package as a dev dependency (the plugin only activates when `wyrihaximus/makefiles` is listed under `require-dev`, or when working inside this repository itself):

```bash
composer require --dev wyrihaximus/makefiles
```

Allow the plugin in `composer.json`:

```json
"config": {
    "allow-plugins": {
        "wyrihaximus/makefiles": true
    }
}
```

Then run `composer update` (or `make install` once a `Makefile` exists) so the root `Makefile` is generated.

## Everyday usage

| Command | Purpose |
| --- | --- |
| `make` / `make all` | Run the full QA pipeline for the project |
| `make contrib` | Run a contributor-focused subset (see `make help-contrib`) |
| `make help` | List available targets with short descriptions |
| `make install` / `make update` | Install or update Composer dependencies in the expected environment |
| `make run …` | Run an arbitrary command inside the PHP container (or locally when Docker is unused) |
| `make shell` | Interactive shell in the PHP environment |
| `make unit-testing-filter …` | Run PHPUnit with a class or method filter |

See [CONTRIBUTING.md](CONTRIBUTING.md) for the contribution workflow used across WyriHaximus PHP repositories.

## What is included

Makefile fragments live under [`includes/`](includes/). They are merged into your generated `Makefile` at install time.

### PHP quality assurance ([`includes/PHP.mk`](includes/PHP.mk))

Targets for tooling typically configured under `etc/qa/`, including:

- Syntax lint ([`parallel-lint`](https://github.com/php-parallel-lint/PHP-Parallel-Lint))
- Code style ([PHP_CodeSniffer](https://github.com/PHPCSStandards/PHP_CodeSniffer)) via `cs` / `cs-fix`
- Static analysis ([PHPStan](https://phpstan.org/)) via `stan`
- Unit tests and coverage enforcement ([PHPUnit](https://phpunit.de/), [coverage-guard](https://github.com/shipmonk-rnd/coverage-guard)) via `unit-testing`, `coverage-guard`
- Mutation testing ([Infection](https://infection.github.io/)) via `mutation-testing`
- Composer hygiene: `composer-validate`, `composer-normalize`, `composer-require-checker`, `composer-unused`, dependency helpers
- Refactoring upgrades ([Rector](https://getrector.com/)) via `rector-upgrade`
- Backward compatibility checks ([Roave](https://github.com/Roave/BackwardCompatibilityCheck)) where configured

Many targets honor **supported features** (described under [Configuration](#configuration)) so CI can skip jobs your package does not support (for example when unit tests or PHP_CodeSniffer are turned off).

### Documentation QA ([`includes/Documentation.QA.mk`](includes/Documentation.QA.mk))

`documentation-qa` runs markdown structure lint ([markdownlint-cli2](https://github.com/DavidAnson/markdownlint-cli2)), link checking ([lychee](https://github.com/lycheeverse/lychee)), spelling ([cspell](https://cspell.org/)), and prose lint ([Vale](https://vale.sh/)) against project docs.

### Container access ([`includes/ContainerAccess.mk`](includes/ContainerAccess.mk))

`run` and `shell` targets execute commands in the selected PHP image, with volume mounts for the workspace, Composer cache, optional Docker socket access for [Testcontainers](https://testcontainers.com/) when required, and sensible defaults for CI versus interactive TTY use.

### Aggregates and help

- [`includes/All.mk`](includes/All.mk) and [`includes/Contrib.mk`](includes/Contrib.mk) define `all` and `contrib` from annotated targets in other fragments.
- [`includes/Help.mk`](includes/Help.mk) and [`includes/TaskFinders.mk`](includes/TaskFinders.mk) power help output and CI task list generation (`task-list-ci-*`, `supported-features`).

### Migrations ([`includes/*Migrations*.mk`](includes/))

Idempotent `migrations-*` targets normalize repository layout and tool configs (PHPUnit, PHPStan, PHPCS, Infection, GitHub Actions, Renovate, documentation, AI agent files, and more). They are collected into `on-install-or-update` so existing repos can adopt new conventions without manual copy-paste. Use `make help-migrations` to see migration targets.

### Project-specific targets

The template includes `includes/EXTRA.mk`, which is resolved from your repository’s **`etc/Makefile`**. Put custom targets, Docker Compose service hooks (`extra-services-up` / `extra-services-down`), and lifecycle hooks there; they are inlined when the root `Makefile` is generated.

## Configuration

### Supported features matrix

Declare which capabilities apply to your package under `extra.wyrihaximus.supported-features` in `composer.json`. The plugin resolves defaults from your requirements (for example `ext-parallel` excludes macOS and Windows CI variants and enables ZTS) and prints the matrix during generation.

Available feature keys (see [`SupportedFeatures`](src/Composer/SupportedFeatures.php)):

| Feature | Default | Typical use |
| --- | --- | --- |
| `code-style` | enabled | PHPCS targets and related CI jobs |
| `static-analysis` | enabled | PHPStan and mutation testing CI jobs |
| `unit-tests` | enabled | PHPUnit and coverage jobs |
| `composer-dependency-checkers` | enabled | `composer-require-checker` / `composer-unused` |
| `composer-plugin` | off | Set `true` for Composer plugins that need plugin-specific CI |
| `linux` / `macos` / `windows` | enabled | OS dimensions in CI task lists |
| `zts` | auto from `ext-parallel` | ZTS PHP Docker image selection |
| `opentelemetry-instrumentation` | auto from OTel setup | Docker OpenTelemetry env tuning |

Example:

```json
"extra": {
    "wyrihaximus": {
        "supported-features": {
            "composer-plugin": true
        }
    }
}
```

### Requirements-aware conditionals

The generator inspects `composer.json` requirements and adjusts the `Makefile` (PHP version platform, slim versus full Docker images, ZTS, Testcontainers socket access, and similar) so generated commands match what the project actually needs.

## CI integration

Annotated targets carry markers (for example `##*LCH*##`) that [`TaskListInjector`](src/Composer/Installer/TaskListInjector.php) turns into JSON job lists. GitHub Actions workflows in consuming repositories call `make task-list-ci-locked`, `make task-list-ci-low`, `make task-list-ci-high`, or related targets to build matrices for locked, lowest, and highest dependency runs across supported operating systems.

Run `make supported-features` locally to inspect the feature flags CI uses to filter jobs.

## License

The MIT License (MIT)

Copyright (c) 2026 Cees-Jan Kiewiet

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
