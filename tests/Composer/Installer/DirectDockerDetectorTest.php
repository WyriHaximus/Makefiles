<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Makefiles\Composer\Installer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WyriHaximus\Makefiles\Composer\Installer\DirectDockerDetector;
use WyriHaximus\TestUtilities\TestCase;

final class DirectDockerDetectorTest extends TestCase
{
    #[Test]
    #[DataProvider('provideTargetUsesDockerCases')]
    public function targetUsesDocker(string $makefileContents, string $target, bool $expected): void
    {
        self::assertSame($expected, DirectDockerDetector::targetUsesDocker($makefileContents, $target));
    }

    /** @return iterable<string, array{string, string, bool}> */
    public static function provideTargetUsesDockerCases(): iterable
    {
        yield 'literal docker in recipe' => [
            "terraform-fmt: ## fmt ##*I*##\n\tdocker run --rm hashicorp/terraform:1.14.8 fmt\n",
            'terraform-fmt',
            true,
        ];

        yield 'project docker wrapper variable' => [
            "TERRAFORM=docker run -i --network=host hashicorp/terraform:1.14.8\n\nterraform-fmt: ## fmt ##*I*##\n\t\$(TERRAFORM) -chdir=./.terraform/dev fmt\n",
            'terraform-fmt',
            true,
        ];

        yield 'project docker wrapper variable brace syntax' => [
            "TERRAFORM=docker run -i hashicorp/terraform:1.14.8\n\nterraform-fmt: ## fmt ##*I*##\n\t\${TERRAFORM} -chdir=./.terraform/dev fmt\n",
            'terraform-fmt',
            true,
        ];

        yield 'framework docker run variable is ignored' => [
            "DOCKER_RUN:=docker run --rm ghcr.io/example/php:8.4-dev\n\ncomposer-normalize: ## normalize ##*I*##\n\t\$(DOCKER_RUN) composer normalize\n",
            'composer-normalize',
            false,
        ];

        yield 'make sub-target recursion finds docker' => [
            "terraform-fmt: ## fmt ##*I*##\n\t\$(MAKE) terraform-fmt-raw\n\nterraform-fmt-raw: ####\n\tdocker run --rm hashicorp/terraform:1.14.8 fmt\n",
            'terraform-fmt',
            true,
        ];

        yield 'circular make delegation returns false' => [
            "terraform-fmt: ## fmt ##*I*##\n\t\$(MAKE) terraform-fmt\n",
            'terraform-fmt',
            false,
        ];

        yield 'unknown target returns false' => [
            "other: ## x ##\n\tdocker run --rm image cmd\n",
            'missing-target',
            false,
        ];

        yield 'non-docker recipe' => [
            "update-k6-repositories: ## update ##*I*##\n\tphp bin/update-k6-repositories.php\n",
            'update-k6-repositories',
            false,
        ];

        yield 'service lifecycle ifeq block recurses to docker compose' => [
            <<<'MAKEFILE'
before-unit-tests-service: ####
	docker compose up -d --wait

unit-testing: ## Run tests ##*AE*##
ifeq ("$(IN_CI)","TRUE")
	$(DOCKER_RUN_WITH_SOCKET) vendor/bin/phpunit
else
	@bash -ec '$(MAKE) before-unit-tests-service; trap "$(MAKE) after-unit-tests-service || true" EXIT; $(DOCKER_RUN_WITH_SOCKET) vendor/bin/phpunit'
endif
MAKEFILE,
            'unit-testing',
            true,
        ];
    }

    /** @return iterable<string, array{string, array<string, list<string>>, string, bool}> */
    public static function provideInjectFlagsCases(): iterable
    {
        yield 'docker tasks present' => [
            "ALL_HAS_DIRECT_DOCKER_TASKS=when_aggregate_has_direct_docker_tasks(all, TRUE, FALSE)\n\nall-target: ## x ##\n\tdocker build .\n",
            ['all' => ['all-target']],
            'ALL_HAS_DIRECT_DOCKER_TASKS=TRUE',
            false,
        ];

        yield 'no docker tasks' => [
            "ALL_HAS_DIRECT_DOCKER_TASKS=when_aggregate_has_direct_docker_tasks(all, TRUE, FALSE)\n\nall-target: ## x ##\n\tphp bin/test.php\n",
            ['all' => ['all-target']],
            'ALL_HAS_DIRECT_DOCKER_TASKS=FALSE',
            false,
        ];

        yield 'unknown aggregate' => [
            'FLAG=when_aggregate_has_direct_docker_tasks(missing, TRUE, FALSE)',
            ['all' => []],
            '',
            true,
        ];
    }

    /** @param array<string, list<string>> $aggregates */
    #[Test]
    #[DataProvider('provideInjectFlagsCases')]
    public function injectFlags(string $makefile, array $aggregates, string $expectedFragment, bool $throws): void
    {
        if ($throws) {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageIsOrContains('Unknown task aggregate for direct docker detection: missing');
        }

        $result = DirectDockerDetector::injectFlags($makefile, $aggregates);

        if ($throws) {
            return;
        }

        self::assertStringContainsString($expectedFragment, $result);
    }
}
