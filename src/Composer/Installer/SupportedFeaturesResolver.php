<?php

declare(strict_types=1);

namespace WyriHaximus\Makefiles\Composer\Installer;

use WyriHaximus\Makefiles\Composer\SupportedFeatures;

use function array_key_exists;
use function file_exists;
use function file_get_contents;
use function in_array;
use function is_array;
use function is_bool;
use function is_string;
use function str_contains;
use function str_ends_with;

final class SupportedFeaturesResolver
{
    private function __construct()
    {
    }

    /**
     * @param array<mixed>     $json
     * @param non-empty-string $rootPackagePath
     *
     * @return array<string, bool>
     */
    public static function resolve(array $json, Requirements $requirements, string $rootPackagePath = '.'): array
    {
        /** @var array<string, bool> $supportedFeatures */
        $supportedFeatures = SupportedFeatures::DEFAULTS;

        $supportedFeatures = self::detectPackageOrProject($supportedFeatures, $json, $rootPackagePath);

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

        if (self::isOpenTelemetryInstrumentationPackage($json, $requirements)) {
            $supportedFeatures[SupportedFeatures::FEATURE_OPENTELEMETRY_INSTRUMENTATION] = true;
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

    /**
     * @param array<string, bool> $supportedFeatures
     * @param array<mixed>        $json
     * @param non-empty-string    $rootPackagePath
     *
     * @return array<string, bool>
     */
    private static function detectPackageOrProject(array $supportedFeatures, array $json, string $rootPackagePath): array
    {
        if (self::isProjectType($json) || self::isProjectByWorkflow($rootPackagePath)) {
            $supportedFeatures[SupportedFeatures::FEATURE_PROJECT] = true;
            $supportedFeatures[SupportedFeatures::FEATURE_PACKAGE] = false;
        }

        return $supportedFeatures;
    }

    /** @param array<mixed> $json */
    private static function isProjectType(array $json): bool
    {
        return array_key_exists('type', $json) && $json['type'] === 'project';
    }

    /** @param non-empty-string $rootPackagePath */
    private static function isProjectByWorkflow(string $rootPackagePath): bool
    {
        $ciWorkflowPath = $rootPackagePath . '.github/workflows/ci.yaml';
        if (! file_exists($ciWorkflowPath)) {
            return false;
        }

        $content = file_get_contents($ciWorkflowPath);

        return $content !== false && str_contains($content, 'project.yaml');
    }

    /** @param array<mixed> $json */
    private static function isOpenTelemetryInstrumentationPackage(array $json, Requirements $requirements): bool
    {
        if (! in_array('ext-opentelemetry', $requirements->withoutDev, true)) {
            return false;
        }

        if (! array_key_exists('autoload', $json) || ! is_array($json['autoload'])) {
            return false;
        }

        if (! array_key_exists('files', $json['autoload']) || ! is_array($json['autoload']['files'])) {
            return false;
        }

        foreach ($json['autoload']['files'] as $file) {
            if (! is_string($file)) {
                continue;
            }

            if (str_ends_with($file, '_register.php')) {
                return true;
            }
        }

        return false;
    }
}
