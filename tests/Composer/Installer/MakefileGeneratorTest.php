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
use WyriHaximus\TestUtilities\TestCase;

use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function str_contains;

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
    public function makefilePathAcceptsUnixAbsoluteRootWithoutTrailingSeparator(): void
    {
        $method = new ReflectionMethod(MakefileGenerator::class, 'makefilePath');
        self::assertSame(
            '/tmp/project/Makefile',
            $method->invoke(null, '/tmp/project'),
        );
    }

    #[Test]
    public function makefilePathAcceptsWindowsAbsoluteRoot(): void
    {
        $method = new ReflectionMethod(MakefileGenerator::class, 'makefilePath');
        self::assertSame(
            'D:\\a\\Makefiles\\Makefiles\\Makefile',
            $method->invoke(null, 'D:\\a\\Makefiles\\Makefiles\\'),
        );
    }
}
