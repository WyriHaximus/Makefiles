<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Makefiles\Composer\Installer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\Makefiles\Composer\Installer\Requirements;
use WyriHaximus\Makefiles\Composer\Installer\TaskListInjector;
use WyriHaximus\Makefiles\Composer\SupportedFeatures;
use WyriHaximus\Tests\Makefiles\Composer\Installer\TestUtilities\ProjectSandbox;
use WyriHaximus\Tests\Makefiles\TestCase;

final class TaskListInjectorTest extends TestCase
{
    /** @return iterable<string, array{string, array<string, bool>, list<string>, list<string>}> */
    public static function provideInjectCases(): iterable
    {
        $features                                        = SupportedFeatures::DEFAULTS;
        $features[SupportedFeatures::FEATURE_CODE_STYLE] = false;

        yield 'skips feature gated targets when disabled' => [
            "make-list(all)\nALL_HAS_DIRECT_DOCKER_TASKS=when_aggregate_has_direct_docker_tasks(all, TRUE, FALSE)\ngated: ## cs ##*I*##^code-style^##\n",
            $features,
            ['$(MAKE)  ## Count: 0'],
            ['$(MAKE) gated'],
        ];

        yield 'skips piped feature gated targets when any feature disabled' => [
            "make-list(all)\nALL_HAS_DIRECT_DOCKER_TASKS=when_aggregate_has_direct_docker_tasks(all, TRUE, FALSE)\npiped: ## cs ##*AI*##^code-style|unit-tests^##\nenabled: ## ok ##*A*##\n",
            $features,
            ['$(MAKE) enabled ## Count: 1'],
            ['$(MAKE) piped'],
        ];

        yield 'feature gate on first target does not skip second target' => [
            "make-list(all)\nmake-list(contrib)\nALL_HAS_DIRECT_DOCKER_TASKS=when_aggregate_has_direct_docker_tasks(all, TRUE, FALSE)\ngated: ## cs ##*A*##^code-style^##\nenabled: ## ok ##*A*##\ncontrib-task: ## contrib ##*E*##\n",
            $features,
            ['$(MAKE) enabled ## Count: 1', '$(MAKE) contrib-task ## Count: 1'],
            ['$(MAKE) gated'],
        ];

        yield 'injects task-list for multiple aggregates independently' => [
            <<<'MAKEFILE'
make-list(ci-dos)
task-list(ci-dos)
make-list(ci-low)
task-list(ci-low)
only-dos: ## dos ##*D*##
only-low: ## low only ##*L*##

MAKEFILE,
            SupportedFeatures::DEFAULTS,
            [
                '@echo "[\"only-dos\"]" ## Count: 1',
                '@echo "[\"only-low\"]" ## Count: 1',
                '$(MAKE) only-dos ## Count: 1',
                '$(MAKE) only-low ## Count: 1',
            ],
            [
                'task-list(ci-dos)',
                'task-list(ci-low)',
            ],
        ];

        $featuresWithoutUnitTests                                        = SupportedFeatures::DEFAULTS;
        $featuresWithoutUnitTests[SupportedFeatures::FEATURE_UNIT_TESTS] = false;

        yield 'ci-dos skips unit-tests when feature disabled' => [
            <<<'MAKEFILE'
make-list(ci-dos)
task-list(ci-dos)
unit-testing-raw: ## Run tests ##*D*##^unit-tests^##

MAKEFILE,
            $featuresWithoutUnitTests,
            [
                '@echo "[]" ## Count: 0',
                '$(MAKE)  ## Count: 0',
            ],
            [
                '\"unit-testing-raw\"',
                'task-list(ci-dos)',
            ],
        ];
    }

    #[Test]
    public function injectExactOutputForAggregatesAndDockerFlags(): void
    {
        $input = <<<'MAKEFILE'
make-list(all)
task-list(all)
make-list(on-install-or-update)
ALL_HAS_DIRECT_DOCKER_TASKS=when_aggregate_has_direct_docker_tasks(all, TRUE, FALSE)
alpha: ## all ##*AI*##
beta: #### dep ##*I*##
gamma: ## contrib ##*E*##
delta: ## dos ##*D*##
zeta: ## locked ##*C*##
eta: ## low ##*L*##
theta: ## high ##*H*##
docker-task: ## docker ##*I*##
	docker run image

MAKEFILE;

        $context = ProjectSandbox::context(
            $this->getTmpDir(),
            $this->getTmpDir(),
            new Requirements([], []),
            SupportedFeatures::DEFAULTS,
        );

        $expected = <<<'MAKEFILE'
$(MAKE) alpha zeta eta theta docker-task ## Count: 5
@echo "[\"alpha\",\"zeta\",\"eta\",\"theta\",\"docker-task\"]" ## Count: 5
$(MAKE) alpha beta docker-task ## Count: 3
ALL_HAS_DIRECT_DOCKER_TASKS=TRUE
alpha: ## all ##*AI*##
beta: #### dep ##*I*##
gamma: ## contrib ##*E*##
delta: ## dos ##*D*##
zeta: ## locked ##*C*##
eta: ## low ##*L*##
theta: ## high ##*H*##
docker-task: ## docker ##*I*##
	docker run image

MAKEFILE;

        self::assertSame($expected, TaskListInjector::inject($context, $input));
    }

    #[Test]
    public function injectReplacesCiLockedListWithExactOutputForKMarker(): void
    {
        $input = <<<'MAKEFILE'
make-list(ci-locked)
task-list(ci-locked)
documentation-markdownlint: ## Lint ##*K*##
documentation-links: ## Links ##*K*##

MAKEFILE;

        $context = ProjectSandbox::context(
            $this->getTmpDir(),
            $this->getTmpDir(),
            new Requirements([], []),
            SupportedFeatures::DEFAULTS,
        );

        $expected = <<<'MAKEFILE'
$(MAKE) documentation-markdownlint documentation-links ## Count: 2
@echo "[\"documentation-markdownlint\",\"documentation-links\"]" ## Count: 2
documentation-markdownlint: ## Lint ##*K*##
documentation-links: ## Links ##*K*##

MAKEFILE;

        self::assertSame($expected, TaskListInjector::inject($context, $input));
    }

    #[Test]
    public function injectExactOutputForKMarkerAcrossCiAggregatesAndDirectDockerFlags(): void
    {
        $input = <<<'MAKEFILE'
make-list(all)
task-list(all)
make-list(ci-all)
task-list(ci-all)
make-list(ci-locked)
task-list(ci-locked)
make-list(ci-dos)
task-list(ci-dos)
ALL_HAS_DIRECT_DOCKER_TASKS=when_aggregate_has_direct_docker_tasks(ci-locked, TRUE, FALSE)
DOCKER_RUN_DOCUMENTATION=docker run --rm -i image:tag
IMAGE_MARKDOWNLINT := markdown:tag
IMAGE_LYCHEE := lychee:tag

documentation-markdownlint: ## Lint ##*K*##
	$(DOCKER_RUN_DOCUMENTATION) $(IMAGE_MARKDOWNLINT) --config etc/qa/documentation.markdownlint-cli2.yaml

documentation-links: ## Links ##*K*##
	$(DOCKER_RUN_DOCUMENTATION) $(IMAGE_LYCHEE) --config etc/qa/lychee.toml .
unit-testing-raw: ## Run tests ##*D*##
	php vendor/bin/phpunit

MAKEFILE;

        $context = ProjectSandbox::context(
            $this->getTmpDir(),
            $this->getTmpDir(),
            new Requirements([], []),
            SupportedFeatures::DEFAULTS,
        );

        $expected = <<<'MAKEFILE'
$(MAKE)  ## Count: 0
@echo "[]" ## Count: 0
$(MAKE)  ## Count: 0
@echo "[]" ## Count: 0
$(MAKE) documentation-markdownlint documentation-links ## Count: 2
@echo "[\"documentation-markdownlint\",\"documentation-links\"]" ## Count: 2
$(MAKE) unit-testing-raw ## Count: 1
@echo "[\"unit-testing-raw\"]" ## Count: 1
ALL_HAS_DIRECT_DOCKER_TASKS=TRUE
DOCKER_RUN_DOCUMENTATION=docker run --rm -i image:tag
IMAGE_MARKDOWNLINT := markdown:tag
IMAGE_LYCHEE := lychee:tag

documentation-markdownlint: ## Lint ##*K*##
	$(DOCKER_RUN_DOCUMENTATION) $(IMAGE_MARKDOWNLINT) --config etc/qa/documentation.markdownlint-cli2.yaml

documentation-links: ## Links ##*K*##
	$(DOCKER_RUN_DOCUMENTATION) $(IMAGE_LYCHEE) --config etc/qa/lychee.toml .
unit-testing-raw: ## Run tests ##*D*##
	php vendor/bin/phpunit

MAKEFILE;

        self::assertSame($expected, TaskListInjector::inject($context, $input));
    }

    #[Test]
    public function injectExactOutputForHashCountMapOnInstallTargets(): void
    {
        $input = <<<'MAKEFILE'
make-list(all)
task-list(all)
make-list(ci-all)
task-list(ci-all)
make-list(on-install-or-update)
task-list(on-install-or-update)
two-hash-i: ## two ##*I*##
four-hash-i: #### four ##*I*##

MAKEFILE;

        $context = ProjectSandbox::context(
            $this->getTmpDir(),
            $this->getTmpDir(),
            new Requirements([], []),
            SupportedFeatures::DEFAULTS,
        );

        $expected = <<<'MAKEFILE'
$(MAKE) two-hash-i ## Count: 1
@echo "[\"two-hash-i\"]" ## Count: 1
$(MAKE)  ## Count: 0
@echo "[]" ## Count: 0
$(MAKE) two-hash-i four-hash-i ## Count: 2
@echo "[\"two-hash-i\",\"four-hash-i\"]" ## Count: 2
two-hash-i: ## two ##*I*##
four-hash-i: #### four ##*I*##

MAKEFILE;

        self::assertSame($expected, TaskListInjector::inject($context, $input));
    }

    /**
     * @param array<string, bool> $features
     * @param list<string>        $contains
     * @param list<string>        $notContains
     */
    #[Test]
    #[DataProvider('provideInjectCases')]
    public function inject(string $input, array $features, array $contains, array $notContains): void
    {
        $tmpdir  = $this->getTmpDir();
        $context = ProjectSandbox::context($tmpdir, $tmpdir, new Requirements([], []), $features);
        $result  = TaskListInjector::inject($context, $input);

        foreach ($contains as $needle) {
            self::assertStringContainsString($needle, $result);
        }

        foreach ($notContains as $needle) {
            self::assertStringNotContainsString($needle, $result);
        }
    }
}
