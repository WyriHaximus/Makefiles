<?php

declare(strict_types=1);

namespace WyriHaximus\Makefiles\Composer\Installer;

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
        preg_match_all(
            '/([A-Z_]+)=when_target_exists_in_extra\(([a-z0-9-]+),\s+([A-Za-z0-9\"-]+),\s+([A-Za-z0-9,\"-]+)\)/',
            $makefileContents,
            $matches,
            PREG_OFFSET_CAPTURE,
        );

        if ($matches[0] === []) {
            return $makefileContents;
        }

        $etcMakefilePath = $rootPackagePath . 'etc' . DIRECTORY_SEPARATOR . 'Makefile';
        $etcMakefile     = is_file($etcMakefilePath) ? file_get_contents($etcMakefilePath) : false;

        foreach ($matches[0] as $i => $fullLine) {
            $targetName = $matches[2][$i][0];
            $hasTarget  = is_string($etcMakefile)
                && preg_match('/^' . preg_quote($targetName, '/') . ':/m', $etcMakefile) === 1;

            $makefileContents = str_replace(
                $fullLine[0],
                $matches[1][$i][0] . '=' . ($hasTarget ? $matches[3][$i][0] : $matches[4][$i][0]),
                $makefileContents,
            );
        }

        return $makefileContents;
    }
}
