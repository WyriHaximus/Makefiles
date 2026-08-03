<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Makefiles\Composer\Installer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\Makefiles\Composer\Installer\IncludeLoader;
use WyriHaximus\Tests\Makefiles\Composer\Installer\TestUtilities\CapturingNullIO;
use WyriHaximus\Tests\Makefiles\Composer\Installer\TestUtilities\ProjectSandbox;
use WyriHaximus\TestUtilities\TestCase;

use function chmod;
use function file_put_contents;
use function mkdir;
use function str_contains;
use function symlink;

final class IncludeLoaderTest extends TestCase
{
    #[Test]
    public function loadInlinesReferenceIncludesAndExtraMakefile(): void
    {
        $root      = $this->getTmpDir();
        $reference = $root . 'reference/';
        mkdir($reference . 'includes', 0755, true);
        mkdir($root . 'etc', 0755, true);
        file_put_contents($reference . 'includes/All.mk', "all-target:\n");
        file_put_contents($root . 'etc/Makefile', "extra-target:\n");

        $context = ProjectSandbox::context($root, $reference);
        $result  = IncludeLoader::load($context, "include includes/All.mk\ninclude includes/EXTRA.mk\n");

        self::assertStringContainsString('all-target:', $result);
        self::assertStringContainsString('extra-target:', $result);
        self::assertStringNotContainsString('include includes/', $result);
        self::assertInstanceOf(CapturingNullIO::class, $context->io);
        self::assertTrue(str_contains($context->io->output(), 'Including: All.mk'));
        self::assertTrue(str_contains($context->io->output(), 'Including: etc/Makefile'));
    }

    /** @return iterable<string, array{string, string}> */
    public static function provideLoadReturnsEmptyStringCases(): iterable
    {
        yield 'unreadable include' => ['unreadable', "include includes/Unreadable.mk\n"];
        yield 'include outside package root' => ['outside', "include includes/Escape.mk\n"];
        yield 'missing include' => ['missing', "include includes/Missing.mk\n"];
    }

    #[Test]
    #[DataProvider('provideLoadReturnsEmptyStringCases')]
    public function loadReturnsEmptyString(string $setupType, string $input): void
    {
        if ($setupType === 'unreadable' && ! ProjectSandbox::canSimulateUnreadableFiles()) {
            self::markTestSkipped('File permission tests cannot run as root.');
        }

        $root      = $this->getTmpDir();
        $reference = $root . 'reference/';
        mkdir($reference . 'includes', 0755, true);

        if ($setupType === 'unreadable') {
            $includePath = $reference . 'includes/Unreadable.mk';
            file_put_contents($includePath, "unreadable-target:\n");
            chmod($includePath, 0000);
        }

        if ($setupType === 'outside') {
            $outside = $root . 'outside/';
            mkdir($outside, 0755, true);
            file_put_contents($outside . 'Escape.mk', "escape-target:\n");
            symlink($outside . 'Escape.mk', $reference . 'includes/Escape.mk');
        }

        try {
            self::assertSame("\n", IncludeLoader::load(ProjectSandbox::context($root, $reference), $input));
        } finally {
            if ($setupType === 'unreadable') {
                chmod($reference . 'includes/Unreadable.mk', 0644);
            }
        }
    }
}
