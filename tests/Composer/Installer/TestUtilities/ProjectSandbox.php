<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Makefiles\Composer\Installer\TestUtilities;

use PHPUnit\Framework\Assert;
use RuntimeException;
use WyriHaximus\Makefiles\Composer\Installer\MakefileGenerationContext;
use WyriHaximus\Makefiles\Composer\Installer\Requirements;
use WyriHaximus\Makefiles\Composer\SupportedFeatures;

use function closedir;
use function copy;
use function dirname;
use function file_exists;
use function function_exists;
use function is_dir;
use function is_file;
use function mkdir;
use function opendir;
use function posix_geteuid;
use function readdir;
use function rtrim;

use const DIRECTORY_SEPARATOR;

final class ProjectSandbox
{
    private const array PACKAGE_DIRECTORIES = ['src', 'templates', 'includes', 'etc'];

    private function __construct()
    {
    }

    public static function capturingIo(): CapturingNullIO
    {
        return new CapturingNullIO();
    }

    /** @param array<string, bool> $supportedFeatures */
    public static function context(
        string $rootPackagePath,
        string $referenceRoot,
        Requirements $requirements = new Requirements([], []),
        array $supportedFeatures = SupportedFeatures::DEFAULTS,
    ): MakefileGenerationContext {
        return new MakefileGenerationContext(
            self::capturingIo(),
            $rootPackagePath,
            $referenceRoot,
            $requirements,
            $supportedFeatures,
        );
    }

    public static function createProjectRoot(string $tmpDir): string
    {
        $projectRoot = $tmpDir . 'project-root' . DIRECTORY_SEPARATOR;

        if (! file_exists($projectRoot) && ! mkdir($projectRoot, 0755, true)) {
            Assert::fail('Failed to create project sandbox directory: ' . $projectRoot);
        }

        return $projectRoot;
    }

    /** @return array{root: string, reference: string} */
    public static function createStubReferenceRoot(string $tmpDir): array
    {
        $root      = self::createProjectRoot($tmpDir);
        $reference = $root . 'reference' . DIRECTORY_SEPARATOR;
        mkdir($reference . 'templates', 0755, true);
        mkdir($reference . 'includes', 0755, true);
        mkdir($root . 'etc', 0755, true);

        return ['root' => $root, 'reference' => $reference];
    }

    public static function mirrorDirectory(string $src, string $dst): void
    {
        $dir = opendir($src);
        if ($dir === false) {
            throw new RuntimeException('Failed to open directory');
        }

        if (! file_exists($dst)) {
            mkdir($dst);
        }

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $srcPath = $src . DIRECTORY_SEPARATOR . $file;
            $dstPath = $dst . DIRECTORY_SEPARATOR . $file;

            if (is_dir($srcPath)) {
                self::mirrorDirectory($srcPath, $dstPath);
            } elseif (is_file($srcPath)) {
                copy($srcPath, $dstPath);
            }
        }

        closedir($dir);
    }

    public static function mirrorPackage(string $src, string $dst): void
    {
        if (! file_exists($dst) && ! mkdir($dst, 0755, true)) {
            throw new RuntimeException('Failed to create package mirror directory: ' . $dst);
        }

        foreach (self::PACKAGE_DIRECTORIES as $directory) {
            $srcPath = $src . $directory;
            if (! is_dir($srcPath)) {
                continue;
            }

            self::mirrorDirectory($srcPath, $dst . DIRECTORY_SEPARATOR . $directory);
        }

        $composerJson = $src . 'composer.json';
        if (! is_file($composerJson)) {
            return;
        }

        copy($composerJson, $dst . DIRECTORY_SEPARATOR . 'composer.json');
    }

    public static function canSimulateUnreadableFiles(): bool
    {
        return ! function_exists('posix_geteuid') || posix_geteuid() !== 0;
    }

    public static function packageSourceRoot(): string
    {
        return dirname(__DIR__, 4);
    }

    public static function mirroredProject(string $tmpDir): string
    {
        $root = rtrim($tmpDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        self::mirrorPackage(self::packageSourceRoot() . DIRECTORY_SEPARATOR, $root);

        return $root;
    }
}
