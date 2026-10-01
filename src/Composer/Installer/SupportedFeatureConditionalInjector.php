<?php

declare(strict_types=1);

namespace WyriHaximus\Makefiles\Composer\Installer;

use function array_key_exists;
use function preg_match_all;
use function str_replace;
use function strlen;
use function substr;
use function trim;

use const PREG_OFFSET_CAPTURE;

final class SupportedFeatureConditionalInjector
{
    private function __construct()
    {
    }

    /** @param array<string, bool> $supportedFeatures */
    public static function inject(string $makefileContents, array $supportedFeatures): string
    {
        preg_match_all(
            '/when_supported_feature\("([a-z-]+)",\s+(""|[^,]+),\s+([^)]+)\)/',
            $makefileContents,
            $matches,
            PREG_OFFSET_CAPTURE,
        );

        foreach ($matches[0] as $i => $fullMatch) {
            $feature   = $matches[1][$i][0];
            $whenTrue  = self::normalizeValue($matches[2][$i][0]);
            $whenFalse = self::normalizeValue($matches[3][$i][0]);
            $enabled   = array_key_exists($feature, $supportedFeatures) && $supportedFeatures[$feature];

            $makefileContents = str_replace(
                $fullMatch[0],
                $enabled ? $whenTrue : $whenFalse,
                $makefileContents,
            );
        }

        return $makefileContents;
    }

    private static function normalizeValue(string $value): string
    {
        $value = trim($value);

        if ($value === '""') {
            return '';
        }

        if (strlen($value) >= 2 && $value[0] === '"' && $value[strlen($value) - 1] === '"') {
            return substr($value, 1, -1);
        }

        return $value;
    }
}
