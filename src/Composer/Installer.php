<?php

declare(strict_types=1);

namespace WyriHaximus\Makefiles\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Exception;

use function array_key_exists;
use function dirname;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function is_array;
use function is_string;
use function json_decode;
use function preg_replace_callback;
use function realpath;
use function str_starts_with;

use const DIRECTORY_SEPARATOR;
use const PHP_INT_MIN;

final class Installer implements PluginInterface, EventSubscriberInterface
{
    /** @return array<string, array<string|int>> */
    public static function getSubscribedEvents(): array
    {
        return [ScriptEvents::PRE_AUTOLOAD_DUMP => ['findEventListeners', PHP_INT_MIN]];
    }

    public function activate(Composer $composer, IOInterface $io): void
    {
        // does nothing, see getSubscribedEvents() instead.
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        // does nothing, see getSubscribedEvents() instead.
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        // does nothing, see getSubscribedEvents() instead.
    }

    /**
     * Called before every dump autoload, generates a fresh PHP class.
     */
    public static function findEventListeners(Event $event): void
    {
        $rootPackagePath = dirname(self::getVendorDir($event->getComposer())) . DIRECTORY_SEPARATOR;
        if (! file_exists($rootPackagePath . '/composer.json')) {
            return;
        }

        $jsonRaw = file_get_contents($rootPackagePath . '/composer.json');
        if (! is_string($jsonRaw)) {
            return;
        }

        $json = json_decode($jsonRaw, true);
        if (! is_array($json)) {
            return;
        }

        if (array_key_exists('name', $json) && $json['name'] === 'wyrihaximus/makefiles') {
            self::generateMakefile($event->getIO(), $rootPackagePath, true);

            return;
        }

        if (! array_key_exists('require-dev', $json)) {
            return;
        }

        if (! is_array($json['require-dev'])) {
            return;
        }

        foreach ($json['require-dev'] as $package => $targetVersion) {
            if ($package === 'wyrihaximus/makefiles') {
                self::generateMakefile($event->getIO(), $rootPackagePath, false);

                return;
            }
        }
    }

    /** @return non-empty-string */
    private static function getVendorDir(Composer $composer): string
    {
        $vendorDir = $composer->getConfig()->get('vendor-dir');
        if ($vendorDir === '' || ! file_exists($vendorDir)) {
            throw new Exception('vendor-dir must be a string');
        }

        return $vendorDir;
    }

    private static function generateMakefile(IOInterface $io, string $rootPackagePath, bool $selfRoot): void
    {
        $io->write('<info>wyrihaximus/makefiles:</info> Generating Makefile');
        $referenceRoot    = $rootPackagePath . ($selfRoot ? '' : 'vendor' . DIRECTORY_SEPARATOR . 'wyrihaximus' . DIRECTORY_SEPARATOR . 'makefiles' . DIRECTORY_SEPARATOR);
        $makefileContents = file_get_contents($referenceRoot . 'templates' . DIRECTORY_SEPARATOR . 'Makefile.PHP');

        if (! is_string($makefileContents)) {
            return;
        }

        $makefileContents = preg_replace_callback(
            '/include includes\/([a-zA-Z.]+)/',
            static fn (array $matches): string => $matches[1] === 'EXTRA.mk' ? self::loadInclude(
                $io,
                $rootPackagePath,
                'etc/Makefile',
            ) : self::loadInclude(
                $io,
                $referenceRoot . 'includes' . DIRECTORY_SEPARATOR,
                $matches[1],
            ),
            $makefileContents,
        );

        file_put_contents($rootPackagePath . 'Makefile', $makefileContents);

        $io->write('<info>wyrihaximus/makefiles:</info> Generating Makefile took less than a second');
    }

    private static function loadInclude(IOInterface $io, string $makefilesPackageRoot, string $filename): string
    {
        $makefileIncludePath = realpath($makefilesPackageRoot . $filename);
        if (! is_string($makefileIncludePath) || ! str_starts_with($makefileIncludePath, $makefileIncludePath) || ! file_exists($makefileIncludePath)) {
            return '';
        }

        $makefileContents =  file_get_contents($makefileIncludePath);
        if (! is_string($makefileContents)) {
            return '';
        }

        $io->write('<info>wyrihaximus/makefiles:</info> Including: ' . $filename);

        return $makefileContents;
    }
}
