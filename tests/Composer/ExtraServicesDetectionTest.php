<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Makefiles\Composer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use WyriHaximus\Makefiles\Composer\Installer;
use WyriHaximus\TestUtilities\TestCase;

use function dirname;
use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function mkdir;

use const DIRECTORY_SEPARATOR;

final class ExtraServicesDetectionTest extends TestCase
{
    #[Test]
    #[DataProvider('provideInjectExtraServicesFlagCases')]
    public function injectExtraServicesFlag(
        string $etcMakefileContents,
        string $templateContents,
        string $expectedHasExtraServices,
    ): void {
        $rootPackagePath = $this->getTmpDir();
        $etcDir          = $rootPackagePath . 'etc' . DIRECTORY_SEPARATOR;

        if (! is_dir($etcDir)) {
            mkdir($etcDir);
        }

        file_put_contents($etcDir . 'Makefile', $etcMakefileContents);

        $result = $this->invokeInjectExtraServicesFlag($templateContents, $rootPackagePath);

        self::assertStringContainsString('HAS_EXTRA_SERVICES=' . $expectedHasExtraServices, $result);
        self::assertStringNotContainsString('when_target_exists_in_extra', $result);
    }

    #[Test]
    public function injectExtraServicesFlagWithoutEtcMakefile(): void
    {
        $rootPackagePath = $this->getTmpDir();
        $template        = "HAS_EXTRA_SERVICES=when_target_exists_in_extra(extra-services-up, TRUE, FALSE)\n";

        $result = $this->invokeInjectExtraServicesFlag($template, $rootPackagePath);

        self::assertStringContainsString('HAS_EXTRA_SERVICES=FALSE', $result);
        self::assertStringNotContainsString('when_target_exists_in_extra', $result);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function provideInjectExtraServicesFlagCases(): iterable
    {
        $template = "HAS_EXTRA_SERVICES=when_target_exists_in_extra(extra-services-up, TRUE, FALSE)\n";

        yield 'etc/Makefile defines extra-services-up' => [
            "extra-services-up: ####\n\tdocker compose up -d --wait\n",
            $template,
            'TRUE',
        ];

        yield 'etc/Makefile without extra-services-up' => [
            "functional-testing: ## tests ##*A*##\n\tvendor/bin/phpunit\n",
            $template,
            'FALSE',
        ];
    }

    #[Test]
    public function generatedTemplateContainsInCiFalse(): void
    {
        $templatePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'Makefile.PHP';
        $template     = file_get_contents($templatePath);

        self::assertIsString($template);
        self::assertStringContainsString('ifeq ("$(GITHUB_ACTIONS)","true")', $template);
        self::assertStringContainsString('IN_CI=FALSE', $template);
        self::assertStringNotContainsString('include includes/Services.mk', $template);
        self::assertStringNotContainsString('RUN_WITH_EXTRA_SERVICES', $template);
        self::assertStringNotContainsString('EXTRA_SERVICES_DOCKER_NETWORK', $template);
    }

    #[Test]
    public function injectExtraServicesDirectDockerFlags(): void
    {
        $input = <<<'MAKEFILE'
HAS_EXTRA_SERVICES=TRUE
ALL_HAS_DIRECT_DOCKER_TASKS=FALSE
CONTRIB_HAS_DIRECT_DOCKER_TASKS=FALSE
MAKEFILE;

        $result = $this->invokeInjectExtraServicesDirectDockerFlags($input);

        self::assertStringContainsString('ALL_HAS_DIRECT_DOCKER_TASKS=TRUE', $result);
        self::assertStringContainsString('CONTRIB_HAS_DIRECT_DOCKER_TASKS=TRUE', $result);
    }

    #[Test]
    public function injectExtraServicesDirectDockerFlagsLeavesFalseWhenNoExtraServices(): void
    {
        $input = <<<'MAKEFILE'
HAS_EXTRA_SERVICES=FALSE
ALL_HAS_DIRECT_DOCKER_TASKS=FALSE
MAKEFILE;

        $result = $this->invokeInjectExtraServicesDirectDockerFlags($input);

        self::assertStringContainsString('ALL_HAS_DIRECT_DOCKER_TASKS=FALSE', $result);
    }

    private function invokeInjectExtraServicesDirectDockerFlags(string $makefileContents): string
    {
        $method = new ReflectionMethod(Installer::class, 'injectExtraServicesDirectDockerFlags');

        /** @var string $result */
        $result = $method->invoke(null, $makefileContents);

        return $result;
    }

    private function invokeInjectExtraServicesFlag(string $makefileContents, string $rootPackagePath): string
    {
        $method = new ReflectionMethod(Installer::class, 'injectExtraServicesFlag');

        /** @var string $result */
        $result = $method->invoke(null, $makefileContents, $rootPackagePath);

        return $result;
    }
}
