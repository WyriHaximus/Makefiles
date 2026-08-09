<?php

declare(strict_types=1);

namespace WyriHaximus\Makefiles\Composer\Installer;

use function array_keys;
use function array_values;
use function base64_encode;
use function basename;
use function dirname;
use function file_get_contents;
use function glob;
use function is_file;
use function is_readable;
use function str_replace;

use const DIRECTORY_SEPARATOR;

final class Base64FileInjector
{
    private function __construct()
    {
    }

    public static function inject(string $makefileContents): string
    {
        $globResult = glob(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'base64' . DIRECTORY_SEPARATOR . '*');
        if ($globResult === false) {
            return $makefileContents;
        }

        $base64FileContents = [];
        foreach ($globResult as $file) {
            if (! is_file($file) || ! is_readable($file)) {
                continue;
            }

            $fileContents = file_get_contents($file);
            if ($fileContents === false) {
                continue;
            }

            $base64FileContents['base64(' . basename($file) . ')'] = base64_encode($fileContents);
        }

        return str_replace(array_keys($base64FileContents), array_values($base64FileContents), $makefileContents);
    }
}
