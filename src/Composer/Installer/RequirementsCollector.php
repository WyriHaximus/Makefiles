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
use function iterator_to_array;
use function json_decode;
use function rtrim;
use function str_replace;

final class RequirementsCollector
{
    private function __construct()
    {
    }

    public static function collect(Composer $composer): Requirements
    {
        $vendorDir = self::getVendorDir($composer);

        /** @var list<string> $all */
        $all = array_values(array_unique([
            ...array_keys($composer->getPackage()->getRequires()),
            ...array_keys($composer->getPackage()->getDevRequires()),
            ...iterator_to_array(self::retrieveRequiredPackagesAndExtensions($vendorDir, true), false),
        ]));

        /** @var list<string> $withoutDev */
        $withoutDev = array_values(array_unique([
            ...array_keys($composer->getPackage()->getRequires()),
            ...iterator_to_array(self::retrieveRequiredPackagesAndExtensions($vendorDir, false), false),
        ]));

        return new Requirements($all, $withoutDev);
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
        // GlobIterator requires forward slashes; vendor-dir uses backslashes on Windows.
        $composerJsonGlobPattern = str_replace('\\', '/', rtrim($vendorDir, '/\\')) . '/*/*/composer.json';

        foreach (new GlobIterator($composerJsonGlobPattern, FilesystemIterator::KEY_AS_FILENAME | FilesystemIterator::SKIP_DOTS) as $node) {
            /** @var SplFileInfo $node */
            $realPath = $node->getRealPath();
            if ($realPath === false || ! is_file($realPath) || ! is_readable($realPath)) {
                continue;
            }

            $composerJson = file_get_contents($realPath);
            assert(is_string($composerJson));

            $json = json_decode($composerJson, true);
            if (! is_array($json)) {
                continue;
            }

            if (array_key_exists('require', $json) && is_array($json['require'])) {
                foreach (array_filter(array_keys($json['require']), is_string(...)) as $package) {
                    yield $package;
                }
            }

            if (! array_key_exists('require-dev', $json) || ! is_array($json['require-dev'])) {
                continue;
            }

            if (! $includeDev) {
                continue;
            }

            foreach (array_filter(array_keys($json['require-dev']), is_string(...)) as $package) {
                yield $package;
            }
        }
    }
}
