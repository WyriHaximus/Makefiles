<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Makefiles\Composer\Installer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use RuntimeException;
use WyriHaximus\Makefiles\Composer\Installer\IncludeLoader;
use WyriHaximus\Tests\Makefiles\Composer\Installer\TestUtilities\CapturingNullIO;
use WyriHaximus\Tests\Makefiles\Composer\Installer\TestUtilities\ProjectSandbox;
use WyriHaximus\Tests\Makefiles\TestCase;

use function chmod;
use function file_put_contents;
use function in_array;
use function ini_get;
use function ini_set;
use function mkdir;
use function str_contains;
use function str_replace;
use function symlink;

use const DIRECTORY_SEPARATOR;

final class IncludeLoaderTest extends TestCase
{
    #[Test]
    public function loadThrowsWhenIncludeReplacementHitsPcreBacktrackLimit(): void
    {
        $previousBacktrackLimit = ini_get('pcre.backtrack_limit');
        ini_set('pcre.backtrack_limit', '0');

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageIsOrContains('Failed load in includes:');

            IncludeLoader::load(
                ProjectSandbox::context($this->getTmpDir(), $this->getTmpDir() . 'reference/'),
                'include includes/All.mk',
            );
        } finally {
            ini_set('pcre.backtrack_limit', (string) $previousBacktrackLimit);
        }
    }

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
            ProjectSandbox::createBrokenSymlink($reference . 'includes/Broken.mk');
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

        yield 'root without trailing slash rejects sibling directory prefix' => [
            'c:/projectincludes/All.mk',
            'c:/project',
            false,
        ];

        yield 'unix file path with drive-letter root' => [
            '/tmp/project/includes/All.mk',
            'C:/Project/includes',
            false,
        ];

        yield 'single character file path outside single character root' => [
            'b',
            'a',
            false,
        ];

        yield 'backslash root trimmed before prefix check' => [
            'C:\\Project\\includes\\All.mk',
            'C:\\Project\\includes\\',
            true,
        ];

        yield 'root with redundant trailing slashes' => [
            'C:/Project/includes/All.mk',
            'C:/Project/includes///',
            true,
        ];
    }

    /** @return iterable<string, array{string, bool}> */
    public static function providePathHasWindowsDriveLetterCases(): iterable
    {
        yield 'drive letter colon only' => ['C:', true];
        yield 'drive letter path' => ['C:/project', true];
        yield 'single character path' => ['C', false];
        yield 'two characters without colon' => ['CD', false];
        yield 'unix path' => ['/var/www', false];
    }

    /** @return iterable<string, array{string, string}> */
    public static function provideNormalizePathForComparisonCases(): iterable
    {
        yield 'backslashes become slashes' => ['C:\\Project\\includes\\All.mk', 'C:/Project/includes/All.mk'];
        yield 'extended path prefix stripped' => ['//?/C:/Project/All.mk', 'C:/Project/All.mk'];
        yield 'unix path unchanged' => ['/tmp/project/file.mk', '/tmp/project/file.mk'];
    }

    #[Test]
    #[DataProvider('providePathHasWindowsDriveLetterCases')]
    public function pathHasWindowsDriveLetter(string $path, bool $expected): void
    {
        $method = new ReflectionMethod(IncludeLoader::class, 'pathHasWindowsDriveLetter');
        self::assertSame($expected, $method->invoke(null, $path));
    }

    #[Test]
    #[DataProvider('provideNormalizePathForComparisonCases')]
    public function normalizePathForComparison(string $path, string $expected): void
    {
        $method = new ReflectionMethod(IncludeLoader::class, 'normalizePathForComparison');
        self::assertSame($expected, $method->invoke(null, $path));
    }

    #[Test]
    public function normalizedRootPathWithTrailingSlashPreventsPrefixAmbiguity(): void
    {
        $method = new ReflectionMethod(IncludeLoader::class, 'normalizedRootPathWithTrailingSlash');
        self::assertSame('c:/project/', $method->invoke(null, 'c:/project'));
        self::assertSame('c:/project/includes/', $method->invoke(null, 'c:/project/includes/'));
    }

    #[Test]
    public function shouldComparePathsCaseInsensitivelyWhenEitherPathHasDriveLetter(): void
    {
        $method = new ReflectionMethod(IncludeLoader::class, 'shouldComparePathsCaseInsensitively');
        self::assertTrue($method->invoke(null, 'C:/Project/All.mk', '/tmp/includes/'));
        self::assertFalse($method->invoke(null, '/tmp/project/All.mk', '/tmp/includes/'));
    }

    #[Test]
    #[DataProvider('provideIsRealPathInsideRootCases')]
    public function isRealPathInsideRoot(string $fileRealPath, string $rootRealPath, bool $expected): void
    {
        $method = new ReflectionMethod(IncludeLoader::class, 'isRealPathInsideRoot');
        self::assertSame($expected, $method->invoke(null, $fileRealPath, $rootRealPath));
    }

    #[Test]
    public function missingIncludeContentsIsEmptyString(): void
    {
        $method = new ReflectionMethod(IncludeLoader::class, 'missingIncludeContents');
        self::assertSame('', $method->invoke(null));
    }

    #[Test]
    public function includeCandidateExistsReflectsFilePresence(): void
    {
        $root = $this->getTmpDir() . 'include-candidate/';
        mkdir($root);
        $existing = $root . 'exists.mk';
        file_put_contents($existing, "target:\n");
        $method = new ReflectionMethod(IncludeLoader::class, 'includeCandidateExists');
        self::assertTrue($method->invoke(null, $existing));
        self::assertFalse($method->invoke(null, $root . 'missing.mk'));
    }

    #[Test]
    public function loadDoesNotLogIncludingMessageForMissingIncludeFile(): void
    {
        $root      = $this->getTmpDir();
        $reference = $root . 'reference/';
        mkdir($reference . 'includes', 0755, true);
        $context = ProjectSandbox::context($root, $reference);

        self::assertSame("\n", IncludeLoader::load($context, "include includes/Missing.mk\n"));
        self::assertInstanceOf(CapturingNullIO::class, $context->io);
        self::assertStringNotContainsString('Including:', $context->io->output());
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
        $loadInclude            = new ReflectionMethod(IncludeLoader::class, 'loadInclude');
        $missingIncludeContents = new ReflectionMethod(IncludeLoader::class, 'missingIncludeContents');

        self::assertSame(
            $missingIncludeContents->invoke(null),
            $loadInclude->invoke(null, ProjectSandbox::capturingIo(), $reference, 'includes/Missing.mk'),
        );
    }

    #[Test]
    public function loadIncludeReturnsEmptyStringWhenRealPathCannotBeResolved(): void
    {
        if (! ProjectSandbox::canCreateSymlinks()) {
            self::markTestSkipped('Symlink tests cannot run when symlink creation is unavailable.');
        }

        $root      = $this->getTmpDir();
        $reference = $root . 'reference/';
        mkdir($reference . 'includes', 0755, true);
        ProjectSandbox::createBrokenSymlink($reference . 'includes/Broken.mk');
        $method = new ReflectionMethod(IncludeLoader::class, 'loadInclude');

        self::assertSame(
            '',
            $method->invoke(null, ProjectSandbox::capturingIo(), $reference . 'includes' . DIRECTORY_SEPARATOR, 'Broken.mk'),
        );
    }

    #[Test]
    public function loadIncludeReturnsEmptyStringForReadableDirectoryWithoutIoMessage(): void
    {
        $root      = $this->getTmpDir();
        $reference = $root . 'reference/';
        mkdir($reference . 'includes/Directory.mk', 0755, true);
        $io     = ProjectSandbox::capturingIo();
        $method = new ReflectionMethod(IncludeLoader::class, 'loadInclude');

        self::assertSame('', $method->invoke(null, $io, $reference . 'includes' . DIRECTORY_SEPARATOR, 'Directory.mk'));
        self::assertSame('', $io->output());
    }

    #[Test]
    public function loadIncludeReturnsExactContentsAndWritesIncludingMessage(): void
    {
        $root      = $this->getTmpDir();
        $reference = $root . 'reference/';
        mkdir($reference . 'includes', 0755, true);
        $contents = "exact-include:\n\t@echo exact\n";
        file_put_contents($reference . 'includes/Exact.mk', $contents);
        $io     = ProjectSandbox::capturingIo();
        $method = new ReflectionMethod(IncludeLoader::class, 'loadInclude');

        self::assertSame($contents, $method->invoke(null, $io, $reference . 'includes' . DIRECTORY_SEPARATOR, 'Exact.mk'));
        self::assertSame(
            '<info>wyrihaximus/makefiles:</info> Including: Exact.mk',
            str_replace(["\r", "\n"], '', $io->output()),
        );
    }

    #[Test]
    public function loadReturnsExactMergedStringForSingleInclude(): void
    {
        $root      = $this->getTmpDir();
        $reference = $root . 'reference/';
        mkdir($reference . 'includes', 0755, true);
        $includeBody = "merged-target:\n";
        file_put_contents($reference . 'includes/Merged.mk', $includeBody);

        $result = IncludeLoader::load(
            ProjectSandbox::context($root, $reference),
            "before\ninclude includes/Merged.mk\nafter\n",
        );

        self::assertSame("before\n" . $includeBody . "\nafter\n", $result);
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
