<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Makefiles\Composer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use WyriHaximus\Makefiles\Composer\Installer\DirectDockerDetector;
use WyriHaximus\Makefiles\Composer\Installer\ExtraServicesInjector;
use WyriHaximus\Tests\Makefiles\TestCase;

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

        $result = ExtraServicesInjector::inject($templateContents, $rootPackagePath);

        self::assertStringContainsString('HAS_EXTRA_SERVICES=' . $expectedHasExtraServices, $result);
        self::assertStringNotContainsString('when_target_exists_in_extra', $result);
    }

    #[Test]
    public function injectLeavesMakefileUntouchedWhenPlaceholderSyntaxIsMalformed(): void
    {
        $input = "BROKEN=when_target_exists_in_extra(incomplete\nOTHER=VALUE\n";

        self::assertSame($input, ExtraServicesInjector::inject($input, $this->getTmpDir()));
    }

    #[Test]
    public function injectLeavesMakefileUntouchedWhenNoPlaceholders(): void
    {
        $input = "HAS_EXTRA_SERVICES=FALSE\nOTHER=VALUE\n";

        self::assertSame($input, ExtraServicesInjector::inject($input, $this->getTmpDir()));
    }

    #[Test]
    public function injectLeavesMakefileUntouchedWhenNoPlaceholdersEvenWithEtcMakefile(): void
    {
        $rootPackagePath = $this->getTmpDir();
        $etcDir          = $rootPackagePath . 'etc' . DIRECTORY_SEPARATOR;
        mkdir($etcDir);
        file_put_contents($etcDir . 'Makefile', "extra-services-up: ####\n\tdocker compose up -d --wait\n");
        $input = "HAS_EXTRA_SERVICES=FALSE\nOTHER=VALUE\n";

        self::assertSame($input, ExtraServicesInjector::inject($input, $rootPackagePath));
    }

    #[Test]
    public function injectResolvesMultiplePlaceholdersWithAssertSame(): void
    {
        $rootPackagePath = $this->getTmpDir();
        $etcDir          = $rootPackagePath . 'etc' . DIRECTORY_SEPARATOR;
        mkdir($etcDir);
        file_put_contents(
            $etcDir . 'Makefile',
            <<<'MAKEFILE'
extra-services-up: ####
	docker compose up -d --wait

extra-services-wait: ####
	docker compose exec -T svc await_startup
MAKEFILE,
        );

        $input = <<<'MAKEFILE'
HAS_EXTRA_SERVICES=when_target_exists_in_extra(extra-services-up, TRUE, FALSE)
WAIT=when_target_exists_in_extra(extra-services-wait, TRUE, FALSE)
MISSING=when_target_exists_in_extra(missing-target, TRUE, FALSE)
MAKEFILE;

        $expected = <<<'MAKEFILE'
HAS_EXTRA_SERVICES=TRUE
WAIT=TRUE
MISSING=FALSE
MAKEFILE;

        self::assertSame($expected, ExtraServicesInjector::inject($input, $rootPackagePath));
    }

    #[Test]
    public function injectResolvesTargetWhenEtcMakefileUsesCrlfLineEndings(): void
    {
        $rootPackagePath = $this->getTmpDir();
        $etcDir          = $rootPackagePath . 'etc' . DIRECTORY_SEPARATOR;
        mkdir($etcDir);
        file_put_contents($etcDir . 'Makefile', "extra-services-up: ####\r\n\tdocker compose up -d --wait\r\n");

        $input    = "HAS_EXTRA_SERVICES=when_target_exists_in_extra(extra-services-up, TRUE, FALSE)\n";
        $expected = "HAS_EXTRA_SERVICES=TRUE\n";

        self::assertSame($expected, ExtraServicesInjector::inject($input, $rootPackagePath));
    }

    #[Test]
    public function injectDoesNotTreatDecoyPrefixTargetAsMatch(): void
    {
        $rootPackagePath = $this->getTmpDir();
        $etcDir          = $rootPackagePath . 'etc' . DIRECTORY_SEPARATOR;
        mkdir($etcDir);
        file_put_contents(
            $etcDir . 'Makefile',
            "extra-services-up-backup: ####\n\techo decoy\n",
        );

        $input    = "HAS_EXTRA_SERVICES=when_target_exists_in_extra(extra-services-up, TRUE, FALSE)\n";
        $expected = "HAS_EXTRA_SERVICES=FALSE\n";

        self::assertSame($expected, ExtraServicesInjector::inject($input, $rootPackagePath));
    }

    #[Test]
    public function injectDoesNotTreatRegexSpecialDecoyTargetAsDottedTargetMatch(): void
    {
        $rootPackagePath = $this->getTmpDir();
        $etcDir          = $rootPackagePath . 'etc' . DIRECTORY_SEPARATOR;
        mkdir($etcDir);
        file_put_contents(
            $etcDir . 'Makefile',
            "myXtarget: ####\n\techo decoy\n",
        );

        $input    = "HAS_EXTRA_SERVICES=when_target_exists_in_extra(my.target, TRUE, FALSE)\n";
        $expected = "HAS_EXTRA_SERVICES=FALSE\n";

        self::assertSame($expected, ExtraServicesInjector::inject($input, $rootPackagePath));
    }

    #[Test]
    public function injectMatchesExactTargetHeaderNotDecoyPrefix(): void
    {
        $rootPackagePath = $this->getTmpDir();
        $etcDir          = $rootPackagePath . 'etc' . DIRECTORY_SEPARATOR;
        mkdir($etcDir);
        file_put_contents(
            $etcDir . 'Makefile',
            <<<'MAKEFILE'
extra-services-up-backup: ####
	echo decoy

extra-services-up: ####
	docker compose up -d --wait
MAKEFILE,
        );

        $input    = "HAS_EXTRA_SERVICES=when_target_exists_in_extra(extra-services-up, TRUE, FALSE)\n";
        $expected = "HAS_EXTRA_SERVICES=TRUE\n";

        self::assertSame($expected, ExtraServicesInjector::inject($input, $rootPackagePath));
    }

    #[Test]
    public function injectExtraServicesFlagWithoutEtcMakefile(): void
    {
        $rootPackagePath = $this->getTmpDir();
        $template        = "HAS_EXTRA_SERVICES=when_target_exists_in_extra(extra-services-up, TRUE, FALSE)\n";

        $result = ExtraServicesInjector::inject($template, $rootPackagePath);

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
        self::assertStringContainsString('MUTATION_THREADS?=$(THREADS)', $template);
        self::assertStringContainsString('ifeq ("$(IN_CI)","TRUE")', $template);
        self::assertStringContainsString('MUTATION_THREADS?=1', $template);
        self::assertStringContainsString('-e MUTATION_THREADS="${MUTATION_THREADS}"', $template);
        self::assertStringContainsString('OTEL_PHP_FIBERS_ENABLED?=when_in_requirements', $template);
        self::assertStringNotContainsString('include includes/Services.mk', $template);
        self::assertStringNotContainsString('RUN_WITH_EXTRA_SERVICES', $template);
        self::assertStringNotContainsString('EXTRA_SERVICES_DOCKER_NETWORK', $template);
    }

    #[Test]
    public function phpMkUsesMutationThreadsForInfection(): void
    {
        $phpMkPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'PHP.mk';
        $phpMk     = file_get_contents($phpMkPath);

        self::assertIsString($phpMk);
        self::assertStringContainsString('--threads=$(MUTATION_THREADS)', $phpMk);
        self::assertStringNotContainsString('--threads=$(THREADS)', $phpMk);
    }

    #[Test]
    public function injectExtraServicesDirectDockerFlags(): void
    {
        $input = <<<'MAKEFILE'
HAS_EXTRA_SERVICES=TRUE
ALL_HAS_DIRECT_DOCKER_TASKS=FALSE
CONTRIB_HAS_DIRECT_DOCKER_TASKS=FALSE
MAKEFILE;

        $expected = <<<'MAKEFILE'
HAS_EXTRA_SERVICES=TRUE
ALL_HAS_DIRECT_DOCKER_TASKS=TRUE
CONTRIB_HAS_DIRECT_DOCKER_TASKS=TRUE
MAKEFILE;

        self::assertSame($expected, DirectDockerDetector::injectExtraServicesDirectDockerFlags($input));
    }

    #[Test]
    public function injectExtraServicesDirectDockerFlagsLeavesFalseWhenNoExtraServices(): void
    {
        $input = <<<'MAKEFILE'
HAS_EXTRA_SERVICES=FALSE
ALL_HAS_DIRECT_DOCKER_TASKS=FALSE
MAKEFILE;

        self::assertSame($input, DirectDockerDetector::injectExtraServicesDirectDockerFlags($input));
    }

    #[Test]
    public function etcMakefileDefinesTargetMatchesExactHeaderWithRegexSpecialTargetName(): void
    {
        $method   = new ReflectionMethod(ExtraServicesInjector::class, 'etcMakefileDefinesTarget');
        $makefile = "my.target: ####\n\tdocker compose up\n";

        self::assertTrue($method->invoke(null, $makefile, 'my.target'));
        self::assertFalse($method->invoke(null, $makefile, 'myXtarget'));
    }

    #[Test]
    public function normalizeLineEndingsConvertsCrLfToLf(): void
    {
        $method = new ReflectionMethod(ExtraServicesInjector::class, 'normalizeLineEndings');
        self::assertSame("a\nb\n", $method->invoke(null, "a\r\nb\r\n"));
        self::assertSame("extra-services-up:\n", $method->invoke(null, "extra-services-up:\r"));
    }

    #[Test]
    public function readEtcMakefileReturnsFalseWhenEtcMakefileMissing(): void
    {
        $method = new ReflectionMethod(ExtraServicesInjector::class, 'readEtcMakefile');
        self::assertFalse($method->invoke(null, $this->getTmpDir() . 'missing-etc/'));
    }

    #[Test]
    public function injectResolvesTargetWhenEtcMakefileHasMultipleLinesBeforeMatch(): void
    {
        $rootPackagePath = $this->getTmpDir();
        $etcDir          = $rootPackagePath . 'etc' . DIRECTORY_SEPARATOR;
        mkdir($etcDir);
        file_put_contents(
            $etcDir . 'Makefile',
            <<<'MAKEFILE'
# comment
functional-testing: ####
	echo local

extra-services-up: ####
	docker compose up -d --wait
MAKEFILE,
        );

        $input    = "HAS_EXTRA_SERVICES=when_target_exists_in_extra(extra-services-up, TRUE, FALSE)\n";
        $expected = "HAS_EXTRA_SERVICES=TRUE\n";

        self::assertSame($expected, ExtraServicesInjector::inject($input, $rootPackagePath));
    }

    #[Test]
    public function injectExtraServicesDirectDockerFlagsDoesNotMatchPartialFlagNames(): void
    {
        $input = <<<'MAKEFILE'
HAS_EXTRA_SERVICES=TRUE
ALL_HAS_DIRECT_DOCKER_TASKS_EXTRA=FALSE
ALL_HAS_DIRECT_DOCKER_TASKS=FALSE
MAKEFILE;

        $expected = <<<'MAKEFILE'
HAS_EXTRA_SERVICES=TRUE
ALL_HAS_DIRECT_DOCKER_TASKS_EXTRA=FALSE
ALL_HAS_DIRECT_DOCKER_TASKS=TRUE
MAKEFILE;

        self::assertSame($expected, DirectDockerDetector::injectExtraServicesDirectDockerFlags($input));
    }

    #[Test]
    public function targetHeaderPatternQuotesRegexSpecialCharacters(): void
    {
        $method = new ReflectionMethod(ExtraServicesInjector::class, 'targetHeaderPattern');

        self::assertSame('/^my\.target:/', $method->invoke(null, 'my.target'));
    }
}
