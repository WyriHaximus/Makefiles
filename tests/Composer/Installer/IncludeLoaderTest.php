<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Makefiles\Composer\Installer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use WyriHaximus\Makefiles\Composer\Installer\IncludeLoader;
use WyriHaximus\Tests\Makefiles\Composer\Installer\TestUtilities\CapturingNullIO;
use WyriHaximus\Tests\Makefiles\Composer\Installer\TestUtilities\ProjectSandbox;
use WyriHaximus\Tests\Makefiles\TestCase;

use function chmod;
use function file_put_contents;
use function in_array;
use function mkdir;
use function str_contains;
use function symlink;

use const DIRECTORY_SEPARATOR;

final class IncludeLoaderTest extends TestCase
{
    #[Test]
    public function loadInlinesReferenceIncludesAndExtraMakefile(): void
    {
        $root      = $this->getTmpDir();
        $reference = $root . 'reference/';
        mkdir($reference . 'includes', 0755, true);
        mkdir($root . 'etc', 0755, true);
        file_put_contents($reference . 'includes/All.mk', "all-target:\n");
        file_put_contents($root . 'etc/Makefile', "extra-target:\n");

        $context = ProjectSandbox::context($root, $reference);
        $result  = IncludeLoader::load($context, "include includes/All.mk\ninclude includes/EXTRA.mk\n");

        self::assertStringContainsString('all-target:', $result);
        self::assertStringContainsString('extra-target:', $result);
        self::assertStringNotContainsString('include includes/', $result);
        self::assertInstanceOf(CapturingNullIO::class, $context->io);
        self::assertTrue(str_contains($context->io->output(), 'Including: All.mk'));
        self::assertTrue(str_contains($context->io->output(), 'Including: etc/Makefile'));
    }

    /** @return iterable<string, array{string, string}> */
    public static function provideLoadReturnsEmptyStringCases(): iterable
    {
        yield 'unreadable include' => ['unreadable', "include includes/Unreadable.mk\n"];
        yield 'include outside package root' => ['outside', "include includes/Escape.mk\n"];
        yield 'missing include' => ['missing', "include includes/Missing.mk\n"];
        yield 'broken symlink include' => ['broken-symlink', "include includes/Broken.mk\n"];
        yield 'directory include' => ['directory', "include includes/Directory.mk\n"];
    }

    #[Test]
    #[DataProvider('provideLoadReturnsEmptyStringCases')]
    public function loadReturnsEmptyString(string $setupType, string $input): void
    {
        if ($setupType === 'unreadable' && ! ProjectSandbox::canSimulateUnreadableFiles()) {
            self::markTestSkipped('File permission tests cannot run on Windows or as root.');
        }

        if (in_array($setupType, ['outside', 'broken-symlink'], true) && ! ProjectSandbox::canCreateSymlinks()) {
            self::markTestSkipped('Symlink tests cannot run when symlink creation is unavailable.');
        }

        $root      = $this->getTmpDir();
        $reference = $root . 'reference/';
        mkdir($reference . 'includes', 0755, true);

        if ($setupType === 'unreadable') {
            $includePath = $reference . 'includes/Unreadable.mk';
            file_put_contents($includePath, "unreadable-target:\n");
            chmod($includePath, 0000);
        }

        if ($setupType === 'outside') {
            $outside = $root . 'outside/';
            mkdir($outside, 0755, true);
            file_put_contents($outside . 'Escape.mk', "escape-target:\n");
            symlink($outside . 'Escape.mk', $reference . 'includes/Escape.mk');
        }

        if ($setupType === 'broken-symlink') {
            symlink($root . 'missing-target.mk', $reference . 'includes/Broken.mk');
        }

        if ($setupType === 'directory') {
            mkdir($reference . 'includes/Directory.mk', 0755, true);
        }

        try {
            self::assertSame("\n", IncludeLoader::load(ProjectSandbox::context($root, $reference), $input));
        } finally {
            if ($setupType === 'unreadable') {
                chmod($reference . 'includes/Unreadable.mk', 0644);
            }
        }
    }

    /** @return iterable<string, array{string, string, bool}> */
    public static function provideIsRealPathInsideRootCases(): iterable
    {
        yield 'windows paths case insensitive' => [
            'C:/Project/includes/All.mk',
            'c:/project/includes',
            true,
        ];

        yield 'windows root path case insensitive' => [
            'c:/project/includes/All.mk',
            'C:/Project/includes',
            true,
        ];

        yield 'extended windows path prefix' => [
            '//?/C:/Project/includes/All.mk',
            'c:/project/includes',
            true,
        ];

        yield 'outside root' => [
            'C:/Project/outside/All.mk',
            'c:/project/includes',
            false,
        ];
    }

    #[Test]
    #[DataProvider('provideIsRealPathInsideRootCases')]
    public function isRealPathInsideRoot(string $fileRealPath, string $rootRealPath, bool $expected): void
    {
        $method = new ReflectionMethod(IncludeLoader::class, 'isRealPathInsideRoot');
        self::assertSame($expected, $method->invoke(null, $fileRealPath, $rootRealPath));
    }

    #[Test]
    public function loadIncludeRejectsPathsOutsidePackageRootWithoutSymlinks(): void
    {
        $root      = $this->getTmpDir();
        $reference = $root . 'reference/';
        mkdir($reference . 'includes', 0755, true);
        mkdir($reference . 'outside', 0755, true);
        file_put_contents($reference . 'outside/Escape.mk', "escape-target:\n");

        $method = new ReflectionMethod(IncludeLoader::class, 'loadInclude');
        $result = $method->invoke(
            null,
            ProjectSandbox::capturingIo(),
            $reference . 'includes' . DIRECTORY_SEPARATOR,
            '../outside/Escape.mk',
        );

        self::assertSame('', $result);
    }

    #[Test]
    public function loadIncludeReturnsEmptyStringForMissingInclude(): void
    {
        $root      = $this->getTmpDir();
        $reference = $root . 'reference/';
        mkdir($reference . 'includes', 0755, true);
        $method = new ReflectionMethod(IncludeLoader::class, 'loadInclude');

        self::assertSame('', $method->invoke(null, ProjectSandbox::capturingIo(), $reference, 'includes/Missing.mk'));
    }

    #[Test]
    public function loadIncludeReturnsEmptyStringForUnreadableInclude(): void
    {
        if (! ProjectSandbox::canSimulateUnreadableFiles()) {
            self::markTestSkipped('File permission tests cannot run on Windows or as root.');
        }

        $root         = $this->getTmpDir();
        $reference    = $root . 'reference/';
        $includesPath = $reference . 'includes/';
        mkdir($includesPath, 0755, true);
        $includePath = $includesPath . 'Unreadable.mk';
        file_put_contents($includePath, "unreadable-target:\n");
        chmod($includePath, 0000);
        $method = new ReflectionMethod(IncludeLoader::class, 'loadInclude');
        $io     = ProjectSandbox::capturingIo();

        try {
            self::assertSame('', $method->invoke(null, $io, $reference, 'includes/Unreadable.mk'));
            self::assertSame('', $io->output());
        } finally {
            chmod($includePath, 0644);
        }
    }
}
