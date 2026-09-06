<?php

declare(strict_types=1);

namespace WyriHaximus\Makefiles\Composer\Installer;

use function array_map;
use function array_unique;
use function array_values;
use function count;
use function explode;
use function implode;
use function in_array;
use function ltrim;
use function preg_match;
use function preg_match_all;
use function str_contains;
use function str_starts_with;
use function trim;

final class ServiceLifecycleInjector
{
    private function __construct()
    {
    }

    /**
     * Expands `service_start(target)` / `service_cleanup(target)` placeholders into a bash recipe
     * with EXIT trap when the named targets exist in the compiled Makefile.
     */
    public static function inject(string $makefileContents): string
    {
        if (! str_contains($makefileContents, 'service_start(') && ! str_contains($makefileContents, 'service_cleanup(')) {
            // @infection-ignore-all
            return $makefileContents;
        }

        $availableTargets = self::extractMakefileTargets($makefileContents);

        $lines  = explode("\n", $makefileContents);
        $output = [];
        $count  = count($lines);
        $index  = 0;

        while ($index < $count) {
            $line = $lines[$index];

            if (preg_match('/^([a-z0-9-]+):/', $line) !== 1) {
                $output[] = $line;
                $index++;

                continue;
            }

            $targetLine = $line;
            $index++;
            $recipeLines = [];

            while ($index < $count && str_starts_with($lines[$index], "\t")) {
                $recipeLines[] = $lines[$index];
                $index++;
            }

            $recipeBody = implode("\n", $recipeLines);

            if (! str_contains($recipeBody, 'service_start(') && ! str_contains($recipeBody, 'service_cleanup(')) {
                $output[] = $targetLine;
                foreach ($recipeLines as $recipeLine) {
                    $output[] = $recipeLine;
                }

                continue;
            }

            $output[] = $targetLine;
            $output[] = self::expandServiceLifecycleRecipe($recipeLines, $availableTargets);
        }

        return implode("\n", $output);
    }

    /**
     * @param list<string> $recipeLines
     * @param list<string> $availableTargets
     */
    private static function expandServiceLifecycleRecipe(array $recipeLines, array $availableTargets): string
    {
        $startTargets   = [];
        $cleanupTargets = [];
        $middleLines    = [];

        foreach ($recipeLines as $recipeLine) {
            if (preg_match('/^\tservice_start\(([a-z0-9-]+)\)/', $recipeLine, $matches) === 1) {
                if (in_array($matches[1], $availableTargets, true)) {
                    $startTargets[] = $matches[1];
                }

                continue;
            }

            if (preg_match('/^\tservice_cleanup\(([a-z0-9-]+)\)/', $recipeLine, $matches) === 1) {
                if (in_array($matches[1], $availableTargets, true)) {
                    $cleanupTargets[] = $matches[1];
                }

                continue;
            }

            $middleLines[] = ltrim($recipeLine, "\t");
        }

        if ($startTargets === [] && $cleanupTargets === []) {
            return implode("\n", array_map(static fn (string $middleLine): string => "\t" . $middleLine, $middleLines));
        }

        $middleCommand = implode(' && ', array_map(trim(...), $middleLines));
        $ciRecipe      = "\t" . $middleCommand;

        $bashLines = [];

        if ($startTargets !== []) {
            $bashLines[] = "\t$(MAKE) " . implode(' ', $startTargets) . '; \\';
        }

        if ($cleanupTargets !== []) {
            $bashLines[] = "\ttrap \"$(MAKE) " . implode(' ', $cleanupTargets) . ' || true" EXIT; \\';
        }

        $bashLines[] = "\t" . $middleCommand;
        $localRecipe = "\t@bash -ec '" . implode("\n", $bashLines) . "'";

        return implode("\n", [
            'ifeq ("$(IN_CI)","TRUE")',
            $ciRecipe,
            'else',
            $localRecipe,
            'endif',
        ]);
    }

    /** @return list<string> */
    private static function extractMakefileTargets(string $makefileContents): array
    {
        preg_match_all('/^([a-z0-9-]+):/m', $makefileContents, $matches);

        return array_values(array_unique($matches[1]));
    }
}
