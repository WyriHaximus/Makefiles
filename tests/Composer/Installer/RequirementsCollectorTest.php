<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Makefiles\Composer\Installer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Throwable;
use WyriHaximus\Makefiles\Composer\Installer\RequirementsCollector;
use WyriHaximus\Tests\Makefiles\Composer\Installer\TestUtilities\ComposerFixture;
use WyriHaximus\Tests\Makefiles\Composer\Installer\TestUtilities\ProjectSandbox;
use WyriHaximus\TestUtilities\TestCase;

use function chmod;
use function file_exists;
use function file_put_contents;
use function mkdir;

final class RequirementsCollectorTest extends TestCase
{
    /** @return iterable<string, array{callable(self): string, list<string>, list<string>, bool}> */
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
    public function collect(callable $setup, array $expectedAll, array $expectedWithoutDev, bool $requiresUnreadablePermissions): void
    {
        if ($requiresUnreadablePermissions && ! ProjectSandbox::canSimulateUnreadableFiles()) {
            self::markTestSkipped('File permission tests cannot run as root.');
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
            $unreadable = $vendorDir . 'foo/bar/composer.json';
            if (file_exists($unreadable)) {
                chmod($unreadable, 0644);
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
