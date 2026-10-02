<?php

declare(strict_types=1);

namespace WyriHaximus\Makefiles\Composer\Installer;

use Composer\IO\IOInterface;
use RuntimeException;

use function assert;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function is_file;
use function is_readable;
use function is_string;
use function rename;
use function rtrim;
use function str_contains;
use function strlen;
use function uniqid;

use const DIRECTORY_SEPARATOR;

final class MakefileGenerator
{
    private function __construct()
    {
    }

    public static function generate(MakefileGenerationContext $context): void
    {
        $context->io->write('<info>wyrihaximus/makefiles:</info> Supported features Matrix:');
        foreach ($context->supportedFeatures as $name => $supported) {
            $context->io->write('<info>wyrihaximus/makefiles:</info> ' . $name . ': ' . ($supported ? '✅' : '❌'));
        }

        $templatePath = $context->referenceRoot . 'templates' . DIRECTORY_SEPARATOR . 'Makefile.PHP';
        if (! is_file($templatePath) || ! is_readable($templatePath)) {
            return;
        }

        $makefileContents = file_get_contents($templatePath);
        assert(is_string($makefileContents));

        self::announceMakefileGeneration($context->io);

        $makefileContents = IncludeLoader::load($context, $makefileContents);
        $makefileContents = ExtraServicesInjector::inject($makefileContents, $context->rootPackagePath);
        $makefileContents = ServiceLifecycleInjector::inject($makefileContents);
        $makefileContents = TaskListInjector::inject($context, $makefileContents);
        $makefileContents = HelpInjector::inject($makefileContents, $context->rootPackagePath);
        $makefileContents = RequirementConditionalInjector::inject($makefileContents, $context->requirements->all);
        $makefileContents = LowestVersionInjector::inject($makefileContents, $context->rootPackagePath);
        $makefileContents = SupportedFeaturesInjector::inject($makefileContents, $context->supportedFeatures);
        $makefileContents = SupportedFeatureConditionalInjector::inject($makefileContents, $context->supportedFeatures);
        $makefileContents = Base64FileInjector::inject($makefileContents);

        self::writeMakefile(self::makefilePath($context->rootPackagePath), $makefileContents);

        $context->io->write('<info>wyrihaximus/makefiles:</info> Generating Makefile took less than a second');
    }

    private static function writeMakefile(string $path, string $contents): void
    {
        $temporaryPath = self::temporaryMakefilePath($path);
        $written       = file_put_contents($temporaryPath, $contents);
        assert($written === strlen($contents));

        assert(rename($temporaryPath, $path));
    }

    private static function temporaryMakefilePath(string $makefilePath): string
    {
        $directory = dirname($makefilePath);

        return $directory . DIRECTORY_SEPARATOR . '.Makefile.' . uniqid('', true) . '.tmp';
    }

    private static function makefilePath(string $rootPackagePath): string
    {
        if ($rootPackagePath === '' || ! self::isAbsolutePath($rootPackagePath)) {
            throw new RuntimeException('Refusing to write Makefile to an unsafe root package path.');
        }

        $separator = '/';
        if (str_contains($rootPackagePath, '\\')) {
            $separator = '\\';
        } elseif (self::pathUsesWindowsDriveLetter($rootPackagePath)) {
            $separator = '\\';
        }

        return rtrim($rootPackagePath, '/\\') . $separator . 'Makefile';
    }

    private static function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if ($path[0] === '/' || $path[0] === '\\') {
            return true;
        }

        return self::pathUsesWindowsDriveLetter($path);
    }

    private static function pathUsesWindowsDriveLetter(string $path): bool
    {
        return strlen($path) >= 2 && $path[1] === ':';
    }

    private static function announceMakefileGeneration(IOInterface $io): void
    {
        $io->write('<info>wyrihaximus/makefiles:</info> Generating Makefile');
    }
}
