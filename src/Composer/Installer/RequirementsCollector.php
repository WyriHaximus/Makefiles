<?php

declare(strict_types=1);

namespace WyriHaximus\Makefiles\Composer\Installer;

use Composer\Composer;
use Exception;
use FilesystemIterator;
use GlobIterator;
use SplFileInfo;

use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_unique;
use function array_values;
use function assert;
use function file_get_contents;
use function is_array;
use function is_dir;
use function is_file;
use function is_readable;
use function is_string;
use function json_decode;
use function rtrim;
use function str_replace;

final class RequirementsCollector
{
    private const int VENDOR_COMPOSER_JSON_GLOB_FLAGS = FilesystemIterator::KEY_AS_FILENAME | FilesystemIterator::SKIP_DOTS;

    private function __construct()
    {
    }

    public static function collect(Composer $composer): Requirements
    {
        $vendorDir = self::getVendorDir($composer);

        return new Requirements(
            self::allRequirements($composer, $vendorDir),
            self::requirementsWithoutDev($composer, $vendorDir),
        );
    }

    /**
     * @param non-empty-string $vendorDir
     *
     * @return list<string>
     */
    private static function allRequirements(Composer $composer, string $vendorDir): array
    {
        $vendorPackages = [];
        foreach (self::retrieveRequiredPackagesAndExtensions($vendorDir, true) as $package) {
            $vendorPackages[] = $package;
        }

        return array_values(array_unique([
            ...array_keys($composer->getPackage()->getRequires()),
            ...array_keys($composer->getPackage()->getDevRequires()),
            ...$vendorPackages,
        ]));
    }

    /**
     * @param non-empty-string $vendorDir
     *
     * @return list<string>
     */
    private static function requirementsWithoutDev(Composer $composer, string $vendorDir): array
    {
        $vendorPackages = [];
        foreach (self::retrieveRequiredPackagesAndExtensions($vendorDir, false) as $package) {
            $vendorPackages[] = $package;
        }

        return array_values(array_unique([
            ...array_keys($composer->getPackage()->getRequires()),
            ...$vendorPackages,
        ]));
    }

    /** @return non-empty-string */
    private static function getVendorDir(Composer $composer): string
    {
        $vendorDir = $composer->getConfig()->get('vendor-dir');
        if ($vendorDir === '' || ! is_dir($vendorDir)) {
            throw new Exception('vendor-dir must be a string');
        }

        return $vendorDir;
    }

    /**
     * @param non-empty-string $vendorDir
     *
     * @return iterable<string>
     */
    private static function retrieveRequiredPackagesAndExtensions(string $vendorDir, bool $includeDev): iterable
    {
        $composerJsonGlobPattern = self::vendorComposerJsonGlobPattern($vendorDir);

        foreach (new GlobIterator($composerJsonGlobPattern, self::VENDOR_COMPOSER_JSON_GLOB_FLAGS | FilesystemIterator::SKIP_DOTS) as $node) {
            /** @var SplFileInfo $node */
            yield from self::packagesFromVendorComposerJsonNode($node, $includeDev);
        }
    }

    /** @return iterable<string> */
    private static function packagesFromVendorComposerJsonNode(SplFileInfo $node, bool $includeDev): iterable
    {
        $realPath = $node->getRealPath();
        if ($realPath === false) {
            return;
        }

        if (! self::vendorComposerJsonPathIsAccessible($realPath)) {
            return;
        }

        $composerJson = file_get_contents($realPath);
        assert(is_string($composerJson));

        $json = json_decode($composerJson, true);
        if (! is_array($json)) {
            return;
        }

        if (array_key_exists('require', $json) && is_array($json['require'])) {
            foreach (array_filter(array_keys($json['require']), is_string(...)) as $package) {
                yield $package;
            }
        }

        if (! array_key_exists('require-dev', $json) || ! is_array($json['require-dev'])) {
            return;
        }

        if (! $includeDev) {
            return;
        }

        foreach (array_filter(array_keys($json['require-dev']), is_string(...)) as $package) {
            yield $package;
        }
    }

    private static function vendorComposerJsonPathIsAccessible(string $realPath): bool
    {
        return is_file($realPath) && is_readable($realPath);
    }

    /** @return non-empty-string */
    private static function vendorComposerJsonGlobPattern(string $vendorDir): string
    {
        // GlobIterator requires forward slashes; vendor-dir uses backslashes on Windows.
        return str_replace('\\', '/', rtrim($vendorDir, '/\\')) . '/*/*/composer.json';
    }
}
