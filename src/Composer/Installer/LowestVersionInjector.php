<?php

declare(strict_types=1);

namespace WyriHaximus\Makefiles\Composer\Installer;

use function array_key_exists;
use function explode;
use function file_get_contents;
use function is_array;
use function is_file;
use function is_float;
use function is_int;
use function is_string;
use function json_decode;
use function preg_match_all;
use function str_replace;

use const PREG_OFFSET_CAPTURE;

final class LowestVersionInjector
{
    private function __construct()
    {
    }

    public static function inject(string $makefileContents, string $rootPackagePath): string
    {
        preg_match_all(
            '/([A-Z_]+)=lowest_cleaned_version_in_tree_from_file\(\"([A-Za-z0-9.-\/]+)\",\s+\"([A-Za-z0-9-.]+)\"\)/',
            $makefileContents,
            $matchesThirdPass,
            PREG_OFFSET_CAPTURE,
        );

        foreach ($matchesThirdPass[0] as $i => $fullLine) {
            $filePath     = $rootPackagePath . $matchesThirdPass[2][$i][0];
            $fileContents = is_file($filePath) ? file_get_contents($filePath) : false;
            $json         = is_string($fileContents) ? json_decode($fileContents, true) : null;
            $version      = self::cleanVersionToMajorMinor(self::getValueFromTree(
                is_array($json) ? $json : [],
                explode('.', $matchesThirdPass[3][$i][0]),
            ));

            $makefileContents = str_replace(
                $fullLine[0],
                $matchesThirdPass[1][$i][0] . '="' . $version . '"',
                $makefileContents,
            );
        }

        return $makefileContents;
    }

    /**
     * @param array<mixed>  $array
     * @param array<string> $keys
     */
    private static function getValueFromTree(array $array, array $keys): string
    {
        $current = $array;
        foreach ($keys as $key) {
            if (! is_array($current) || ! array_key_exists($key, $current)) {
                return '0';
            }

            $current = $current[$key];
        }

        if (is_string($current) || is_int($current) || is_float($current)) {
            return (string) $current;
        }

        return '0';
    }

    private static function cleanVersionToMajorMinor(string $version): string
    {
        $parts = explode('.', $version, 3);
        $major = $parts[0];
        $minor = $parts[1] ?? '0';

        return $major . '.' . $minor;
    }
}
