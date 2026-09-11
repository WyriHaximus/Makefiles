<?php

declare(strict_types=1);

namespace WyriHaximus\Makefiles\Composer\Installer;

use function array_key_exists;
use function file_get_contents;
use function is_array;
use function is_file;
use function is_readable;
use function is_string;
use function json_decode;
use function strtolower;

final class AsciiBannerGenerator
{
    private function __construct()
    {
    }

    /** @return list<string> */
    public static function forText(string $text): array
    {
        return FigletFont::mini()->render(strtolower($text));
    }

    /** @return list<string> */
    public static function forPackage(string $rootPackagePath): array
    {
        $packageName = self::readPackageName($rootPackagePath);
        if ($packageName === null) {
            return [];
        }

        return self::forText($packageName);
    }

    private static function readPackageName(string $rootPackagePath): string|null
    {
        $composerJsonPath = $rootPackagePath . 'composer.json';
        if (! is_file($composerJsonPath) || ! is_readable($composerJsonPath)) {
            return null;
        }

        $json = json_decode((string) file_get_contents($composerJsonPath), true);
        if (! is_array($json) || ! array_key_exists('name', $json) || ! is_string($json['name']) || $json['name'] === '') {
            return null;
        }

        return $json['name'];
    }
}
