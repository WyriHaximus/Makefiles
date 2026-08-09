<?php

declare(strict_types=1);

namespace WyriHaximus\Makefiles\Composer\Installer;

use RuntimeException;

use function file_get_contents;
use function file_put_contents;
use function getcwd;
use function is_file;
use function is_string;
use function rtrim;
use function str_contains;
use function strlen;

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
        if (! is_file($templatePath)) {
            return;
        }

        $makefileContents = file_get_contents($templatePath);
        if (! is_string($makefileContents)) {
            return;
        }

        $context->io->write('<info>wyrihaximus/makefiles:</info> Generating Makefile');

        $makefileContents = IncludeLoader::load($context, $makefileContents);
        $makefileContents = ExtraServicesInjector::inject($makefileContents, $context->rootPackagePath);
        $makefileContents = ServiceLifecycleInjector::inject($makefileContents);
        $makefileContents = TaskListInjector::inject($context, $makefileContents);
        $makefileContents = HelpInjector::inject($makefileContents);
        $makefileContents = RequirementConditionalInjector::inject($makefileContents, $context->requirements->all);
        $makefileContents = LowestVersionInjector::inject($makefileContents, $context->rootPackagePath);
        $makefileContents = SupportedFeaturesInjector::inject($makefileContents, $context->supportedFeatures);
        $makefileContents = Base64FileInjector::inject($makefileContents);

        file_put_contents(self::makefilePath($context->rootPackagePath), $makefileContents);

        $context->io->write('<info>wyrihaximus/makefiles:</info> Generating Makefile took less than a second');
    }

    private static function makefilePath(string $rootPackagePath): string
    {
        if ($rootPackagePath === '') {
            throw new RuntimeException('Refusing to write Makefile to an unsafe root package path.');
        }

        if (! self::isAbsolutePath($rootPackagePath)) {
            $rootPackagePath = getcwd() . DIRECTORY_SEPARATOR . $rootPackagePath;
        }

        $separator = str_contains($rootPackagePath, '\\') ? '\\' : DIRECTORY_SEPARATOR;

        return rtrim($rootPackagePath, '/\\') . $separator . 'Makefile';
    }

    private static function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if ($path[0] === DIRECTORY_SEPARATOR) {
            return true;
        }

        return strlen($path) >= 2 && $path[1] === ':';
    }
}
