<?php

declare(strict_types=1);

namespace WyriHaximus\Makefiles\Composer\Installer;

use function array_any;
use function explode;
use function file_get_contents;
use function is_file;
use function is_string;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function str_replace;

use const DIRECTORY_SEPARATOR;
use const PREG_OFFSET_CAPTURE;

final class ExtraServicesInjector
{
    private function __construct()
    {
    }

    /**
     * Resolves `when_target_exists_in_extra(...)` placeholders depending on whether the package
     * defines the given target in `etc/Makefile`.
     */
    public static function inject(string $makefileContents, string $rootPackagePath): string
    {
        $matchCount = preg_match_all(
            '/([A-Z_]+)=when_target_exists_in_extra\(([a-z0-9.-]+),\s+([A-Za-z0-9\"-]+),\s+([A-Za-z0-9,\"-]+)\)/',
            $makefileContents,
            $matches,
            PREG_OFFSET_CAPTURE,
        );

        $result = $makefileContents;

        if ($matchCount !== 0) {
            $etcMakefile = self::readEtcMakefile($rootPackagePath);

            foreach ($matches[0] as $i => $fullLine) {
                $targetName = $matches[2][$i][0];
                $hasTarget  = is_string($etcMakefile) && self::etcMakefileDefinesTarget($etcMakefile, $targetName);

                $result = str_replace(
                    $fullLine[0],
                    $matches[1][$i][0] . '=' . ($hasTarget ? $matches[3][$i][0] : $matches[4][$i][0]),
                    $result,
                );
            }
        }

        return $result;
    }

    private static function readEtcMakefile(string $rootPackagePath): string|false
    {
        $etcMakefilePath = $rootPackagePath . 'etc' . DIRECTORY_SEPARATOR . 'Makefile';

        if (! is_file($etcMakefilePath)) {
            return false;
        }

        return file_get_contents($etcMakefilePath);
    }

    private static function etcMakefileDefinesTarget(string $etcMakefile, string $targetName): bool
    {
        $targetHeaderPattern = self::targetHeaderPattern($targetName);

        return array_any(
            explode("\n", self::normalizeLineEndings($etcMakefile)),
            static fn (string $line): bool => preg_match($targetHeaderPattern, $line) === 1,
        );
    }

    private static function targetHeaderPattern(string $targetName): string
    {
        return '/^' . preg_quote($targetName, '/') . ':/';
    }

    private static function normalizeLineEndings(string $contents): string
    {
        return str_replace(["\r\n", "\r"], "\n", $contents);
    }
}
