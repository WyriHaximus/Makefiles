<?php

declare(strict_types=1);

namespace WyriHaximus\Makefiles\Composer\Installer;

use function implode;
use function preg_match;
use function preg_match_all;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strpos;
use function substr;
use function trim;
use function usort;

final class HelpInjector
{
    private const array HELP_HEADER_LINES = [
        '@printf "\033[33mUsage:\033[0m\n"',
        '@printf "  make [target]\n"',
        '@printf "\n"',
        '@printf "\033[33mTargets:\033[0m\n"',
    ];

    private function __construct()
    {
    }

    public static function inject(string $makefileContents): string
    {
        /** @var array<string, list<array{0: string, 1: string}>> $helpTargets */
        $helpTargets = [
            'main' => [],
            'migrations' => [],
            'contrib' => [],
        ];

        preg_match_all(
            '/^([a-zA-Z0-9_-]+):.*?## (.+)$/m',
            $makefileContents,
            $matches,
        );

        foreach ($matches[0] as $i => $fullLine) {
            if (str_contains($fullLine, '##U##')) {
                continue;
            }

            $target         = $matches[1][$i];
            $haspos         = strpos($matches[2][$i], '#');
            $helpLine       = trim(
                $target . ': ## ' . substr(
                    $matches[2][$i],
                    0,
                    $haspos !== false ? $haspos : strlen($matches[2][$i]),
                ),
            );
            $isMigration    = str_starts_with($target, 'migrations-');
            $hasContribFlag = preg_match('/##\*([AEDILCH]+)\*/', $fullLine, $typeMatch) === 1 && str_contains($typeMatch[1], 'E');

            if (! $isMigration) {
                $helpTargets['main'][] = [
                    $target,
                    $helpLine,
                ];
            }

            if ($isMigration) {
                $helpTargets['migrations'][] = [
                    $target,
                    $helpLine,
                ];
            }

            if ($isMigration || ! $hasContribFlag) {
                continue;
            }

            $helpTargets['contrib'][] = [
                $target,
                $helpLine,
            ];
        }

        foreach ($helpTargets as $helpType => $entries) {
            usort($entries, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

            $makefileContents = str_replace(
                'help(' . $helpType . ')',
                self::formatHelpRecipe($entries),
                $makefileContents,
            );
        }

        return $makefileContents;
    }

    /** @param list<array{0: string, 1: string}> $entries */
    private static function formatHelpRecipe(array $entries): string
    {
        $lines = self::HELP_HEADER_LINES;

        foreach ($entries as $entry) {
            $description = substr($entry[1], strlen($entry[0]) + 5);
            $lines[]     = '@printf "  \033[32m%-32s\033[0m %s\n" '
                . self::shellQuote($entry[0])
                . ' '
                . self::shellQuote($description);
        }

        return implode("\n\t", $lines);
    }

    private static function shellQuote(string $value): string
    {
        return "'" . str_replace("'", "'\\''", $value) . "'";
    }
}
