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

use function basename;
use function chmod;
use function dirname;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function mkdir;
use function preg_quote;
use function trim;

use const DIRECTORY_SEPARATOR;

final class MakefileGeneratorTest extends TestCase
{
    /** @return iterable<string, array{bool, bool, bool, string, list<string>, string}> */
    public static function provideGenerateCases(): iterable
    {
        $matrix = self::supportedFeaturesMatrixIoOutput();

        yield 'writes processed makefile' => [
            true,
            true,
            false,
            '',
            ['stub-target:', 'NEEDS=FALSE', 'PHP_VERSION="8.4"', 'alpha: ## Alpha'],
            $matrix
                . '<info>wyrihaximus/makefiles:</info> Generating Makefile' . "\n"
                . '<info>wyrihaximus/makefiles:</info> Including: Stub.mk' . "\n"
                . '<info>wyrihaximus/makefiles:</info> Generating Makefile took less than a second' . "\n",
        ];

        yield 'missing template' => [
            false,
            true,
            false,
            '',
            [],
            $matrix,
        ];

        yield 'empty root package path' => [
            true,
            false,
            true,
            'Refusing to write Makefile to an unsafe root package path.',
            [],
            $matrix
                . '<info>wyrihaximus/makefiles:</info> Generating Makefile' . "\n"
                . '<info>wyrihaximus/makefiles:</info> Including: Stub.mk' . "\n",
        ];
    }

    /** @param list<string> $expectedInMakefile */
    #[Test]
    #[DataProvider('provideGenerateCases')]
    public function generate(
        bool $createTemplate,
        bool $useSandboxRoot,
        bool $expectException,
        string $exceptionMessage,
        array $expectedInMakefile,
        string $expectedOutput,
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

        if ($expectException) {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageIsOrContains($exceptionMessage);
        }

        MakefileGenerator::generate($context);

        self::assertInstanceOf(CapturingNullIO::class, $context->io);
        self::assertSame($expectedOutput, $context->io->output());

        if ($expectedInMakefile === []) {
            self::assertFalse(file_exists($root . 'Makefile'));

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
        $temporaryMakefiles = glob($root . '.Makefile.*.tmp');
        self::assertIsArray($temporaryMakefiles);
        self::assertCount(0, $temporaryMakefiles);

        file_put_contents($reference . 'templates/Makefile.PHP', "beta: ## Beta ####\n");
        $secondContext = ProjectSandbox::context(
            $root,
            $reference,
            new Requirements(['php'], ['php']),
            SupportedFeatures::DEFAULTS,
        );
        MakefileGenerator::generate($secondContext);

        self::assertFileExists($root . 'Makefile');
        self::assertSame("beta: ## Beta ####\n", file_get_contents($root . 'Makefile'));
        $temporaryMakefiles = glob($root . '.Makefile.*.tmp');
        self::assertIsArray($temporaryMakefiles);
        self::assertCount(0, $temporaryMakefiles);
        $makefilePaths = glob($root . 'Makefile');
        self::assertIsArray($makefilePaths);
        self::assertCount(1, $makefilePaths);
        self::assertInstanceOf(CapturingNullIO::class, $secondContext->io);
        self::assertSame(
            self::supportedFeaturesMatrixIoOutput()
                . '<info>wyrihaximus/makefiles:</info> Generating Makefile' . "\n"
                . '<info>wyrihaximus/makefiles:</info> Generating Makefile took less than a second' . "\n",
            $secondContext->io->output(),
        );
    }

    #[Test]
    public function announceMakefileGenerationWritesExactComposerIoLine(): void
    {
        $io     = ProjectSandbox::capturingIo();
        $method = new ReflectionMethod(MakefileGenerator::class, 'announceMakefileGeneration');
        $method->invoke(null, $io);

        self::assertSame(
            '<info>wyrihaximus/makefiles:</info> Generating Makefile' . "\n",
            $io->output(),
        );
    }

    #[Test]
    public function generateReturnsEarlyWhenTemplatePathIsDirectory(): void
    {
        ['root' => $root, 'reference' => $reference] = ProjectSandbox::createStubReferenceRoot($this->getTmpDir());
        mkdir($reference . 'templates/Makefile.PHP', 0755, true);

        $context = ProjectSandbox::context($root, $reference);
        MakefileGenerator::generate($context);

        self::assertFalse(file_exists($root . 'Makefile'));
        self::assertInstanceOf(CapturingNullIO::class, $context->io);
        self::assertSame(self::supportedFeaturesMatrixIoOutput(), $context->io->output());
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

        yield 'unix absolute root forward slashes only' => [
            '/var/www/my-app',
            '/var/www/my-app/Makefile',
        ];

        yield 'windows absolute root with backslashes' => [
            'D:\\a\\Makefiles\\Makefiles\\',
            'D:\\a\\Makefiles\\Makefiles\\Makefile',
        ];

        yield 'drive path without slash characters uses backslash separator' => [
            'D:project',
            'D:project\\Makefile',
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
        yield 'single leading backslash' => ['\\', true];
        yield 'drive letter colon only' => ['D:', true];
        yield 'unix root only' => ['/only', true];
        yield 'windows backslash path' => ['C:\\path\\to\\project', true];
        yield 'leading backslash path' => ['\\\\server\\share\\project', true];
        yield 'single leading backslash path' => ['\\project-root', true];
        yield 'drive letter without leading slash' => ['E:project\\dir', true];
        yield 'relative path with colon not in second position' => ['relative:path', false];
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

    #[Test]
    public function temporaryMakefilePathUsesProjectDirectory(): void
    {
        $makefilePath  = '/tmp/example-project/Makefile';
        $method        = new ReflectionMethod(MakefileGenerator::class, 'temporaryMakefilePath');
        $temporaryPath = $method->invoke(null, $makefilePath);
        self::assertIsString($temporaryPath);
        $directorySeparator = preg_quote(DIRECTORY_SEPARATOR, '#');
        self::assertMatchesRegularExpression(
            '#^/tmp/example-project' . $directorySeparator . '\\.Makefile\\.[0-9a-f.]+\\.tmp$#',
            $temporaryPath,
        );
    }

    #[Test]
    public function announceMakefileGenerationWritesExactStatusLine(): void
    {
        $io     = ProjectSandbox::capturingIo();
        $method = new ReflectionMethod(MakefileGenerator::class, 'announceMakefileGeneration');
        $method->invoke(null, $io);

        self::assertSame(
            '<info>wyrihaximus/makefiles:</info> Generating Makefile',
            trim($io->output()),
        );
    }

    #[Test]
    public function writeMakefileLeavesTemporaryFilesInProjectDirectory(): void
    {
        $projectDir   = $this->getTmpDir() . 'write-makefile/';
        $makefilePath = $projectDir . 'Makefile';
        mkdir($projectDir, 0755, true);
        $writeMethod = new ReflectionMethod(MakefileGenerator::class, 'writeMakefile');

        $writeMethod->invoke(null, $makefilePath, "generated\n");

        self::assertSame("generated\n", file_get_contents($makefilePath));
        self::assertSame([], glob($projectDir . '.Makefile.*.tmp'));
        self::assertSame([], glob(dirname($projectDir) . '/' . basename($projectDir) . '.Makefile.*.tmp'));
    }

    private static function supportedFeaturesMatrixIoOutput(): string
    {
        $output = '<info>wyrihaximus/makefiles:</info> Supported features Matrix:' . "\n";
        foreach (SupportedFeatures::DEFAULTS as $name => $supported) {
            $output .= '<info>wyrihaximus/makefiles:</info> ' . $name . ': ' . ($supported ? '✅' : '❌') . "\n";
        }

        return $output;
    }
}
