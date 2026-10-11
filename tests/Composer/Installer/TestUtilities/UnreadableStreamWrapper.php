<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Makefiles\Composer\Installer\TestUtilities;

// phpcs:disable PSR1.Methods.CamelCapsMethodName,Squiz.NamingConventions.ValidVariableName -- PHP stream wrapper API
final class UnreadableStreamWrapper
{
    public mixed $context = null;

    /** @return array<string, int> */
    public function url_stat(string $path, int $flags): array
    {
        return [
            'dev' => 0,
            'ino' => 0,
            'mode' => 0100644,
            'nlink' => 1,
            'uid' => 0,
            'gid' => 0,
            'rdev' => 0,
            'size' => 0,
            'atime' => 0,
            'mtime' => 0,
            'ctime' => 0,
            'blksize' => 0,
            'blocks' => 0,
        ];
    }

    /** @param-out string|null $openedPath */
    public function stream_open(string $path, string $mode, int $options, mixed &$openedPath): bool
    {
        return false;
    }
}
// phpcs:enable
