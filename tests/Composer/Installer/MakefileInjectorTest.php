<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Makefiles\Composer\Installer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\Makefiles\Composer\Installer\Base64FileInjector;
use WyriHaximus\Makefiles\Composer\Installer\HelpInjector;
use WyriHaximus\Makefiles\Composer\Installer\LowestVersionInjector;
use WyriHaximus\Makefiles\Composer\Installer\RequirementConditionalInjector;
use WyriHaximus\Makefiles\Composer\Installer\Requirements;
use WyriHaximus\Makefiles\Composer\Installer\SupportedFeaturesInjector;
use WyriHaximus\Makefiles\Composer\Installer\SupportedFeaturesResolver;
use WyriHaximus\Makefiles\Composer\SupportedFeatures;
use WyriHaximus\Tests\Makefiles\Composer\Installer\TestUtilities\ProjectSandbox;
use WyriHaximus\TestUtilities\TestCase;

use function array_merge;
use function base64_encode;
use function chmod;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function mkdir;
use function unlink;

final class MakefileInjectorTest extends TestCase
{
    private const string LOWEST_VERSION_TEMPLATE = 'PHP_VERSION=lowest_cleaned_version_in_tree_from_file("composer.json", "config.platform.php")';

    /** @return iterable<string, array{string, list<string>, string}> */
    public static function provideRequirementConditionalCases(): iterable
    {
        yield 'requirement present' => [
            'NEEDS_DOCKER_SOCKET=when_in_requirements(["testcontainers/testcontainers"], TRUE, FALSE)',
            ['testcontainers/testcontainers', 'php'],
            'NEEDS_DOCKER_SOCKET=TRUE',
        ];

        yield 'requirement absent' => [
            'NEEDS_DOCKER_SOCKET=when_in_requirements(["testcontainers/testcontainers"], TRUE, FALSE)',
            ['php'],
            'NEEDS_DOCKER_SOCKET=FALSE',
        ];

        yield 'invalid json list' => [
            'NEEDS_DOCKER_SOCKET=when_in_requirements([broken], TRUE, FALSE)',
            ['php'],
            'NEEDS_DOCKER_SOCKET=FALSE',
        ];
    }

    /** @return iterable<string, array{bool, string, string}> */
    public static function provideLowestVersionCases(): iterable
    {
        yield 'major minor from json tree' => [true, '{"config":{"platform":{"php":"8.4.13"}}}', 'PHP_VERSION="8.4"'];
        yield 'missing file' => [false, '', 'PHP_VERSION="0.0"'];
        yield 'missing tree segment' => [true, '{"config":{}}', 'PHP_VERSION="0.0"'];
        yield 'numeric leaf value' => [true, '{"config":{"platform":{"php":80413}}}', 'PHP_VERSION="80413.0"'];
        yield 'non-scalar leaf' => [true, '{"config":{"platform":{"php":[]}}}', 'PHP_VERSION="0.0"'];
    }

    /** @return iterable<string, array{string, list<string>, list<string>}> */
    public static function provideHelpCases(): iterable
    {
        yield 'builds main migrations and contrib help lists' => [
            <<<'MAKEFILE'
help(main)
help(migrations)
help(contrib)
alpha: ## Alpha task ####
migrations-docs-fix: ## Migration task ####
beta: ## Beta contrib ##*E*##
hidden: ## Hidden ##U##
MAKEFILE,
            ["'alpha: ## Alpha task", 'migrations-docs-fix: ## Migration task', 'beta: ## Beta contrib'],
            ["'hidden: ## Hidden", 'help(main)'],
        ];

        yield 'skips malformed help lines' => [
            "broken-line-without-proper-format\nvalid: ## Valid ####\nhelp(main)\n",
            ["'valid: ## Valid'"],
            ["'broken-line", 'help(main)'],
        ];
    }

    /** @return iterable<string, array{string, bool}> */
    public static function provideBase64Cases(): iterable
    {
        yield 'known placeholder' => ['before base64(LICENSE) after', true];
        yield 'unknown placeholder' => ['no placeholders here', false];
    }

    /** @return iterable<string, array{array<string, mixed>, Requirements, array<string, bool>}> */
    public static function provideSupportedFeaturesResolverCases(): iterable
    {
        yield 'defaults without overrides' => [[], new Requirements([], []), SupportedFeatures::DEFAULTS];
        yield 'ext-parallel' => [
            [],
            new Requirements([], ['ext-parallel']),
            array_merge(SupportedFeatures::DEFAULTS, [
                SupportedFeatures::FEATURE_MACOS => false,
                SupportedFeatures::FEATURE_WINDOWS => false,
                SupportedFeatures::FEATURE_ZTS => true,
            ]),
        ];

        yield 'ext-pcntl' => [
            [],
            new Requirements([], ['ext-pcntl']),
            array_merge(SupportedFeatures::DEFAULTS, [
                SupportedFeatures::FEATURE_MACOS => false,
                SupportedFeatures::FEATURE_WINDOWS => false,
            ]),
        ];

        yield 'ext-parallel and ext-pcntl' => [
            [],
            new Requirements([], ['ext-parallel', 'ext-pcntl']),
            array_merge(SupportedFeatures::DEFAULTS, [
                SupportedFeatures::FEATURE_MACOS => false,
                SupportedFeatures::FEATURE_WINDOWS => false,
                SupportedFeatures::FEATURE_ZTS => true,
            ]),
        ];

        yield 'unknown feature before valid override' => [
            [
                'extra' => [
                    'wyrihaximus' => [
                        'supported-features' => [
                            'unknown-feature' => true,
                            SupportedFeatures::FEATURE_UNIT_TESTS => false,
                        ],
                    ],
                ],
            ],
            new Requirements([], []),
            array_merge(SupportedFeatures::DEFAULTS, [SupportedFeatures::FEATURE_UNIT_TESTS => false]),
        ];

        yield 'non-bool before valid override' => [
            [
                'extra' => [
                    'wyrihaximus' => [
                        'supported-features' => [
                            SupportedFeatures::FEATURE_ZTS => 'not-a-bool',
                            SupportedFeatures::FEATURE_UNIT_TESTS => false,
                        ],
                    ],
                ],
            ],
            new Requirements([], []),
            array_merge(SupportedFeatures::DEFAULTS, [SupportedFeatures::FEATURE_UNIT_TESTS => false]),
        ];

        yield 'extra overrides' => [
            [
                'extra' => [
                    'wyrihaximus' => [
                        'supported-features' => [
                            SupportedFeatures::FEATURE_UNIT_TESTS => false,
                            'unknown-feature' => true,
                            SupportedFeatures::FEATURE_ZTS => 'not-a-bool',
                        ],
                    ],
                ],
            ],
            new Requirements([], []),
            array_merge(SupportedFeatures::DEFAULTS, [SupportedFeatures::FEATURE_UNIT_TESTS => false]),
        ];
    }

    /** @param list<string> $requirements */
    #[Test]
    #[DataProvider('provideRequirementConditionalCases')]
    public function requirementConditionalInject(string $template, array $requirements, string $expected): void
    {
        self::assertSame($expected, RequirementConditionalInjector::inject($template, $requirements));
    }

    #[Test]
    #[DataProvider('provideLowestVersionCases')]
    public function lowestVersionInject(bool $writeComposerJson, string $composerJson, string $expected): void
    {
        $root = $this->getTmpDir() . 'project/';
        mkdir($root);

        if ($writeComposerJson) {
            file_put_contents($root . 'composer.json', $composerJson);
        }

        self::assertSame($expected, LowestVersionInjector::inject(self::LOWEST_VERSION_TEMPLATE, $root));
    }

    /**
     * @param list<string> $contains
     * @param list<string> $notContains
     */
    #[Test]
    #[DataProvider('provideHelpCases')]
    public function helpInject(string $input, array $contains, array $notContains): void
    {
        $result = HelpInjector::inject($input);

        foreach ($contains as $needle) {
            self::assertStringContainsString($needle, $result);
        }

        foreach ($notContains as $needle) {
            self::assertStringNotContainsString($needle, $result);
        }
    }

    #[Test]
    #[DataProvider('provideBase64Cases')]
    public function base64Inject(string $input, bool $usesLicense): void
    {
        if ($usesLicense) {
            $license = file_get_contents(dirname(__DIR__, 3) . '/etc/base64/LICENSE');
            self::assertIsString($license);
            $expected = 'before ' . base64_encode($license) . ' after';
        } else {
            $expected = $input;
        }

        self::assertSame($expected, Base64FileInjector::inject($input));
    }

    #[Test]
    public function base64InjectSkipsUnreadableEntries(): void
    {
        if (! ProjectSandbox::canSimulateUnreadableFiles()) {
            self::markTestSkipped('File permission tests cannot run as root.');
        }

        $base64Dir = dirname(__DIR__, 3) . '/etc/base64/';
        $entryPath = $base64Dir . 'coverage-unreadable-entry';
        file_put_contents($entryPath, 'temporary');
        chmod($entryPath, 0000);

        try {
            $input = 'before base64(coverage-unreadable-entry) after';
            self::assertSame($input, Base64FileInjector::inject($input));
        } finally {
            chmod($entryPath, 0644);
            unlink($entryPath);
        }
    }

    #[Test]
    public function supportedFeaturesInjectExpandsPlaceholders(): void
    {
        $features                                 = SupportedFeatures::DEFAULTS;
        $features[SupportedFeatures::FEATURE_ZTS] = false;
        $result                                   = SupportedFeaturesInjector::inject(
            "supported-features(list)\nsupported-features(raw)",
            $features,
        );

        self::assertStringContainsString('@echo "', $result);
        self::assertStringNotContainsString('supported-features(list)', $result);
        self::assertStringNotContainsString('"zts"', $result);
        self::assertStringContainsString('"unit-tests"', $result);
        self::assertStringNotContainsString('supported-features(raw)', $result);
    }

    /**
     * @param array<string, mixed> $json
     * @param array<string, bool>  $expected
     */
    #[Test]
    #[DataProvider('provideSupportedFeaturesResolverCases')]
    public function supportedFeaturesResolve(array $json, Requirements $requirements, array $expected): void
    {
        self::assertSame($expected, SupportedFeaturesResolver::resolve($json, $requirements));
    }
}
