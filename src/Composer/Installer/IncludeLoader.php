<?php

declare(strict_types=1);

namespace WyriHaximus\Makefiles\Composer\Installer;

use Composer\IO\IOInterface;
use RuntimeException;
use SplFileInfo;

use function assert;
use function file_exists;
use function file_get_contents;
use function is_file;
use function is_readable;
use function is_string;
use function preg_last_error_msg;
use function preg_replace_callback;
use function rtrim;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;

use const DIRECTORY_SEPARATOR;

final class IncludeLoader
{
    private function __construct()
    {
    }

    public static function load(MakefileGenerationContext $context, string $makefileContents): string
    {
        $makefileContents = preg_replace_callback(
            '/include includes\/([a-zA-Z.]+)/',
            static fn (array $matches): string => $matches[1] === 'EXTRA.mk' ? self::loadInclude(
                $context->io,
                $context->rootPackagePath,
                'etc/Makefile',
            ) : self::loadInclude(
                $context->io,
                $context->referenceRoot . 'includes' . DIRECTORY_SEPARATOR,
                $matches[1],
            ),
            $makefileContents,
        );

        if ($makefileContents === null) {
            throw new RuntimeException('Failed load in includes: ' . preg_last_error_msg());
        }

        return $makefileContents;
    }

    private static function loadInclude(IOInterface $io, string $makefilesPackageRoot, string $filename): string
    {
        $candidatePath = $makefilesPackageRoot . $filename;
        if (! file_exists($candidatePath)) {
            return '';
        }

        $makefileIncludePath = new SplFileInfo($candidatePath)->getRealPath();
        if ($makefileIncludePath === false || ! is_file($makefileIncludePath) || ! is_readable($makefileIncludePath)) {
            // @infection-ignore-all
            return '';
        }

        $rootRealPath = new SplFileInfo($makefilesPackageRoot)->getRealPath();
        if ($rootRealPath === false || ! self::isRealPathInsideRoot($makefileIncludePath, $rootRealPath)) {
            return '';
        }

        $makefileContents = file_get_contents($makefileIncludePath);
        assert(is_string($makefileContents));

        $io->write('<info>wyrihaximus/makefiles:</info> Including: ' . $filename);

        return $makefileContents;
    }

    private static function isRealPathInsideRoot(string $fileRealPath, string $rootRealPath): bool
    {
        $normalizedFilePath = self::normalizePathForComparison($fileRealPath);
        $normalizedRootPath = rtrim(self::normalizePathForComparison($rootRealPath), '/') . '/';

        if (
            (strlen($normalizedFilePath) >= 2 && $normalizedFilePath[1] === ':')
            || (strlen($normalizedRootPath) >= 2 && $normalizedRootPath[1] === ':')
        ) {
            $normalizedFilePath = strtolower($normalizedFilePath);
            $normalizedRootPath = strtolower($normalizedRootPath);
        }

        return str_starts_with($normalizedFilePath, $normalizedRootPath);
    }

    private static function normalizePathForComparison(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        if (str_starts_with($path, '//?/')) {
            return substr($path, 4);
        }

        return $path;
    }
}
