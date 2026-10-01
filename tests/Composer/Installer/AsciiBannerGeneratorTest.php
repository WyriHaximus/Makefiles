<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Makefiles\Composer\Installer;

use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WyriHaximus\Makefiles\Composer\Installer\AsciiBannerGenerator;
use WyriHaximus\Tests\Makefiles\Composer\Installer\TestUtilities\ProjectSandbox;
use WyriHaximus\Tests\Makefiles\TestCase;

use function chmod;
use function file_put_contents;
use function mkdir;
use function restore_error_handler;
use function set_error_handler;

final class AsciiBannerGeneratorTest extends TestCase
{
    #[Test]
    public function forTextRendersAsciiBanner(): void
    {
        self::assertCount(4, AsciiBannerGenerator::forText('contrib'));
    }

    #[Test]
    public function forTextNormalizesCase(): void
    {
        self::assertSame(
            AsciiBannerGenerator::forText('contrib'),
            AsciiBannerGenerator::forText('Contrib'),
        );
    }

    #[Test]
    public function forPackageRendersComposerPackageName(): void
    {
        $root = $this->getTmpDir() . 'project/';
        mkdir($root);
        file_put_contents($root . 'composer.json', '{"name":"wyrihaximus/makefiles"}');

        $banner = AsciiBannerGenerator::forPackage($root);

        self::assertCount(4, $banner);
    }

    #[Test]
    public function forPackageReturnsEmptyWhenComposerJsonIsMissing(): void
    {
        $root = $this->getTmpDir() . 'missing-composer/';
        mkdir($root);

        self::assertSame([], AsciiBannerGenerator::forPackage($root));
    }

    #[Test]
    public function forPackageReturnsEmptyWhenComposerJsonIsDirectory(): void
    {
        $root = $this->getTmpDir() . 'composer-json-dir/';
        mkdir($root);
        mkdir($root . 'composer.json');

        set_error_handler(static function (int $severity, string $message): never {
            throw new RuntimeException($message, $severity);
        });

        try {
            self::assertSame([], AsciiBannerGenerator::forPackage($root));
        } finally {
            restore_error_handler();
        }
    }

    #[Test]
    public function forPackageReturnsEmptyWhenNameIsMissing(): void
    {
        $root = $this->getTmpDir() . 'missing-name/';
        mkdir($root);
        file_put_contents($root . 'composer.json', '{"description":"no name"}');

        self::assertSame([], AsciiBannerGenerator::forPackage($root));
    }

    #[Test]
    public function forPackageReturnsEmptyWhenNameIsEmpty(): void
    {
        $root = $this->getTmpDir() . 'empty-name/';
        mkdir($root);
        file_put_contents($root . 'composer.json', '{"name":""}');

        self::assertSame([], AsciiBannerGenerator::forPackage($root));
    }

    #[Test]
    public function forPackageReturnsEmptyWhenComposerJsonIsUnreadable(): void
    {
        if (! ProjectSandbox::canSimulateUnreadableFiles()) {
            self::markTestSkipped('File permission tests cannot run on Windows or as root.');
        }

        $root = $this->getTmpDir() . 'unreadable-composer/';
        mkdir($root);
        file_put_contents($root . 'composer.json', '{"name":"vendor/package"}');
        chmod($root . 'composer.json', 0000);

        try {
            set_error_handler(static function (int $severity, string $message): never {
                throw new RuntimeException($message, $severity);
            });

            try {
                self::assertSame([], AsciiBannerGenerator::forPackage($root));
            } finally {
                restore_error_handler();
            }
        } finally {
            chmod($root . 'composer.json', 0644);
        }
    }
}
