<?php

declare(strict_types=1);

namespace WyriHaximus\Makefiles\Composer\Installer;

use function array_values;
use function implode;
use function ksort;
use function preg_match;
use function preg_match_all;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strpos;
use function substr;
use function trim;

use const SORT_STRING;

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

    public static function inject(string $makefileContents, string $rootPackagePath): string
    {
        $bannerLines = AsciiBannerGenerator::forPackage($rootPackagePath);
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
            $helpLine       = self::buildHelpLine($target, $matches[2][$i]);
            $isMigration    = str_starts_with($target, 'migrations-');
            $hasContribFlag = self::helpLineHasContribExecutorFlag($fullLine);

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

        foreach (['main', 'migrations', 'contrib'] as $helpType) {
            $entries = self::sortHelpEntriesByTargetName($helpTargets[$helpType]);

            $helpBannerLines = match ($helpType) {
                'main' => $bannerLines,
                'migrations' => AsciiBannerGenerator::forText('migrations'),
                'contrib' => AsciiBannerGenerator::forText('contrib'),
            };

            $makefileContents = str_replace(
                'help(' . $helpType . ')',
                self::formatHelpRecipe($entries, $helpBannerLines),
                $makefileContents,
            );
        }

        return $makefileContents;
    }

    /**
     * @param list<array{0: string, 1: string}> $entries
     * @param list<string>                      $bannerLines
     */
    private static function formatHelpRecipe(array $entries, array $bannerLines = []): string
    {
        $lines = [];

        foreach ($bannerLines as $bannerLine) {
            $lines[] = '@printf "%s\n" ' . self::shellQuote($bannerLine);
        }

        if ($bannerLines !== []) {
            $lines[] = '@printf "\n"';
        }

        $lines = [...$lines, ...self::HELP_HEADER_LINES];

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

    /**
     * @param list<array{0: string, 1: string}> $entries
     *
     * @return list<array{0: string, 1: string}>
     */
    private static function sortHelpEntriesByTargetName(array $entries): array
    {
        $entriesByTarget = [];
        foreach ($entries as $entry) {
            $entriesByTarget[$entry[0]] = $entry;
        }

        ksort($entriesByTarget, SORT_STRING);

        return array_values($entriesByTarget);
    }

    private static function buildHelpLine(string $target, string $rawHelpSuffix): string
    {
        return trim($target . ': ## ' . substr($rawHelpSuffix, 0, self::helpSuffixSegmentEnd($rawHelpSuffix)));
    }

    private static function helpSuffixSegmentEnd(string $rawHelpSuffix): int
    {
        $hashPosition = strpos($rawHelpSuffix, '#');

        return $hashPosition !== false ? $hashPosition : strlen($rawHelpSuffix);
    }

    private static function helpLineHasContribExecutorFlag(string $fullLine): bool
    {
        if (preg_match('/##\*([AEDILCH]+)\*/', $fullLine, $typeMatch) !== 1) {
            return false;
        }

        return self::executorFlagsIncludeE($typeMatch[1]);
    }

    private static function executorFlagsIncludeE(string $flags): bool
    {
        return str_contains($flags, 'E') && ! str_contains($flags, '*');
    }
}
