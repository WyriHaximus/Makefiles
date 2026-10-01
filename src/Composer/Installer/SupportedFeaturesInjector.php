<?php

declare(strict_types=1);

namespace WyriHaximus\Makefiles\Composer\Installer;

use function array_filter;
use function array_keys;
use function count;
use function json_encode;
use function str_replace;

use const JSON_THROW_ON_ERROR;

final class SupportedFeaturesInjector
{
    private function __construct()
    {
    }

    /** @param array<string, bool> $supportedFeatures */
    public static function inject(string $makefileContents, array $supportedFeatures): string
    {
        $supportedFeaturesList = array_keys(array_filter($supportedFeatures, static fn (bool $featureSupported): bool => $featureSupported));
        $supportedFeaturesJson = json_encode($supportedFeaturesList, JSON_THROW_ON_ERROR);

        $makefileContents = str_replace('supported-features(list)', '@echo "' . str_replace('"', '\"', $supportedFeaturesJson) . '" ## Count: ' . count($supportedFeaturesList), $makefileContents);

        return str_replace('supported-features(raw)', $supportedFeaturesJson, $makefileContents);
    }
}
