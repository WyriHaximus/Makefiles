<?php

declare(strict_types=1);

namespace WyriHaximus\Makefiles\Composer\Installer;

use function array_filter;
use function array_intersect;
use function array_values;
use function count;
use function is_array;
use function is_string;
use function json_decode;
use function preg_match_all;
use function str_replace;

use const PREG_OFFSET_CAPTURE;

final class RequirementConditionalInjector
{
    private function __construct()
    {
    }

    /** @param list<string> $requiredPackagesAndExtensions */
    public static function inject(string $makefileContents, array $requiredPackagesAndExtensions): string
    {
        preg_match_all(
            '/([A-Z_]+)(=|\?=)when_in_requirements\(([A-Za-z0-9,\/\[\]\"-]+),\s+([A-Za-z0-9\"-]+),\s+([A-Za-z0-9,\"-]+)\)/',
            $makefileContents,
            $matchesSecondPass,
            PREG_OFFSET_CAPTURE,
        );

        foreach ($matchesSecondPass[0] as $i => $fullLine) {
            $requiredPackagesJson = json_decode($matchesSecondPass[3][$i][0], true);
            $requiredPackages     = is_array($requiredPackagesJson) ? array_values(array_filter($requiredPackagesJson, is_string(...))) : [];

            $makefileContents = str_replace(
                $fullLine[0],
                $matchesSecondPass[1][$i][0] . $matchesSecondPass[2][$i][0] . (count(array_intersect(
                    $requiredPackages,
                    $requiredPackagesAndExtensions,
                )) > 0 ? $matchesSecondPass[4][$i][0] : $matchesSecondPass[5][$i][0]),
                $makefileContents,
            );
        }

        return $makefileContents;
    }
}
