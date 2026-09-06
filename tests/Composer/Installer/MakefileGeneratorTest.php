<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Makefiles\Composer\Installer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use RuntimeException;
use WyriHaximus\Makefiles\Composer\Installer\MakefileGenerator;
use WyriHaximus\Makefiles\Composer\Installer\Requirements;
use WyriHaximus\Makefiles\Composer\SupportedFeatures;
use WyriHaximus\Tests\Makefiles\Composer\Installer\TestUtilities\CapturingNullIO;
use WyriHaximus\Tests\Makefiles\Composer\Installer\TestUtilities\ProjectSandbox;
use WyriHaximus\Tests\Makefiles\TestCase;

use function chmod;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function str_contains;

use const DIRECTORY_SEPARATOR;

final class MakefileGeneratorTest extends TestCase
{
    /** @return iterable<string, array{bool, bool, bool, string, list<string>, list<string>}> */
    public static function provideGenerateCases(): iterable
    {
        yield 'writes processed makefile' => [
            true,
            true,
            false,
            '',
            ['stub-target:', 'NEEDS=FALSE', 'PHP_VERSION="8.4"', 'alpha: ## Alpha'],
            ['Supported features Matrix:', 'makefiles:</info> Generating Makefile'],
        ];

        yield 'missing template' => [
            false,
            true,
            false,
            '',
            [],
            ['Supported features Matrix:'],
        ];

        yield 'empty root package path' => [
            true,
            false,
            true,
            'Refusing to write Makefile to an unsafe root package path.',
            [],
            [],
        ];
    }

    /**
     * @param list<string> $expectedInMakefile
     * @param list<string> $expectedInOutput
     */
    #[Test]
    #[DataProvider('provideGenerateCases')]
    public function generate(
        bool $createTemplate,
        bool $useSandboxRoot,
        bool $expectException,
        string $exceptionMessage,
        array $expectedInMakefile,
        array $expectedInOutput,
    ): void {
        ['root' => $root, 'reference' => $reference] = ProjectSandbox::createStubReferenceRoot($this->getTmpDir());

        if ($createTemplate) {
            file_put_contents($root . 'composer.json', '{"config":{"platform":{"php":"8.4.13"}}}');
            file_put_contents($reference . 'templates/Makefile.PHP', <<<'MAKEFILE'
include includes/Stub.mk
NEEDS=when_in_requirements(["missing/pkg"], TRUE, FALSE)
PHP_VERSION=lowest_cleaned_version_in_tree_from_file("composer.json", "config.platform.php")
supported-features(list)
supported-features(raw)
help(main)
alpha: ## Alpha ####
MAKEFILE);
            file_put_contents($reference . 'includes/Stub.mk', "stub-target:\n");
        }

        $context = ProjectSandbox::context(
            $useSandboxRoot ? $root : '',
            $reference,
            new Requirements($createTemplate ? ['php'] : [], $createTemplate ? ['php'] : []),
            SupportedFeatures::DEFAULTS,
        );

        if ($expectException) {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageIsOrContains($exceptionMessage);
        }

        MakefileGenerator::generate($context);
        self::assertInstanceOf(CapturingNullIO::class, $context->io);
        $output = $context->io->output();

        foreach ($expectedInOutput as $needle) {
            self::assertTrue(str_contains($output, $needle));
        }

        if ($expectedInMakefile === []) {
            self::assertFalse(file_exists($root . 'Makefile'));
            self::assertFalse(str_contains($output, 'Generating Makefile took less than a second'));

            return;
        }

        self::assertFileExists($root . 'Makefile');
        $makefile = file_get_contents($root . 'Makefile');
        self::assertIsString($makefile);

        foreach ($expectedInMakefile as $needle) {
            self::assertStringContainsString($needle, $makefile);
        }
    }

    #[Test]
    public function generateReplacesExistingMakefile(): void
    {
        ['root' => $root, 'reference' => $reference] = ProjectSandbox::createStubReferenceRoot($this->getTmpDir());
        file_put_contents($root . 'composer.json', '{"config":{"platform":{"php":"8.4.13"}}}');
        file_put_contents($reference . 'templates/Makefile.PHP', "alpha: ## Alpha ####\n");
        $context = ProjectSandbox::context(
            $root,
            $reference,
            new Requirements(['php'], ['php']),
            SupportedFeatures::DEFAULTS,
        );

        MakefileGenerator::generate($context);
        self::assertSame("alpha: ## Alpha ####\n", file_get_contents($root . 'Makefile'));

        file_put_contents($reference . 'templates/Makefile.PHP', "beta: ## Beta ####\n");
        MakefileGenerator::generate($context);

        self::assertFileExists($root . 'Makefile');
        self::assertSame("beta: ## Beta ####\n", file_get_contents($root . 'Makefile'));
    }

    #[Test]
    public function generateReturnsEarlyWhenTemplateCannotBeRead(): void
    {
        if (! ProjectSandbox::canSimulateUnreadableFiles()) {
            self::markTestSkipped('File permission tests cannot run as root.');
        }

        ['root' => $root, 'reference' => $reference] = ProjectSandbox::createStubReferenceRoot($this->getTmpDir());
        $templatePath                                = $reference . 'templates/Makefile.PHP';
        file_put_contents($templatePath, 'template');
        chmod($templatePath, 0000);

        try {
            MakefileGenerator::generate(ProjectSandbox::context($root, $reference));
            self::assertFalse(file_exists($root . 'Makefile'));
        } finally {
            chmod($templatePath, 0644);
        }
    }

    /** @return iterable<string, array{string, string}> */
    public static function provideMakefilePathSuccessCases(): iterable
    {
        yield 'unix absolute root without trailing separator' => [
            '/tmp/project',
            '/tmp/project/Makefile',
        ];

        yield 'windows absolute root with backslashes' => [
            'D:\\a\\Makefiles\\Makefiles\\',
            'D:\\a\\Makefiles\\Makefiles\\Makefile',
        ];

        if (DIRECTORY_SEPARATOR === '\\') {
            yield 'drive path without slashes on windows' => [
                'D:\\project',
                'D:\\project\\Makefile',
            ];

            return;
        }

        yield 'drive path without slashes on unix' => [
            'D:project',
            'D:project/Makefile',
        ];
    }

    /** @return iterable<string, array{string}> */
    public static function provideMakefilePathRejectionCases(): iterable
    {
        yield 'relative root' => ['tmp-relative-root/'];
    }

    /** @return iterable<string, array{string, bool}> */
    public static function provideIsAbsolutePathCases(): iterable
    {
        yield 'empty path' => ['', false];
        yield 'drive letter colon only' => ['D:', true];
    }

    #[Test]
    #[DataProvider('provideMakefilePathRejectionCases')]
    public function makefilePathRejectsUnsafeRoot(string $rootPackagePath): void
    {
        $method = new ReflectionMethod(MakefileGenerator::class, 'makefilePath');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Refusing to write Makefile to an unsafe root package path.');
        $method->invoke(null, $rootPackagePath);
    }

    #[Test]
    #[DataProvider('provideMakefilePathSuccessCases')]
    public function makefilePath(string $rootPackagePath, string $expectedPath): void
    {
        $method = new ReflectionMethod(MakefileGenerator::class, 'makefilePath');
        self::assertSame($expectedPath, $method->invoke(null, $rootPackagePath));
    }

    #[Test]
    #[DataProvider('provideIsAbsolutePathCases')]
    public function isAbsolutePath(string $path, bool $expected): void
    {
        $method = new ReflectionMethod(MakefileGenerator::class, 'isAbsolutePath');
        self::assertSame($expected, $method->invoke(null, $path));
    }
}
