<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Makefiles\Composer\Installer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Throwable;
use WyriHaximus\Makefiles\Composer\Installer\RequirementsCollector;
use WyriHaximus\Tests\Makefiles\Composer\Installer\TestUtilities\ComposerFixture;
use WyriHaximus\Tests\Makefiles\Composer\Installer\TestUtilities\ProjectSandbox;
use WyriHaximus\Tests\Makefiles\TestCase;

use function chmod;
use function file_put_contents;
use function glob;
use function is_array;
use function is_file;
use function mkdir;
use function symlink;

final class RequirementsCollectorTest extends TestCase
{
    /** @return iterable<string, array{callable(self): string, list<string>, list<string>, bool, bool}> */
    public static function provideCollectCases(): iterable
    {
        yield 'merges root and vendor requirements' => [
            static function (self $test): string {
                $root      = $test->getTmpDir() . 'req-collector/';
                $vendorDir = $root . 'vendor/';
                mkdir($vendorDir . 'foo/bar', 0755, true);
                file_put_contents($vendorDir . 'foo/bar/composer.json', '{"require":{"vendor/pkg":"^1"},"require-dev":{"vendor/dev-pkg":"^1"}}');

                return $vendorDir;
            },
            ['php', 'root/dev', 'vendor/pkg', 'vendor/dev-pkg'],
            ['php', 'root/dev', 'vendor/pkg'],
            false,
            false,
        ];

        yield 'skips invalid vendor json' => [
            static function (self $test): string {
                $root      = $test->getTmpDir() . 'invalid-json/';
                $vendorDir = $root . 'vendor/';
                mkdir($vendorDir . 'broken/pkg', 0755, true);
                file_put_contents($vendorDir . 'broken/pkg/composer.json', 'not-json');

                return $vendorDir;
            },
            ['php'],
            ['php'],
            false,
            false,
        ];

        yield 'continues past invalid vendor json' => [
            static function (self $test): string {
                $root      = $test->getTmpDir() . 'continue-invalid-json/';
                $vendorDir = $root . 'vendor/';
                mkdir($vendorDir . 'broken/pkg', 0755, true);
                mkdir($vendorDir . 'valid/pkg', 0755, true);
                file_put_contents($vendorDir . 'broken/pkg/composer.json', 'not-json');
                file_put_contents($vendorDir . 'valid/pkg/composer.json', '{"require-dev":{"vendor/dev-pkg":"^1"}}');

                return $vendorDir;
            },
            ['php', 'vendor/dev-pkg'],
            ['php'],
            false,
            false,
        ];

        yield 'continues past unreadable vendor json' => [
            static function (self $test): string {
                $root           = $test->getTmpDir() . 'continue-unreadable-json/';
                $vendorDir      = $root . 'vendor/';
                $unreadablePath = $vendorDir . 'broken/pkg/composer.json';
                mkdir($vendorDir . 'broken/pkg', 0755, true);
                mkdir($vendorDir . 'valid/pkg', 0755, true);
                file_put_contents($unreadablePath, '{"require":{"vendor/skipped":"^1"}}');
                file_put_contents($vendorDir . 'valid/pkg/composer.json', '{"require-dev":{"vendor/dev-pkg":"^1"}}');
                chmod($unreadablePath, 0000);

                return $vendorDir;
            },
            ['php', 'vendor/dev-pkg'],
            ['php'],
            true,
            false,
        ];

        yield 'continues past vendor package without require-dev' => [
            static function (self $test): string {
                $root      = $test->getTmpDir() . 'continue-no-require-dev/';
                $vendorDir = $root . 'vendor/';
                mkdir($vendorDir . 'no-dev/pkg', 0755, true);
                mkdir($vendorDir . 'with-dev/pkg', 0755, true);
                file_put_contents($vendorDir . 'no-dev/pkg/composer.json', '{"require":{"vendor/skipped":"^1"}}');
                file_put_contents($vendorDir . 'with-dev/pkg/composer.json', '{"require-dev":{"vendor/dev-pkg":"^1"}}');

                return $vendorDir;
            },
            ['php', 'vendor/dev-pkg'],
            ['php'],
            false,
            false,
        ];

        yield 'skips unreadable vendor json' => [
            static function (self $test): string {
                $root             = $test->getTmpDir() . 'unreadable/';
                $vendorDir        = $root . 'vendor/';
                $composerJsonPath = $vendorDir . 'foo/bar/composer.json';
                mkdir($vendorDir . 'foo/bar', 0755, true);
                file_put_contents($composerJsonPath, '{"require":{"vendor/pkg":"^1"}}');
                chmod($composerJsonPath, 0000);

                return $vendorDir;
            },
            ['php'],
            ['php'],
            true,
            false,
        ];

        yield 'vendor package without require-dev' => [
            static function (self $test): string {
                $root      = $test->getTmpDir() . 'no-require-dev/';
                $vendorDir = $root . 'vendor/';
                mkdir($vendorDir . 'foo/bar', 0755, true);
                file_put_contents($vendorDir . 'foo/bar/composer.json', '{"require":{"only/pkg":"^1"}}');

                return $vendorDir;
            },
            ['php', 'only/pkg'],
            ['php', 'only/pkg'],
            false,
            false,
        ];

        yield 'skips vendor composer.json directory' => [
            static function (self $test): string {
                $root      = $test->getTmpDir() . 'composer-json-directory/';
                $vendorDir = $root . 'vendor/';
                mkdir($vendorDir . 'foo/bar/composer.json', 0755, true);

                return $vendorDir;
            },
            ['php'],
            ['php'],
            false,
            false,
        ];

        yield 'continues past vendor composer.json directory' => [
            static function (self $test): string {
                $root      = $test->getTmpDir() . 'continue-composer-json-directory/';
                $vendorDir = $root . 'vendor/';
                mkdir($vendorDir . 'aaa/foo/composer.json', 0755, true);
                mkdir($vendorDir . 'zzz/bar', 0755, true);
                file_put_contents($vendorDir . 'zzz/bar/composer.json', '{"require-dev":{"vendor/dev-pkg":"^1"}}');

                return $vendorDir;
            },
            ['php', 'vendor/dev-pkg'],
            ['php'],
            false,
            false,
        ];

        yield 'skips broken vendor json symlink' => [
            static function (self $test): string {
                $root      = $test->getTmpDir() . 'broken-vendor-json/';
                $vendorDir = $root . 'vendor/';
                mkdir($vendorDir . 'foo/bar', 0755, true);
                symlink($root . 'missing-composer.json', $vendorDir . 'foo/bar/composer.json');

                return $vendorDir;
            },
            ['php'],
            ['php'],
            false,
            true,
        ];

        yield 'continues past broken vendor json symlink' => [
            static function (self $test): string {
                $root      = $test->getTmpDir() . 'continue-broken-symlink/';
                $vendorDir = $root . 'vendor/';
                mkdir($vendorDir . 'aaa/bar', 0755, true);
                symlink($root . 'missing-composer.json', $vendorDir . 'aaa/bar/composer.json');
                mkdir($vendorDir . 'zzz/valid', 0755, true);
                file_put_contents($vendorDir . 'zzz/valid/composer.json', '{"require-dev":{"vendor/dev-pkg":"^1"}}');

                return $vendorDir;
            },
            ['php', 'vendor/dev-pkg'],
            ['php'],
            false,
            true,
        ];
    }

    /** @return iterable<string, array{string}> */
    public static function provideCollectThrowsCases(): iterable
    {
        yield 'missing vendor dir' => ['missing-vendor'];
        yield 'empty vendor dir' => [''];
    }

    /**
     * @param callable(self): string $setup
     * @param list<string>           $expectedAll
     * @param list<string>           $expectedWithoutDev
     */
    #[Test]
    #[DataProvider('provideCollectCases')]
    public function collect(callable $setup, array $expectedAll, array $expectedWithoutDev, bool $requiresUnreadablePermissions, bool $requiresSymlinks): void
    {
        if ($requiresUnreadablePermissions && ! ProjectSandbox::canSimulateUnreadableFiles()) {
            self::markTestSkipped('File permission tests cannot run on Windows or as root.');
        }

        if ($requiresSymlinks && ! ProjectSandbox::canCreateSymlinks()) {
            self::markTestSkipped('Symlink tests cannot run when symlink creation is unavailable.');
        }

        $vendorDir = $setup($this);

        try {
            $requirements = RequirementsCollector::collect(
                ComposerFixture::composer($vendorDir, ['php' => true, 'root/dev' => true], []),
            );

            foreach ($expectedAll as $package) {
                self::assertContains($package, $requirements->all);
            }

            foreach ($expectedWithoutDev as $package) {
                self::assertContains($package, $requirements->withoutDev);
            }

            self::assertNotContains('vendor/dev-pkg', $requirements->withoutDev);
        } finally {
            $composerJsonGlob = glob($vendorDir . '*/*/composer.json');

            if (is_array($composerJsonGlob)) {
                foreach ($composerJsonGlob as $composerJson) {
                    if (! is_file($composerJson)) {
                        continue;
                    }

                    chmod($composerJson, 0644);
                }
            }
        }
    }

    #[Test]
    public function collectExcludesRootDevRequiresFromWithoutDev(): void
    {
        $vendorDir = $this->getTmpDir() . 'root-dev-only/vendor/';
        mkdir($vendorDir, 0755, true);

        $requirements = RequirementsCollector::collect(
            ComposerFixture::composer($vendorDir, ['php' => true], ['ext-parallel' => true]),
        );

        self::assertContains('ext-parallel', $requirements->all);
        self::assertNotContains('ext-parallel', $requirements->withoutDev);
    }

    #[Test]
    #[DataProvider('provideCollectThrowsCases')]
    public function collectThrowsForInvalidVendorDir(string $vendorDirSuffix): void
    {
        $this->expectException(Throwable::class);
        $this->expectExceptionMessageIsOrContains('vendor-dir must be a string');

        $vendorDir = $vendorDirSuffix === '' ? '' : $this->getTmpDir() . $vendorDirSuffix;
        RequirementsCollector::collect(ComposerFixture::composer($vendorDir));
    }
}
