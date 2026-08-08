<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Makefiles\Composer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use WyriHaximus\Makefiles\Composer\Installer;
use WyriHaximus\TestUtilities\TestCase;

use function str_replace;

final class ServiceLifecycleTest extends TestCase
{
    #[Test]
    #[DataProvider('provideInjectServiceLifecycleCases')]
    public function injectServiceLifecycle(
        string $makefileContents,
        string $expectedNeedle,
        string $unexpectedNeedle,
    ): void {
        $result = $this->invokeInjectServiceLifecycle($makefileContents, $this->getTmpDir());

        self::assertStringContainsString($expectedNeedle, $result);
        self::assertStringNotContainsString($unexpectedNeedle, $result);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function provideInjectServiceLifecycleCases(): iterable
    {
        $template = <<<'MAKEFILE'
extra-services-up: ####
	docker compose up -d --wait

extra-services-wait: ####
	docker compose exec -T svc await_startup

extra-services-down: ####
	docker compose down

unit-testing: ## Run tests ##*AE*##^unit-tests^##
	service_start(extra-services-up)
	service_start(extra-services-wait)
	$(DOCKER_RUN_WITH_SOCKET) vendor/bin/phpunit --colors=always -c ./etc/qa/phpunit.xml
	service_cleanup(extra-services-down)
MAKEFILE;

        yield 'full services' => [
            $template,
            'ifeq ("$(IN_CI)","TRUE")',
            'service_start(',
        ];

        yield 'full services local lifecycle' => [
            $template,
            'trap "$(MAKE) extra-services-down || true" EXIT',
            'service_start(',
        ];

        yield 'up and down only' => [
            str_replace(
                "extra-services-wait: ####\n\tdocker compose exec -T svc await_startup\n\n",
                '',
                $template,
            ),
            '$(MAKE) extra-services-up;',
            'extra-services-wait',
        ];

        yield 'no services' => [
            <<<'MAKEFILE'
unit-testing: ## Run tests ##*AE*##^unit-tests^##
	service_start(extra-services-up)
	service_start(extra-services-wait)
	$(DOCKER_RUN_WITH_SOCKET) vendor/bin/phpunit --colors=always -c ./etc/qa/phpunit.xml
	service_cleanup(extra-services-down)
MAKEFILE,
            '$(DOCKER_RUN_WITH_SOCKET) vendor/bin/phpunit',
            'bash -ec',
        ];

        yield 'partial up only' => [
            <<<'MAKEFILE'
extra-services-up: ####
	docker compose up -d --wait

unit-testing: ## Run tests ##*AE*##^unit-tests^##
	service_start(extra-services-up)
	service_start(extra-services-wait)
	$(DOCKER_RUN_WITH_SOCKET) vendor/bin/phpunit --colors=always -c ./etc/qa/phpunit.xml
	service_cleanup(extra-services-down)
MAKEFILE,
            '$(MAKE) extra-services-up;',
            'extra-services-down',
        ];
    }

    private function invokeInjectServiceLifecycle(string $makefileContents, string $rootPackagePath): string
    {
        $method = new ReflectionMethod(Installer::class, 'injectServiceLifecycle');

        /** @var string $result */
        $result = $method->invoke(null, $makefileContents, $rootPackagePath);

        return $result;
    }
}
