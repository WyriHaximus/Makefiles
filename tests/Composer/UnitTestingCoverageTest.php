<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Makefiles\Composer;

use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\Tests\Makefiles\TestCase;

use function array_filter;
use function dirname;
use function explode;
use function file_get_contents;
use function preg_match;
use function str_contains;
use function substr_count;

use const DIRECTORY_SEPARATOR;

final class UnitTestingCoverageTest extends TestCase
{
    #[Test]
    public function unitTestingRecipesOnlyRequestCoverageWhenRuntimeHasADriver(): void
    {
        $rootPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;
        $phpMk    = file_get_contents($rootPath . 'includes' . DIRECTORY_SEPARATOR . 'PHP.mk');
        $template = file_get_contents($rootPath . 'templates' . DIRECTORY_SEPARATOR . 'Makefile.PHP');

        self::assertIsString($phpMk);
        self::assertIsString($template);
        self::assertStringNotContainsString('PHPUNIT_COVERAGE_DETECT', $phpMk);
        self::assertStringContainsString('PHPUNIT_COVERAGE_DETECT:=php -r', $template);
        self::assertStringContainsString('extension_loaded("pcov")', $template);
        self::assertStringContainsString('extension_loaded("xdebug")', $template);
        self::assertStringContainsString('ifeq ("$(IN_DOCKER)","TRUE")', $template);
        self::assertStringContainsString(
            '$(DOCKER_RUN_WITH_SOCKET) $(PHPUNIT_COVERAGE_DETECT)',
            $template,
        );
        self::assertStringContainsString(
            'PHPUNIT_RAW_COVERAGE_ENABLED:=$(shell $(PHPUNIT_COVERAGE_DETECT))',
            $template,
        );
        self::assertStringContainsString(
            'PHPUNIT_COVERAGE_OPTIONS:=--coverage-text --coverage-html ./var/phpunit/coverage --coverage-clover ./var/phpunit/coverage/clover.xml',
            $phpMk,
        );
        self::assertStringContainsString(
            'PHPUNIT_DOCKER_COVERAGE_OPTIONS:=$(if $(filter 1,$(PHPUNIT_DOCKER_COVERAGE_ENABLED)),$(PHPUNIT_COVERAGE_OPTIONS),)',
            $phpMk,
        );
        self::assertStringContainsString(
            'PHPUNIT_RAW_COVERAGE_OPTIONS:=$(if $(filter 1,$(PHPUNIT_RAW_COVERAGE_ENABLED)),$(PHPUNIT_COVERAGE_OPTIONS),)',
            $phpMk,
        );
        self::assertSame(2, substr_count($phpMk, '$(PHPUNIT_DOCKER_COVERAGE_OPTIONS)'));
        self::assertSame(1, substr_count($phpMk, '$(PHPUNIT_RAW_COVERAGE_OPTIONS)'));
    }

    #[Test]
    public function coverageGuardsOnlyRunWithADriverAndFilteredTestsRemainUnguarded(): void
    {
        $phpMkPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'PHP.mk';
        $phpMk     = file_get_contents($phpMkPath);

        self::assertIsString($phpMk);
        self::assertStringContainsString(
            'coverage-guard: ## Enforce code coverage rules ####' . "\n" .
            "\t@\$(if \$(filter 1,\$(PHPUNIT_DOCKER_COVERAGE_ENABLED)),\$(DOCKER_RUN) vendor/bin/coverage-guard check ./var/phpunit/coverage/clover.xml --config=./etc/qa/coverage-guard.php,:)",
            $phpMk,
        );
        self::assertStringContainsString(
            'coverage-guard-raw: ## Enforce code coverage rules ####' . "\n" .
            "\t@\$(if \$(filter 1,\$(PHPUNIT_RAW_COVERAGE_ENABLED)),php vendor/bin/coverage-guard check ./var/phpunit/coverage/clover.xml --config=./etc/qa/coverage-guard.php,:)",
            $phpMk,
        );

        $filteredRecipe = [];
        $matched        = preg_match('/^unit-testing-filter:[\s\S]*?(?=^unit-testing-raw:)/m', $phpMk, $filteredRecipe);

        self::assertSame(1, $matched);
        self::assertStringNotContainsString('coverage-guard', $filteredRecipe[0]);
    }

    #[Test]
    public function infectionOnlyRunsWhenRuntimeHasACoverageDriver(): void
    {
        $phpMkPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'PHP.mk';
        $phpMk     = file_get_contents($phpMkPath);

        self::assertIsString($phpMk);
        self::assertStringContainsString(
            "\t\$(if \$(filter 1,\$(PHPUNIT_DOCKER_COVERAGE_ENABLED)),\$(DOCKER_RUN_WITH_SOCKET) vendor/bin/infection",
            $phpMk,
        );
        self::assertStringContainsString(
            "\t\$(if \$(filter 1,\$(PHPUNIT_RAW_COVERAGE_ENABLED)),vendor/bin/infection",
            $phpMk,
        );
    }

    #[Test]
    public function generatedMakefileContainsTheConditionalCoverageRecipes(): void
    {
        $rootPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;
        $phpMk    = file_get_contents($rootPath . 'includes' . DIRECTORY_SEPARATOR . 'PHP.mk');
        $template = file_get_contents($rootPath . 'templates' . DIRECTORY_SEPARATOR . 'Makefile.PHP');
        $makefile = file_get_contents($rootPath . 'Makefile');

        self::assertIsString($phpMk);
        self::assertIsString($template);
        self::assertIsString($makefile);

        foreach ([$phpMk, $template] as $source) {
            $coverageLines = array_filter(
                explode("\n", $source),
                static fn (string $line): bool => str_contains($line, 'PHPUNIT_') ||
                    str_contains($line, 'coverage-guard') ||
                    str_contains($line, 'mutation-testing') ||
                    str_contains($line, 'vendor/bin/infection'),
            );

            self::assertNotSame([], $coverageLines);
            foreach ($coverageLines as $coverageLine) {
                self::assertStringContainsString($coverageLine, $makefile);
            }
        }
    }
}
