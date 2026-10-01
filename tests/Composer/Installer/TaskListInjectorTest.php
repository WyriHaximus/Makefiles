<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Makefiles\Composer\Installer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
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

        yield 'skips feature gated targets with prerequisites when disabled' => [
            <<<'MAKEFILE'
make-list(contrib)
task-list(contrib)
documentation-qa: documentation-markdownlint ## Run all ##*E*##^code-style^##
documentation-markdownlint: ## Lint ##*K*##
enabled-contrib: ## ok ##*E*##

MAKEFILE,
            $features,
            [
                '$(MAKE) enabled-contrib ## Count: 1',
                '@echo "[\"enabled-contrib\"]" ## Count: 1',
            ],
            [
                '$(MAKE) documentation-qa',
                'documentation-markdownlint ## Count',
            ],
        ];

        yield 'includes feature gated targets with prerequisites when enabled' => [
            <<<'MAKEFILE'
make-list(contrib)
task-list(contrib)
documentation-qa: documentation-markdownlint ## Run all ##*E*##^code-style^##
documentation-markdownlint: ## Lint ##*K*##
enabled-contrib: ## ok ##*E*##

MAKEFILE,
            SupportedFeatures::DEFAULTS,
            [
                '$(MAKE) documentation-qa enabled-contrib ## Count: 2',
                '@echo "[\"documentation-qa\",\"enabled-contrib\"]" ## Count: 2',
            ],
            [],
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
    }

    #[Test]
    public function injectExactOutputForMultipleCiAggregates(): void
    {
        $input = <<<'MAKEFILE'
make-list(ci-dos)
task-list(ci-dos)
make-list(ci-low)
task-list(ci-low)
only-dos: ## dos ##*D*##
only-low: ## low only ##*L*##

MAKEFILE;

        $context = ProjectSandbox::context(
            $this->getTmpDir(),
            $this->getTmpDir(),
            new Requirements([], []),
            SupportedFeatures::DEFAULTS,
        );

        $expected = <<<'MAKEFILE'
$(MAKE) only-dos ## Count: 1
@echo "[\"only-dos\"]" ## Count: 1
$(MAKE) only-low ## Count: 1
@echo "[\"only-low\"]" ## Count: 1
only-dos: ## dos ##*D*##
only-low: ## low only ##*L*##

MAKEFILE;

        self::assertSame($expected, TaskListInjector::inject($context, $input));
    }

    #[Test]
    public function injectExactOutputWhenUnitTestsFeatureDisabledSkipsDosTarget(): void
    {
        $input = <<<'MAKEFILE'
make-list(ci-dos)
task-list(ci-dos)
unit-testing-raw: ## Run tests ##*D*##^unit-tests^##

MAKEFILE;

        $features                                        = SupportedFeatures::DEFAULTS;
        $features[SupportedFeatures::FEATURE_UNIT_TESTS] = false;

        $context = ProjectSandbox::context(
            $this->getTmpDir(),
            $this->getTmpDir(),
            new Requirements([], []),
            $features,
        );

        $expected = <<<'MAKEFILE'
$(MAKE)  ## Count: 0
@echo "[]" ## Count: 0
unit-testing-raw: ## Run tests ##*D*##^unit-tests^##

MAKEFILE;

        self::assertSame($expected, TaskListInjector::inject($context, $input));
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
documentation-typos: ## Typos ##*K*##

MAKEFILE;

        $context = ProjectSandbox::context(
            $this->getTmpDir(),
            $this->getTmpDir(),
            new Requirements([], []),
            SupportedFeatures::DEFAULTS,
        );

        $expected = <<<'MAKEFILE'
$(MAKE) documentation-markdownlint documentation-links documentation-typos ## Count: 3
@echo "[\"documentation-markdownlint\",\"documentation-links\",\"documentation-typos\"]" ## Count: 3
documentation-markdownlint: ## Lint ##*K*##
documentation-links: ## Links ##*K*##
documentation-typos: ## Typos ##*K*##

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
IMAGE_CSPELL := cspell:tag

documentation-markdownlint: ## Lint ##*K*##
	$(DOCKER_RUN_DOCUMENTATION) $(IMAGE_MARKDOWNLINT) --config etc/qa/documentation.markdownlint-cli2.yaml

documentation-links: ## Links ##*K*##
	$(DOCKER_RUN_DOCUMENTATION) $(IMAGE_LYCHEE) --config etc/qa/lychee.toml .

documentation-typos: ## Typos ##*K*##
	$(DOCKER_RUN_DOCUMENTATION) $(IMAGE_CSPELL) --config etc/qa/cspell.json .
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
$(MAKE) documentation-markdownlint documentation-links documentation-typos ## Count: 3
@echo "[\"documentation-markdownlint\",\"documentation-links\",\"documentation-typos\"]" ## Count: 3
$(MAKE) unit-testing-raw ## Count: 1
@echo "[\"unit-testing-raw\"]" ## Count: 1
ALL_HAS_DIRECT_DOCKER_TASKS=TRUE
DOCKER_RUN_DOCUMENTATION=docker run --rm -i image:tag
IMAGE_MARKDOWNLINT := markdown:tag
IMAGE_LYCHEE := lychee:tag
IMAGE_CSPELL := cspell:tag

documentation-markdownlint: ## Lint ##*K*##
	$(DOCKER_RUN_DOCUMENTATION) $(IMAGE_MARKDOWNLINT) --config etc/qa/documentation.markdownlint-cli2.yaml

documentation-links: ## Links ##*K*##
	$(DOCKER_RUN_DOCUMENTATION) $(IMAGE_LYCHEE) --config etc/qa/lychee.toml .

documentation-typos: ## Typos ##*K*##
	$(DOCKER_RUN_DOCUMENTATION) $(IMAGE_CSPELL) --config etc/qa/cspell.json .
unit-testing-raw: ## Run tests ##*D*##
	php vendor/bin/phpunit

MAKEFILE;

        self::assertSame($expected, TaskListInjector::inject($context, $input));
    }

    #[Test]
    public function injectExactOutputForPipedFeatureGateWhenFirstFeatureDisabled(): void
    {
        $input = <<<'MAKEFILE'
make-list(all)
task-list(all)
piped: ## piped ##*A*##^code-style|unit-tests^##
enabled: ## ok ##*A*##

MAKEFILE;

        $features                                        = SupportedFeatures::DEFAULTS;
        $features[SupportedFeatures::FEATURE_CODE_STYLE] = false;

        $context = ProjectSandbox::context(
            $this->getTmpDir(),
            $this->getTmpDir(),
            new Requirements([], []),
            $features,
        );

        $expected = <<<'MAKEFILE'
$(MAKE) enabled ## Count: 1
@echo "[\"enabled\"]" ## Count: 1
piped: ## piped ##*A*##^code-style|unit-tests^##
enabled: ## ok ##*A*##

MAKEFILE;

        self::assertSame($expected, TaskListInjector::inject($context, $input));
    }

    #[Test]
    public function injectExactOutputForUnknownFeatureInPipeSkipsTarget(): void
    {
        $input = <<<'MAKEFILE'
make-list(all)
task-list(all)
piped: ## piped ##*A*##^not-a-known-feature^##
enabled: ## ok ##*A*##

MAKEFILE;

        $context = ProjectSandbox::context(
            $this->getTmpDir(),
            $this->getTmpDir(),
            new Requirements([], []),
            SupportedFeatures::DEFAULTS,
        );

        $expected = <<<'MAKEFILE'
$(MAKE) enabled ## Count: 1
@echo "[\"enabled\"]" ## Count: 1
piped: ## piped ##*A*##^not-a-known-feature^##
enabled: ## ok ##*A*##

MAKEFILE;

        self::assertSame($expected, TaskListInjector::inject($context, $input));
    }

    #[Test]
    public function injectExactOutputForFeatureGateWhenFeatureDisabled(): void
    {
        $input = <<<'MAKEFILE'
make-list(all)
task-list(all)
gated: ## cs ##*A*##^code-style^##
enabled: ## ok ##*A*##

MAKEFILE;

        $features                                        = SupportedFeatures::DEFAULTS;
        $features[SupportedFeatures::FEATURE_CODE_STYLE] = false;

        $context = ProjectSandbox::context(
            $this->getTmpDir(),
            $this->getTmpDir(),
            new Requirements([], []),
            $features,
        );

        $expected = <<<'MAKEFILE'
$(MAKE) enabled ## Count: 1
@echo "[\"enabled\"]" ## Count: 1
gated: ## cs ##*A*##^code-style^##
enabled: ## ok ##*A*##

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

    #[Test]
    public function matchedLineIsHelpAnnotatedTargetRequiresHelpTypeMarker(): void
    {
        $method = new ReflectionMethod(TaskListInjector::class, 'matchedLineIsHelpAnnotatedTarget');

        self::assertTrue($method->invoke(null, 'target: ## desc ##*A*##'));
        self::assertFalse($method->invoke(null, 'target: ## desc without type marker'));
    }

    #[Test]
    public function helpAnnotatedMatchIndicesSkipsLinesWithoutHelpTypeMarker(): void
    {
        $fullLineMatches = [
            0 => ['decoy: no marker', 0],
            1 => ['good: ## x ##*A*##', 10],
        ];
        $method          = new ReflectionMethod(TaskListInjector::class, 'helpAnnotatedMatchIndices');

        self::assertSame([1], $method->invoke(null, $fullLineMatches));
    }

    #[Test]
    public function allPipedFeaturesEnabledRequiresEveryFeatureInPipe(): void
    {
        $method                                          = new ReflectionMethod(TaskListInjector::class, 'allPipedFeaturesEnabled');
        $features                                        = SupportedFeatures::DEFAULTS;
        $features[SupportedFeatures::FEATURE_CODE_STYLE] = false;

        self::assertFalse($method->invoke(null, 'code-style|unit-tests', $features));
        self::assertTrue($method->invoke(null, 'unit-tests', $features));
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
