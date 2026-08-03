<?php

declare(strict_types=1);

namespace WyriHaximus\Makefiles\Composer\Installer;

use Composer\IO\IOInterface;
use RuntimeException;
use SplFileInfo;

use function file_exists;
use function file_get_contents;
use function is_readable;
use function preg_last_error_msg;
use function preg_replace_callback;
use function str_starts_with;

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
        if ($makefileIncludePath === false || ! file_exists($makefilesPackageRoot)) {
            return '';
        }

        $rootRealPath = new SplFileInfo($makefilesPackageRoot)->getRealPath();
        if ($rootRealPath === false || ! str_starts_with($makefileIncludePath, $rootRealPath)) {
            return '';
        }

        if (! is_readable($makefileIncludePath)) {
            return '';
        }

        $makefileContents = file_get_contents($makefileIncludePath);
        if ($makefileContents === false) {
            return '';
        }

        $io->write('<info>wyrihaximus/makefiles:</info> Including: ' . $filename);

        return $makefileContents;
    }
}
