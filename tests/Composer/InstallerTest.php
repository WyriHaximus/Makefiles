<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Makefiles\Composer;

use Composer\Composer;
use Composer\Config;
use Composer\Factory;
use Composer\Package\RootPackage;
use Composer\Package\RootPackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Repository\RepositoryManager;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use RuntimeException;
use WyriHaximus\Makefiles\Composer\Installer;
use WyriHaximus\Tests\Makefiles\Composer\Installer\TestUtilities\CapturingNullIO;
use WyriHaximus\Tests\Makefiles\Composer\Installer\TestUtilities\ComposerFixture;
use WyriHaximus\Tests\Makefiles\Composer\Installer\TestUtilities\ProjectSandbox;
use WyriHaximus\Tests\Makefiles\TestCase;

use function chmod;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function mkdir;
use function restore_error_handler;
use function set_error_handler;
use function unlink;

use const DIRECTORY_SEPARATOR;
use const PHP_INT_MIN;

final class InstallerTest extends TestCase
{
    #[Test]
    public function rootPackagePathFromVendorDirAppendsDirectorySeparator(): void
    {
        $method = new ReflectionMethod(Installer::class, 'rootPackagePathFromVendorDir');
        self::assertSame('/tmp/project' . DIRECTORY_SEPARATOR, $method->invoke(null, '/tmp/project/vendor'));
    }

    #[Test]
    public function composerJsonPathForRootPackageJoinsComposerJsonFileName(): void
    {
        $method = new ReflectionMethod(Installer::class, 'composerJsonPathForRootPackage');
        self::assertSame('/tmp/project/composer.json', $method->invoke(null, '/tmp/project/'));
    }

    #[Test]
    public function getSubscribedEvents(): void
    {
        self::assertSame(
            [ScriptEvents::PRE_AUTOLOAD_DUMP => ['findEventListeners', PHP_INT_MIN]],
            Installer::getSubscribedEvents(),
        );
        self::assertSame(
            PHP_INT_MIN,
            Installer::getSubscribedEvents()[ScriptEvents::PRE_AUTOLOAD_DUMP][1],
        );
    }

    /** @return iterable<string, array{string, bool, string, bool}> */
    public static function provideEarlyReturnCases(): iterable
    {
        yield 'without composer json' => ['no-composer', false, '', false];
        yield 'invalid composer json' => ['invalid-json', true, 'not-json', false];
        yield 'without makefiles dependency' => ['no-makefiles', true, '{"name":"example/no-makefiles","require-dev":{"php":"^8.4"}}', false];
        yield 'unreadable composer json' => [
            'unreadable-json',
            true,
            '{"name":"example/unreadable","require-dev":{"wyrihaximus/makefiles":"dev-main"}}',
            true,
        ];

        yield 'composer json is a directory' => ['composer-dir', true, '', false];
    }

    #[Test]
    public function findEventListenersReturnsEarlyWhenComposerJsonIsDirectory(): void
    {
        $root         = $this->getTmpDir() . 'composer-json-directory/';
        $vendorDir    = $root . 'vendor/';
        $makeFilePath = $root . 'Makefile';
        mkdir($vendorDir, 0755, true);
        mkdir($vendorDir . 'wyrihaximus/makefiles', 0755, true);
        ProjectSandbox::mirrorPackage(
            ProjectSandbox::packageSourceRoot() . DIRECTORY_SEPARATOR,
            $vendorDir . 'wyrihaximus/makefiles/',
        );
        mkdir($root . 'composer.json', 0755, true);

        $io = ProjectSandbox::capturingIo();
        $this->assertFindEventListenersDoesNotTouchComposerJsonDirectory(
            function () use ($vendorDir, $io): void {
                Installer::findEventListeners($this->eventWithIo($vendorDir, $io));
            },
        );

        self::assertFileDoesNotExist($makeFilePath);
        self::assertSame('', $io->output());
    }

    #[Test]
    #[DataProvider('provideEarlyReturnCases')]
    public function findEventListenersReturnsEarly(string $suffix, bool $writeComposerJson, string $composerJson, bool $restorePermissions): void
    {
        if ($restorePermissions && ! ProjectSandbox::canSimulateUnreadableFiles()) {
            self::markTestSkipped('File permission tests cannot run as root.');
        }

        ['vendorDir' => $vendorDir, 'makeFilePath' => $makeFilePath] = $this->seedProject($suffix, $writeComposerJson, $composerJson);
        $io                                                          = ProjectSandbox::capturingIo();

        try {
            if ($suffix === 'composer-dir') {
                $this->assertFindEventListenersDoesNotTouchComposerJsonDirectory(
                    function () use ($vendorDir, $io): void {
                        Installer::findEventListeners($this->eventWithIo($vendorDir, $io));
                    },
                );
            } else {
                Installer::findEventListeners($this->eventWithIo($vendorDir, $io));
            }

            self::assertFileDoesNotExist($makeFilePath);
            self::assertSame('', $io->output());
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
        $makefile = file_get_contents($root . 'Makefile');
        self::assertIsString($makefile);
        self::assertStringContainsString('documentation-markdownlint:', $makefile);
        self::assertStringContainsString('documentation-links:', $makefile);
        self::assertStringContainsString('documentation-typos:', $makefile);
        self::assertStringContainsString('documentation-vale:', $makefile);
        self::assertStringContainsString('documentation-qa:', $makefile);
        self::assertStringContainsString('DOCKER_RUN_DOCUMENTATION=docker run', $makefile);
        self::assertStringContainsString('@echo "[\"unit-testing-raw\"]" ## Count: 1', $makefile);
        self::assertStringContainsString(
            '@echo "[\"composer-validate\",\"cs\",\"stan\",\"mutation-testing\",\"composer-require-checker\",\"composer-unused\",\"backward-compatibility-check\",\"documentation-markdownlint\",\"documentation-links\",\"documentation-typos\",\"documentation-vale\"]" ## Count: 11',
            $makefile,
        );
        self::assertStringContainsString(
            '$(MAKE) cs-fix cs unit-testing composer-require-checker composer-unused documentation-qa ## Count: 6',
            $makefile,
        );
    }

    #[Test]
    public function findEventListenersGeneratesMakefileForConsumerWithOnlyMakefilesInRequireDev(): void
    {
        $root      = $this->getTmpDir() . 'consumer-only-makefiles/';
        $vendorDir = $root . 'vendor/';
        mkdir($vendorDir . 'wyrihaximus/makefiles', 0755, true);
        ProjectSandbox::mirrorPackage(ProjectSandbox::packageSourceRoot() . DIRECTORY_SEPARATOR, $vendorDir . 'wyrihaximus/makefiles/');
        file_put_contents(
            $root . 'composer.json',
            '{"name":"example/consumer-only-makefiles","require-dev":{"wyrihaximus/makefiles":"dev-main"}}',
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
        $expectedIoOutput         = $io->output();
        $expectedMakeFileContents = file_get_contents($makefilePath);
        self::assertIsString($expectedMakeFileContents);
        unlink($makefilePath);
        self::assertFileDoesNotExist($makefilePath);

        $io    = ProjectSandbox::capturingIo();
        $event = new Event(ScriptEvents::PRE_AUTOLOAD_DUMP, $composer, $io);
        Installer::findEventListeners($event);

        self::assertSame($expectedIoOutput, $io->output());

        self::assertFileExists($makefilePath);
        self::assertSame($expectedMakeFileContents, file_get_contents($makefilePath));
    }

    /** @return array{vendorDir: string, makeFilePath: string} */
    private function seedProject(string $suffix, bool $writeComposerJson, string $composerJson): array
    {
        $root      = $this->getTmpDir() . $suffix . '/';
        $vendorDir = $root . 'vendor/';
        mkdir($vendorDir, 0755, true);

        if ($suffix === 'unreadable-json') {
            mkdir($vendorDir . 'wyrihaximus/makefiles', 0755, true);
            ProjectSandbox::mirrorPackage(
                ProjectSandbox::packageSourceRoot() . DIRECTORY_SEPARATOR,
                $vendorDir . 'wyrihaximus/makefiles/',
            );
        }

        if ($writeComposerJson) {
            if ($suffix === 'composer-dir') {
                mkdir($root . 'composer.json', 0755, true);
            } else {
                file_put_contents($root . 'composer.json', $composerJson);
                if ($suffix === 'unreadable-json') {
                    chmod($root . 'composer.json', 0000);
                }
            }
        }

        return [
            'vendorDir' => $vendorDir,
            'makeFilePath' => ($suffix === 'no-composer' ? dirname($vendorDir) : $root) . DIRECTORY_SEPARATOR . 'Makefile',
        ];
    }

    /** @param callable(): void $invoke */
    private function assertFindEventListenersDoesNotTouchComposerJsonDirectory(callable $invoke): void
    {
        set_error_handler(static function (int $severity, string $message): never {
            throw new RuntimeException($message, $severity);
        });

        try {
            $invoke();
        } finally {
            restore_error_handler();
        }
    }

    private function eventWithIo(string $vendorDir, CapturingNullIO $io): Event
    {
        $composerConfig = new Config();
        $composerConfig->merge(['config' => ['vendor-dir' => $vendorDir]]);
        $repository = Mockery::mock(InstalledRepositoryInterface::class);
        $repository->allows()->getCanonicalPackages()->andReturn([]);
        $repositoryManager = new RepositoryManager($io, $composerConfig, Factory::createHttpDownloader($io, $composerConfig));
        $repositoryManager->setLocalRepository($repository);
        $composer = new Composer();
        $composer->setConfig($composerConfig);
        $composer->setRepositoryManager($repositoryManager);
        $package = Mockery::mock(RootPackageInterface::class);
        $package->allows()->getRequires()->andReturn([]);
        $package->allows()->getDevRequires()->andReturn([]);
        $composer->setPackage($package);

        return new Event(ScriptEvents::PRE_AUTOLOAD_DUMP, $composer, $io);
    }
}
