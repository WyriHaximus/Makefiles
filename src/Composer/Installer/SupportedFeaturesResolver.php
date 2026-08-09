<?php

declare(strict_types=1);

namespace WyriHaximus\Makefiles\Composer\Installer;

use WyriHaximus\Makefiles\Composer\SupportedFeatures;

use function array_key_exists;
use function is_array;
use function is_bool;

final class SupportedFeaturesResolver
{
    private function __construct()
    {
    }

    /**
     * @param array<mixed> $json
     *
     * @return array<string, bool>
     */
    public static function resolve(array $json, Requirements $requirements): array
    {
        /** @var array<string, bool> $supportedFeatures */
        $supportedFeatures = SupportedFeatures::DEFAULTS;

        foreach ($requirements->withoutDev as $packageOrExtension) {
            if ($packageOrExtension === 'ext-parallel') {
                $supportedFeatures[SupportedFeatures::FEATURE_MACOS]   = false;
                $supportedFeatures[SupportedFeatures::FEATURE_WINDOWS] = false;
                $supportedFeatures[SupportedFeatures::FEATURE_ZTS]     = true;
            }

            if ($packageOrExtension !== 'ext-pcntl') {
                continue;
            }

            $supportedFeatures[SupportedFeatures::FEATURE_MACOS]   = false;
            $supportedFeatures[SupportedFeatures::FEATURE_WINDOWS] = false;
        }

        if (
            array_key_exists('extra', $json)
            && is_array($json['extra'])
            && array_key_exists('wyrihaximus', $json['extra'])
            && is_array($json['extra']['wyrihaximus'])
            && array_key_exists('supported-features', $json['extra']['wyrihaximus'])
            && is_array($json['extra']['wyrihaximus']['supported-features'])
        ) {
            foreach ($json['extra']['wyrihaximus']['supported-features'] as $feature => $featureSupported) {
                if (! array_key_exists($feature, SupportedFeatures::DEFAULTS)) {
                    continue;
                }

                if (! is_bool($featureSupported)) {
                    continue;
                }

                $supportedFeatures[$feature] = $featureSupported;
            }
        }

        return $supportedFeatures;
    }
}
