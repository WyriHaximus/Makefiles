<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Makefiles\Composer\Installer\TestUtilities;

use Composer\Composer;
use Composer\Config;
use Composer\Factory;
use Composer\IO\NullIO;
use Composer\Package\RootPackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Repository\RepositoryManager;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Mockery;

final class ComposerFixture
{
    private function __construct()
    {
    }

    /**
     * @param array<string, true> $requires
     * @param array<string, true> $devRequires
     */
    public static function composer(string $vendorDir, array $requires = [], array $devRequires = []): Composer
    {
        $config = new Config();
        $config->merge(['config' => ['vendor-dir' => $vendorDir]]);
        $package = Mockery::mock(RootPackageInterface::class);
        $package->allows()->getRequires()->andReturn($requires);
        $package->allows()->getDevRequires()->andReturn($devRequires);
        $composer = new Composer();
        $composer->setConfig($config);
        $composer->setPackage($package);

        return $composer;
    }

    /**
     * @param array<string, true> $requires
     * @param array<string, true> $devRequires
     */
    public static function event(string $vendorDir, array $requires = [], array $devRequires = []): Event
    {
        $composerConfig = new Config();
        $composerConfig->merge(['config' => ['vendor-dir' => $vendorDir]]);
        $io         = new NullIO();
        $repository = Mockery::mock(InstalledRepositoryInterface::class);
        $repository->allows()->getCanonicalPackages()->andReturn([]);
        $repositoryManager = new RepositoryManager($io, $composerConfig, Factory::createHttpDownloader($io, $composerConfig));
        $repositoryManager->setLocalRepository($repository);
        $composer = new Composer();
        $composer->setConfig($composerConfig);
        $composer->setRepositoryManager($repositoryManager);
        $package = Mockery::mock(RootPackageInterface::class);
        $package->allows()->getRequires()->andReturn($requires);
        $package->allows()->getDevRequires()->andReturn($devRequires);
        $composer->setPackage($package);

        return new Event(ScriptEvents::PRE_AUTOLOAD_DUMP, $composer, $io);
    }
}
