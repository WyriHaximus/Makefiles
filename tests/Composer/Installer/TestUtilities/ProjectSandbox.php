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
use function file_put_contents;
use function function_exists;
use function is_dir;
use function is_file;
use function is_link;
use function mkdir;
use function opendir;
use function posix_geteuid;
use function readdir;
use function rtrim;
use function strtoupper;
use function substr;
use function symlink;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use const DIRECTORY_SEPARATOR;
use const PHP_OS;
use const PHP_OS_FAMILY;

final class ProjectSandbox
{
    private const array PACKAGE_DIRECTORIES = ['src', 'templates', 'includes', 'etc'];

    private static bool|null $canCreateSymlinks = null;

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
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return false;
        }

        return ! function_exists('posix_geteuid') || posix_geteuid() !== 0;
    }

    public static function canCreateSymlinks(): bool
    {
        if (self::$canCreateSymlinks !== null) {
            return self::$canCreateSymlinks;
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            return self::$canCreateSymlinks = true;
        }

        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('wh-st-', true);
        $link   = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('wh-sl-', true);
        file_put_contents($target, '');

        self::$canCreateSymlinks = symlink($target, $link) && is_link($link);

        if (is_link($link)) {
            unlink($link);
        }

        unlink($target);

        return self::$canCreateSymlinks;
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
