<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Makefiles\Composer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\Makefiles\Composer\Installer\ServiceLifecycleInjector;
use WyriHaximus\Tests\Makefiles\TestCase;

use function str_replace;

final class ServiceLifecycleTest extends TestCase
{
    #[Test]
    public function injectLeavesMakefileUntouchedWithoutServicePlaceholders(): void
    {
        $input = <<<'MAKEFILE'
unit-testing: ## Run tests ##*AE*##^unit-tests^##
	$(DOCKER_RUN_WITH_SOCKET) vendor/bin/phpunit --colors=always -c ./etc/qa/phpunit.xml
MAKEFILE;

        self::assertSame($input, ServiceLifecycleInjector::inject($input));
    }

    #[Test]
    #[DataProvider('provideInjectServiceLifecycleCases')]
    public function injectServiceLifecycle(
        string $makefileContents,
        string $expectedNeedle,
        string $unexpectedNeedle,
    ): void {
        $result = ServiceLifecycleInjector::inject($makefileContents);

        self::assertStringContainsString($expectedNeedle, $result);
        self::assertStringNotContainsString($unexpectedNeedle, $result);
    }

    #[Test]
    public function injectBuildsCiAndLocalRecipesWithExactFormatting(): void
    {
        $input = <<<'MAKEFILE'
extra-services-up: ####
	docker compose up -d --wait

extra-services-down: ####
	docker compose down

unit-testing: ## Run tests ##*AE*##^unit-tests^##
	service_start(extra-services-up)
	  vendor/bin/phpunit --colors=always -c ./etc/qa/phpunit.xml
	service_cleanup(extra-services-down)
MAKEFILE;

        $result = ServiceLifecycleInjector::inject($input);

        self::assertStringContainsString(
            <<<'MAKEFILE'
ifeq ("$(IN_CI)","TRUE")
	vendor/bin/phpunit --colors=always -c ./etc/qa/phpunit.xml
else
	@bash -ec '	$(MAKE) extra-services-up; \
	trap "$(MAKE) extra-services-down || true" EXIT; \
	vendor/bin/phpunit --colors=always -c ./etc/qa/phpunit.xml'
endif
MAKEFILE,
            $result,
        );
    }

    #[Test]
    public function injectRestoresMiddleLinesWhenServiceTargetsAreMissing(): void
    {
        $input = <<<'MAKEFILE'
unit-testing: ## Run tests ##*AE*##^unit-tests^##
	service_start(extra-services-up)
	  vendor/bin/phpunit --colors=always -c ./etc/qa/phpunit.xml
	service_cleanup(extra-services-down)
MAKEFILE;

        $result = ServiceLifecycleInjector::inject($input);

        self::assertStringContainsString("\t  vendor/bin/phpunit --colors=always -c ./etc/qa/phpunit.xml", $result);
        self::assertStringNotContainsString('ifeq ("$(IN_CI)","TRUE")', $result);
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
}
