<?php

declare(strict_types=1);

namespace WyriHaximus\Makefiles\Composer\Installer;

use function explode;
use function implode;
use function preg_match;
use function preg_match_all;
use function str_replace;
use function str_starts_with;

final class DockerEnvironmentInjector
{
    private function __construct()
    {
    }

    public static function inject(string $makefileContents): string
    {
        $environmentVariables = [];
        $lines                = explode("\n", $makefileContents);

        foreach ($lines as $index => $line) {
            if (preg_match('/^[A-Za-z0-9_.-]+:/', $line) === 1) {
                $environmentVariables = [];

                $metadata = $line;
                if (preg_match('/##\*[AEDILCHK]+\*##(?<metadata>.*)\z/', $line, $marker) === 1) {
                    $metadata = $marker['metadata'];
                }

                preg_match_all('/%([A-Z_][A-Z0-9_]*(?:\|[A-Z_][A-Z0-9_]*)*)%/', $metadata, $matches);
                foreach ($matches[1] as $variables) {
                    $environmentVariables = [...$environmentVariables, ...explode('|', $variables)];
                }

                continue;
            }

            if ($environmentVariables === []) {
                continue;
            }

            if (str_starts_with($line, "\t")) {
                $lines[$index] = str_replace(
                    '$(DOCKER_RUN)',
                    '$(subst docker run,docker run -e ' . implode(' -e ', $environmentVariables) . ',$(DOCKER_RUN))',
                    $line,
                );

                continue;
            }

            if (
                $line === ''
                || str_starts_with($line, '#')
                || preg_match('/^(?:ifeq|ifneq|ifdef|ifndef|else|endif)\b/', $line) === 1
            ) {
                continue;
            }

            $environmentVariables = [];
        }

        return implode("\n", $lines);
    }
}
