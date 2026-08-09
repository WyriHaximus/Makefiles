<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Makefiles\Composer\Installer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\Makefiles\Composer\Installer\Requirements;
use WyriHaximus\Makefiles\Composer\Installer\TaskListInjector;
use WyriHaximus\Makefiles\Composer\SupportedFeatures;
use WyriHaximus\Tests\Makefiles\Composer\Installer\TestUtilities\ProjectSandbox;
use WyriHaximus\TestUtilities\TestCase;

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
