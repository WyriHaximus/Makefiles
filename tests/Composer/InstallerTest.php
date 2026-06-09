<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Makefiles\Composer;

use Composer\Composer;
use Composer\Config;
use Composer\Factory;
use Composer\IO\NullIO;
use Composer\Package\RootPackage;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Repository\RepositoryManager;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\StreamOutput;
use WyriHaximus\Makefiles\Composer\Installer;
use WyriHaximus\TestUtilities\TestCase;

use function closedir;
use function copy;
use function dirname;
use function file_exists;
use function fseek;
use function is_dir;
use function is_file;
use function mkdir;
use function readdir;
use function Safe\file_get_contents;
use function Safe\fopen;
use function Safe\opendir;
use function Safe\stream_get_contents;
use function Safe\unlink;

use const DIRECTORY_SEPARATOR;

final class InstallerTest extends TestCase
{
    #[Test]
    public function generate(): void
    {
        $vendorDir = $this->getTmpDir() . 'vendor' . DIRECTORY_SEPARATOR;
        mkdir($vendorDir);
        $this->recurseCopy(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR, $this->getTmpDir());
        $composerConfig = new Config();
        $composerConfig->merge([
            'config' => ['vendor-dir' => $vendorDir],
        ]);
        $rootPackage = new RootPackage('wyrihaximus/makefiles', 'dev-main', 'dev-main');
        $rootPackage->setAutoload([
            'classmap' => ['dummy/event','dummy/listener/Listener.php'],
            'psr-4' => ['WyriHaximus\\Makefiles\\' => 'src'],
        ]);

        $io         = new class () extends NullIO {
            private readonly StreamOutput $output;

            public function __construct()
            {
                $this->output = new StreamOutput(fopen('php://memory', 'rw'), decorated: false);
            }

            public function output(): string
            {
                fseek($this->output->getStream(), 0);

                return stream_get_contents($this->output->getStream());
            }

            /** @inheritDoc */
            public function write($messages, bool $newline = true, int $verbosity = self::NORMAL): void
            {
                $this->output->write($messages, $newline, $verbosity & StreamOutput::OUTPUT_RAW);
            }
        };
        $repository = Mockery::mock(InstalledRepositoryInterface::class);
        $repository->allows()->getCanonicalPackages()->andReturn([]);
        $repositoryManager = new RepositoryManager($io, $composerConfig, Factory::createHttpDownloader($io, $composerConfig));
        $repositoryManager->setLocalRepository($repository);
        $composer = new Composer();
        $composer->setConfig($composerConfig);
        $composer->setRepositoryManager($repositoryManager);
        $composer->setPackage($rootPackage);
        $event = new Event(
            ScriptEvents::PRE_AUTOLOAD_DUMP,
            $composer,
            $io,
        );

        $installer = new Installer();

        // Test dead methods and make Infection happy
        $installer->activate($composer, $io);
        $installer->deactivate($composer, $io);
        $installer->uninstall($composer, $io);

        $makefilePath             = $this->getTmpDir() . 'Makefile';
        $expectedMakeFileContents = file_get_contents($makefilePath);
        unlink($makefilePath);

        self::assertFileDoesNotExist($makefilePath);

        // Do the actual generating
        Installer::findEventListeners($event);

        $output = $io->output();

        self::assertStringContainsString('<info>wyrihaximus/makefiles:</info> Generating Makefile', $output);
        self::assertStringContainsString('<info>wyrihaximus/makefiles:</info> Including: All.mk', $output);
        self::assertStringContainsString('<info>wyrihaximus/makefiles:</info> Including: PHP.mk', $output);
        self::assertStringContainsString('<info>wyrihaximus/makefiles:</info> Including: Shell.mk', $output);
        self::assertStringContainsString('<info>wyrihaximus/makefiles:</info> Including: Help.mk', $output);
        self::assertStringContainsString('<info>wyrihaximus/makefiles:</info> Including: TaskFinders.mk', $output);
        self::assertStringContainsString('<info>wyrihaximus/makefiles:</info> Generating Makefile took less than a second', $output);

        self::assertFileExists($makefilePath);
        self::assertSame(file_get_contents($makefilePath), $expectedMakeFileContents);
    }

    private function recurseCopy(string $src, string $dst): void
    {
        $dir = opendir($src);
        if (! file_exists($dst)) {
            mkdir($dst);
        }

        while (( $file = readdir($dir)) !== false) {
            if (( $file === '.' ) || ( $file === '..' )) {
                continue;
            }

            if (is_dir($src . '/' . $file)) {
                $this->recurseCopy($src . '/' . $file, $dst . '/' . $file);
            } elseif (is_file($src . '/' . $file)) {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }

        closedir($dir);
    }
}
