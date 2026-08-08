<?php

declare(strict_types=1);

namespace WyriHaximus\Makefiles\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use WyriHaximus\Makefiles\Composer\Installer\MakefileGenerationContext;
use WyriHaximus\Makefiles\Composer\Installer\MakefileGenerator;
use WyriHaximus\Makefiles\Composer\Installer\RequirementsCollector;
use WyriHaximus\Makefiles\Composer\Installer\SupportedFeaturesResolver;

use function array_key_exists;
use function array_keys;
use function assert;
use function dirname;
use function file_exists;
use function file_get_contents;
use function is_array;
use function is_file;
use function is_readable;
use function is_string;
use function json_decode;

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
     * @api public
     * Called before every dump autoload, generates a fresh PHP class.
     */
    public static function findEventListeners(Event $event): void
    {
        $composer        = $event->getComposer();
        $requirements    = RequirementsCollector::collect($composer);
        $vendorDir       = $composer->getConfig()->get('vendor-dir');
        $rootPackagePath = dirname($vendorDir) . DIRECTORY_SEPARATOR;

        if (! file_exists($rootPackagePath . '/composer.json')) {
            return;
        }

        $composerJsonPath = $rootPackagePath . '/composer.json';
        if (! is_file($composerJsonPath) || ! is_readable($composerJsonPath)) {
            return;
        }

        $jsonRaw = file_get_contents($composerJsonPath);
        assert(is_string($jsonRaw));

        $json = json_decode($jsonRaw, true);
        if (! is_array($json)) {
            return;
        }

        $supportedFeatures = SupportedFeaturesResolver::resolve($json, $requirements);
        $selfRoot          = null;

        if (array_key_exists('name', $json) && $json['name'] === 'wyrihaximus/makefiles') {
            $selfRoot = true;
        } elseif (array_key_exists('require-dev', $json) && is_array($json['require-dev'])) {
            foreach (array_keys($json['require-dev']) as $package) {
                if ($package === 'wyrihaximus/makefiles') {
                    $selfRoot = false;

                    break;
                }
            }
        }

        if ($selfRoot === null) {
            return;
        }

        $referenceRoot = $rootPackagePath . ($selfRoot ? '' : 'vendor' . DIRECTORY_SEPARATOR . 'wyrihaximus' . DIRECTORY_SEPARATOR . 'makefiles' . DIRECTORY_SEPARATOR);

        MakefileGenerator::generate(new MakefileGenerationContext(
            $event->getIO(),
            $rootPackagePath,
            $referenceRoot,
            $requirements,
            $supportedFeatures,
        ));
    }
}
