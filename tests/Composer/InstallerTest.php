<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Makefiles\Composer;

use Composer\Composer;
use Composer\Config;
use Composer\Factory;
use Composer\Package\RootPackage;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Repository\RepositoryManager;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\Makefiles\Composer\Installer;
use WyriHaximus\Tests\Makefiles\Composer\Installer\TestUtilities\ComposerFixture;
use WyriHaximus\Tests\Makefiles\Composer\Installer\TestUtilities\ProjectSandbox;
use WyriHaximus\TestUtilities\TestCase;

use function chmod;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function mkdir;
use function unlink;

use const DIRECTORY_SEPARATOR;
use const PHP_INT_MIN;

final class InstallerTest extends TestCase
{
    /** @return list<string> */
    private function generateOutputFragments(): array
    {
        return [
            '<info>wyrihaximus/makefiles:</info> Supported features Matrix:',
            '<info>wyrihaximus/makefiles:</info> composer-plugin: ✅',
            '<info>wyrihaximus/makefiles:</info> unit-tests: ✅',
            '<info>wyrihaximus/makefiles:</info> zts: ❌',
            '<info>wyrihaximus/makefiles:</info> Generating Makefile',
            '<info>wyrihaximus/makefiles:</info> Including: All.mk',
            '<info>wyrihaximus/makefiles:</info> Including: PHP.mk',
            '<info>wyrihaximus/makefiles:</info> Including: ContainerAccess.mk',
            '<info>wyrihaximus/makefiles:</info> Including: Help.mk',
            '<info>wyrihaximus/makefiles:</info> Including: TaskFinders.mk',
            '<info>wyrihaximus/makefiles:</info> Generating Makefile took less than a second',
        ];
    }

    #[Test]
    public function getSubscribedEvents(): void
    {
        self::assertSame(
            [ScriptEvents::PRE_AUTOLOAD_DUMP => ['findEventListeners', PHP_INT_MIN]],
            Installer::getSubscribedEvents(),
        );
    }

    /** @return iterable<string, array{string, bool, string, bool}> */
    public static function provideEarlyReturnCases(): iterable
    {
        yield 'without composer json' => ['no-composer', false, '', false];
        yield 'invalid composer json' => ['invalid-json', true, 'not-json', false];
        yield 'without makefiles dependency' => ['no-makefiles', true, '{"name":"example/no-makefiles","require-dev":{"php":"^8.4"}}', false];
        yield 'unreadable composer json' => ['unreadable-json', true, '{}', true];
    }

    #[Test]
    #[DataProvider('provideEarlyReturnCases')]
    public function findEventListenersReturnsEarly(string $suffix, bool $writeComposerJson, string $composerJson, bool $restorePermissions): void
    {
        if ($restorePermissions && ! ProjectSandbox::canSimulateUnreadableFiles()) {
            self::markTestSkipped('File permission tests cannot run as root.');
        }

        ['vendorDir' => $vendorDir, 'makeFilePath' => $makeFilePath] = $this->seedProject($suffix, $writeComposerJson, $composerJson);

        try {
            Installer::findEventListeners(ComposerFixture::event($vendorDir));
            self::assertFileDoesNotExist($makeFilePath);
        } finally {
            if ($restorePermissions) {
                chmod(dirname($makeFilePath) . '/composer.json', 0644);
            }
        }
    }

    #[Test]
    public function findEventListenersGeneratesMakefileForConsumerProject(): void
    {
        $root      = $this->getTmpDir() . 'consumer/';
        $vendorDir = $root . 'vendor/';
        mkdir($vendorDir . 'wyrihaximus/makefiles', 0755, true);
        ProjectSandbox::mirrorPackage(ProjectSandbox::packageSourceRoot() . DIRECTORY_SEPARATOR, $vendorDir . 'wyrihaximus/makefiles/');
        file_put_contents(
            $root . 'composer.json',
            '{"name":"example/consumer","require-dev":{"wyrihaximus/makefiles":"dev-main","php":"^8.4"}}',
        );

        Installer::findEventListeners(ComposerFixture::event($vendorDir));

        self::assertFileExists($root . 'Makefile');
    }

    #[Test]
    public function generate(): void
    {
        $projectRoot = ProjectSandbox::mirroredProject($this->getTmpDir());
        $vendorDir   = $projectRoot . 'vendor';
        mkdir($vendorDir);

        $io             = ProjectSandbox::capturingIo();
        $composerConfig = new Config();
        $composerConfig->merge(['config' => ['vendor-dir' => $vendorDir]]);
        $rootPackage = new RootPackage('wyrihaximus/makefiles', 'dev-main', 'dev-main');
        $rootPackage->setAutoload([
            'classmap' => ['dummy/event', 'dummy/listener/Listener.php'],
            'psr-4' => ['WyriHaximus\\Makefiles\\' => 'src'],
        ]);
        $repository = Mockery::mock(InstalledRepositoryInterface::class);
        $repository->allows()->getCanonicalPackages()->andReturn([]);
        $repositoryManager = new RepositoryManager($io, $composerConfig, Factory::createHttpDownloader($io, $composerConfig));
        $repositoryManager->setLocalRepository($repository);
        $composer = new Composer();
        $composer->setConfig($composerConfig);
        $composer->setRepositoryManager($repositoryManager);
        $composer->setPackage($rootPackage);
        $event = new Event(ScriptEvents::PRE_AUTOLOAD_DUMP, $composer, $io);

        $installer = new Installer();
        $installer->activate($composer, $io);
        $installer->deactivate($composer, $io);
        $installer->uninstall($composer, $io);

        $makefilePath = $projectRoot . 'Makefile';
        Installer::findEventListeners($event);
        $expectedMakeFileContents = file_get_contents($makefilePath);
        self::assertIsString($expectedMakeFileContents);
        unlink($makefilePath);
        self::assertFileDoesNotExist($makefilePath);

        $io    = ProjectSandbox::capturingIo();
        $event = new Event(ScriptEvents::PRE_AUTOLOAD_DUMP, $composer, $io);
        Installer::findEventListeners($event);

        foreach ($this->generateOutputFragments() as $fragment) {
            self::assertStringContainsString($fragment, $io->output());
        }

        self::assertFileExists($makefilePath);
        self::assertSame($expectedMakeFileContents, file_get_contents($makefilePath));
    }

    /** @return array{vendorDir: string, makeFilePath: string} */
    private function seedProject(string $suffix, bool $writeComposerJson, string $composerJson): array
    {
        $root      = $this->getTmpDir() . $suffix . '/';
        $vendorDir = $root . 'vendor/';
        mkdir($vendorDir, 0755, true);

        if ($writeComposerJson) {
            file_put_contents($root . 'composer.json', $composerJson);
            if ($composerJson === '{}') {
                chmod($root . 'composer.json', 0000);
            }
        }

        return [
            'vendorDir' => $vendorDir,
            'makeFilePath' => ($suffix === 'no-composer' ? dirname($vendorDir) : $root) . DIRECTORY_SEPARATOR . 'Makefile',
        ];
    }
}
