<?php

declare(strict_types=1);

namespace WyriHaximus\Makefiles\Composer\Installer;

use RuntimeException;

use function array_any;
use function array_key_exists;
use function array_keys;
use function array_map;
use function explode;
use function implode;
use function in_array;
use function ltrim;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function preg_replace;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;

use const PREG_OFFSET_CAPTURE;

final class DirectDockerDetector
{
    private const array DOCKER_FRAMEWORK_VARIABLES = [
        'DOCKER_RUN',
        'DOCKER_RUN_WITHOUT_NETWORK_FOR_COMPOSER',
        'DOCKER_RUN_WITH_SOCKET',
        'DOCKER_SHELL',
        'DOCKER_INTERACTIVE_SHELL',
    ];

    private function __construct()
    {
    }

    /** @param array<string, list<string>> $tasks */
    public static function injectFlags(string $makefileContents, array $tasks): string
    {
        preg_match_all(
            '/([A-Z_]+)=when_aggregate_has_direct_docker_tasks\(([a-z-]+),\s+([A-Za-z0-9\"-]+),\s+([A-Za-z0-9,\"-]+)\)/',
            $makefileContents,
            $matches,
            PREG_OFFSET_CAPTURE,
        );

        foreach ($matches[0] as $i => $fullLine) {
            $taskKey = $matches[2][$i][0];
            if (! array_key_exists($taskKey, $tasks)) {
                throw new RuntimeException('Unknown task aggregate for direct docker detection: ' . $taskKey);
            }

            $hasDirectDockerTasks = array_any(
                $tasks[$taskKey],
                static fn (string $target): bool => self::targetUsesDocker($makefileContents, $target),
            );
            $makefileContents     = str_replace(
                $fullLine[0],
                $matches[1][$i][0] . '=' . ($hasDirectDockerTasks ? $matches[3][$i][0] : $matches[4][$i][0]),
                $makefileContents,
            );
        }

        return self::injectExtraServicesDirectDockerFlags($makefileContents);
    }

    /**
     * When a package declares extra services, aggregate targets must run on the host so
     * service lifecycle recipes can invoke `docker compose` outside the PHP container.
     */
    public static function injectExtraServicesDirectDockerFlags(string $makefileContents): string
    {
        if (! str_contains($makefileContents, 'HAS_EXTRA_SERVICES=TRUE')) {
            return $makefileContents;
        }

        foreach (['ALL_HAS_DIRECT_DOCKER_TASKS', 'CONTRIB_HAS_DIRECT_DOCKER_TASKS'] as $flag) {
            $makefileContents = preg_replace(
                '/^' . preg_quote($flag, '/') . '=FALSE$/m',
                $flag . '=TRUE',
                $makefileContents,
            ) ?? $makefileContents;
        }

        return $makefileContents;
    }

    public static function targetUsesDocker(string $makefileContents, string $target): bool
    {
        return self::targetCallsDockerDirectly($makefileContents, $target, []);
    }

    /**
     * @param list<string>        $visited
     * @param array<string, true> $dockerWrapperVariables
     */
    private static function targetCallsDockerDirectly(
        string $makefileContents,
        string $target,
        array $visited,
        array $dockerWrapperVariables = [],
    ): bool {
        if (in_array($target, $visited, true)) {
            return false;
        }

        $visited[] = $target;
        $recipe    = self::extractTargetRecipe($makefileContents, $target);
        if ($recipe === null) {
            return false;
        }

        if ($dockerWrapperVariables === []) {
            $dockerWrapperVariables = self::extractDockerWrapperVariables($makefileContents);
        }

        if (preg_match('/^\tdocker\b/m', $recipe) === 1) {
            return true;
        }

        if (self::recipeUsesDockerWrapperVariable($recipe, $dockerWrapperVariables)) {
            return true;
        }

        if (preg_match_all('/\$\(MAKE\)\s+([a-zA-Z0-9_-]+)/', $recipe, $subTargets) === false) {
            return false;
        }

        if ($subTargets[1] === []) {
            return false;
        }

        return array_any(
            $subTargets[1],
            static fn (string $subTarget): bool => self::targetCallsDockerDirectly(
                $makefileContents,
                $subTarget,
                $visited,
                $dockerWrapperVariables,
            ),
        );
    }

    /** @return array<string, true> */
    private static function extractDockerWrapperVariables(string $makefileContents): array
    {
        $variables = [];
        preg_match_all(
            '/^([A-Z_][A-Z0-9_]*)(?:[:?+]?=)(.+)$/m',
            $makefileContents,
            $matches,
        );

        foreach ($matches[1] as $i => $name) {
            if (in_array($name, self::DOCKER_FRAMEWORK_VARIABLES, true)) {
                continue;
            }

            if (preg_match('/^docker\b/', ltrim($matches[2][$i])) !== 1) {
                continue;
            }

            $variables[$name] = true;
        }

        return $variables;
    }

    /** @param array<string, true> $dockerWrapperVariables */
    private static function recipeUsesDockerWrapperVariable(string $recipe, array $dockerWrapperVariables): bool
    {
        if ($dockerWrapperVariables === []) {
            return false;
        }

        $names = implode('|', array_map(static fn (string $name): string => preg_quote($name, '/'), array_keys($dockerWrapperVariables)));

        return preg_match('/^\t\$\((?:' . $names . ')\)/m', $recipe) === 1
            || preg_match('/^\t\$\{(?:' . $names . ')\}/m', $recipe) === 1;
    }

    private static function extractTargetRecipe(string $makefileContents, string $target): string|null
    {
        if (preg_match('/^' . preg_quote($target, '/') . ':[^\n]*\n/m', $makefileContents, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $offset = $match[0][1] + strlen($match[0][0]);
        $rest   = substr($makefileContents, $offset);

        if ($rest === '') {
            return '';
        }

        $recipeLines = [];

        foreach (explode("\n", $rest) as $line) {
            if (self::isTargetRecipeLine($line)) {
                $recipeLines[] = $line;

                continue;
            }

            if ($recipeLines !== []) {
                break;
            }

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            break;
        }

        if ($recipeLines === []) {
            return '';
        }

        return implode("\n", $recipeLines) . "\n";
    }

    private static function isTargetRecipeLine(string $line): bool
    {
        return str_starts_with($line, "\t")
            || str_starts_with($line, 'ifeq')
            || str_starts_with($line, 'else')
            || str_starts_with($line, 'endif');
    }
}
