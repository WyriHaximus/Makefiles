<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Makefiles\Composer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use RuntimeException;
use WyriHaximus\Makefiles\Composer\Installer;
use WyriHaximus\TestUtilities\TestCase;

use function array_any;

final class TaskAggregateDirectDockerTest extends TestCase
{
    /** @return iterable<string, array{0: string, 1: list<string>, 2: bool}> */
    public static function taskAggregateCallsDockerDirectlyProvider(): iterable
    {
        yield 'docker run variable' => [
            "target:\n\t\$(DOCKER_RUN) vendor/bin/phpunit\n",
            ['target'],
            false,
        ];

        yield 'docker shell variable' => [
            "target:\n\t\$(DOCKER_SHELL) composer validate\n",
            ['target'],
            false,
        ];

        yield 'host native command' => [
            "target:\n\tphp vendor/bin/phpunit\n",
            ['target'],
            false,
        ];

        yield 'make delegation to docker abstraction child' => [
            "parent:\n\t\$(MAKE) child || true\nchild:\n\t\$(DOCKER_SHELL) vendor/bin/roave-backward-compatibility-check\n",
            ['parent'],
            false,
        ];

        yield 'literal docker command' => [
            "target:\n\tdocker run --rm alpine echo hi\n",
            ['target'],
            true,
        ];

        yield 'mixed abstraction and literal docker aggregate' => [
            "docker-target:\n\t\$(DOCKER_RUN) vendor/bin/phpunit\nterraform-fmt:\n\tdocker run -i hashicorp/terraform:1.14.8 fmt\n",
            ['docker-target', 'terraform-fmt'],
            true,
        ];

        yield 'abstraction-only aggregate' => [
            "one:\n\t\$(DOCKER_RUN) vendor/bin/phpunit\nother:\n\t\$(DOCKER_SHELL) composer validate\n",
            ['one', 'other'],
            false,
        ];

        yield 'empty target list' => [
            "target:\n\t\$(DOCKER_RUN) vendor/bin/phpunit\n",
            [],
            false,
        ];

        yield 'unknown target' => [
            "other:\n\t\$(DOCKER_RUN) vendor/bin/phpunit\n",
            ['missing'],
            false,
        ];

        yield 'make delegation cycle' => [
            "a:\n\t\$(MAKE) b\nb:\n\t\$(MAKE) a\n",
            ['a'],
            false,
        ];
    }

    /** @param list<string> $targets */
    #[Test]
    #[DataProvider('taskAggregateCallsDockerDirectlyProvider')]
    public function taskAggregateCallsDockerDirectly(string $makefileContents, array $targets, bool $expected): void
    {
        self::assertSame(
            $expected,
            array_any(
                $targets,
                fn (string $target): bool => $this->targetCallsDockerDirectly($makefileContents, $target),
            ),
        );
    }

    #[Test]
    public function injectAggregateDirectDockerFlagsResolvesPlaceholders(): void
    {
        $makefileContents = <<<'MAKEFILE'
ALL_HAS_DIRECT_DOCKER_TASKS=when_aggregate_has_direct_docker_tasks(all, TRUE, FALSE)
CONTRIB_HAS_DIRECT_DOCKER_TASKS=when_aggregate_has_direct_docker_tasks(contrib, TRUE, FALSE)

docker-target:
	$(DOCKER_RUN) vendor/bin/phpunit

host-target:
	php vendor/bin/phpunit
MAKEFILE;

        $result = $this->invokePrivateStatic(
            'injectAggregateDirectDockerFlags',
            $makefileContents,
            [
                'all' => ['docker-target'],
                'contrib' => ['host-target'],
            ],
        );

        self::assertIsString($result);
        self::assertStringContainsString('ALL_HAS_DIRECT_DOCKER_TASKS=FALSE', $result);
        self::assertStringContainsString('CONTRIB_HAS_DIRECT_DOCKER_TASKS=FALSE', $result);
    }

    #[Test]
    public function injectAggregateDirectDockerFlagsDetectsLiteralDockerInAggregate(): void
    {
        $makefileContents = "ALL_HAS_DIRECT_DOCKER_TASKS=when_aggregate_has_direct_docker_tasks(all, TRUE, FALSE)\n\n"
            . "docker-target:\n\t\$(DOCKER_RUN) vendor/bin/phpunit\n\n"
            . "terraform-fmt:\n\tdocker run -i hashicorp/terraform:1.14.8 fmt\n";

        $result = $this->invokePrivateStatic(
            'injectAggregateDirectDockerFlags',
            $makefileContents,
            [
                'all' => ['docker-target', 'terraform-fmt'],
            ],
        );

        self::assertIsString($result);
        self::assertStringContainsString('ALL_HAS_DIRECT_DOCKER_TASKS=TRUE', $result);
    }

    #[Test]
    public function injectAggregateDirectDockerFlagsResolvesOnInstallOrUpdatePlaceholder(): void
    {
        $makefileContents = "ON_INSTALL_OR_UPDATE_HAS_DIRECT_DOCKER_TASKS=when_aggregate_has_direct_docker_tasks(on-install-or-update, TRUE, FALSE)\n\n"
            . "docker-target:\n\t\$(DOCKER_RUN) vendor/bin/phpunit\n\n"
            . "terraform-fmt:\n\tdocker run -i hashicorp/terraform:1.14.8 fmt\n";

        $result = $this->invokePrivateStatic(
            'injectAggregateDirectDockerFlags',
            $makefileContents,
            [
                'on-install-or-update' => ['docker-target', 'terraform-fmt'],
            ],
        );

        self::assertIsString($result);
        self::assertStringContainsString('ON_INSTALL_OR_UPDATE_HAS_DIRECT_DOCKER_TASKS=TRUE', $result);
    }

    #[Test]
    public function injectAggregateDirectDockerFlagsThrowsForUnknownAggregate(): void
    {
        self::expectException(RuntimeException::class);
        self::expectExceptionMessageIsOrContains('Unknown task aggregate for direct docker detection: unknown');

        $this->invokePrivateStatic(
            'injectAggregateDirectDockerFlags',
            'FLAG=when_aggregate_has_direct_docker_tasks(unknown, TRUE, FALSE)',
            ['all' => []],
        );
    }

    private function invokePrivateStatic(string $methodName, mixed ...$arguments): mixed
    {
        $method = new ReflectionMethod(Installer::class, $methodName);

        return $method->invoke(null, ...$arguments);
    }

    private function targetCallsDockerDirectly(string $makefileContents, string $target): bool
    {
        $result = $this->invokePrivateStatic('targetCallsDockerDirectly', $makefileContents, $target, []);
        self::assertIsBool($result);

        return $result;
    }
}
