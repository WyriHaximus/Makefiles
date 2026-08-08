<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Makefiles\Composer\Installer\TestUtilities;

use function is_dir;
use function is_link;
use function rmdir;
use function rtrim;
use function scandir;
use function unlink;

use const DIRECTORY_SEPARATOR;

final class ProjectWorkspaceGuard
{
    private const array ARTIFACT_DIRECTORIES = [
        'tmp-relative-root',
        'tmp',
    ];

    public static function backupOnce(): void
    {
        // Tests write Makefiles only under temporary directories; nothing to back up in the project root.
    }

    public static function restore(): void
    {
        $root = self::projectRoot();

        foreach (self::ARTIFACT_DIRECTORIES as $directory) {
            self::removeDirectoryIfExists($root . $directory);
        }
    }

    private static function removeDirectoryIfExists(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $entries = scandir($path);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $entryPath = $path . DIRECTORY_SEPARATOR . $entry;
            if (is_link($entryPath)) {
                unlink($entryPath);

                continue;
            }

            if (is_dir($entryPath)) {
                self::removeDirectoryIfExists($entryPath);

                continue;
            }

            unlink($entryPath);
        }

        rmdir($path);
    }

    private static function projectRoot(): string
    {
        return rtrim(ProjectSandbox::packageSourceRoot(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }
}
