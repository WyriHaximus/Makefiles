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
        yield 'builds aggregates and docker flags' => [
            <<<'MAKEFILE'
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

MAKEFILE,
            SupportedFeatures::DEFAULTS,
            ['$(MAKE) alpha zeta eta theta docker-task', '$(MAKE) alpha beta docker-task ## Count: 3', 'ALL_HAS_DIRECT_DOCKER_TASKS=TRUE'],
            [],
        ];

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

        yield 'K marker injects ci-locked only not all ci-all or ci-dos' => [
            <<<'MAKEFILE'
make-list(all)
task-list(all)
make-list(ci-locked)
task-list(ci-locked)
make-list(ci-dos)
task-list(ci-dos)
documentation-markdownlint: ## Lint markdown structure ##*K*##
unit-testing-raw: ## Run tests ##*D*##^unit-tests^##

MAKEFILE,
            SupportedFeatures::DEFAULTS,
            [
                '@echo "[\"documentation-markdownlint\"]" ## Count: 1',
                '$(MAKE) documentation-markdownlint ## Count: 1',
                '$(MAKE) unit-testing-raw ## Count: 1',
                '@echo "[\"unit-testing-raw\"]" ## Count: 1',
            ],
            [
                'make-list(all)',
                'task-list(all)',
                'task-list(ci-locked)',
                'make-list(ci-locked)',
                'task-list(ci-dos)',
                'make-list(ci-dos)',
                'documentation-markdownlint","unit-testing-raw',
            ],
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
    public function injectReplacesCiLockedListWithExactOutputForKMarker(): void
    {
        $input = <<<'MAKEFILE'
make-list(ci-locked)
task-list(ci-locked)
documentation-markdownlint: ## Lint ##*K*##

MAKEFILE;

        $context = ProjectSandbox::context(
            $this->getTmpDir(),
            $this->getTmpDir(),
            new Requirements([], []),
            SupportedFeatures::DEFAULTS,
        );

        $expected = <<<'MAKEFILE'
$(MAKE) documentation-markdownlint ## Count: 1
@echo "[\"documentation-markdownlint\"]" ## Count: 1
documentation-markdownlint: ## Lint ##*K*##

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
