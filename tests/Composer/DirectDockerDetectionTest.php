<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Makefiles\Composer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use WyriHaximus\Makefiles\Composer\Installer;
use WyriHaximus\TestUtilities\TestCase;

final class DirectDockerDetectionTest extends TestCase
{
    #[Test]
    #[DataProvider('provideTargetCallsDockerDirectlyCases')]
    public function targetCallsDockerDirectly(string $makefileContents, string $target, bool $expected): void
    {
        self::assertSame(
            $expected,
            $this->invokeTargetCallsDockerDirectly($makefileContents, $target),
        );
    }

    /** @return iterable<string, array{string, string, bool}> */
    public static function provideTargetCallsDockerDirectlyCases(): iterable
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

    private function invokeTargetCallsDockerDirectly(string $makefileContents, string $target): bool
    {
        $method = new ReflectionMethod(Installer::class, 'targetCallsDockerDirectly');

        /** @var bool $result */
        $result = $method->invoke(null, $makefileContents, $target, []);

        return $result;
    }
}
