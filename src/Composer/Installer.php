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
use FilesystemIterator;
use GlobIterator;
use RuntimeException;
use SplFileInfo;

use function array_any;
use function array_filter;
use function array_intersect;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_unique;
use function array_values;
use function assert;
use function base64_encode;
use function basename;
use function count;
use function dirname;
use function explode;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function implode;
use function in_array;
use function is_array;
use function is_bool;
use function is_file;
use function is_float;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function json_last_error_msg;
use function ltrim;
use function preg_last_error_msg;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function preg_replace;
use function preg_replace_callback;
use function realpath;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strpos;
use function substr;
use function trim;
use function usort;

use const DIRECTORY_SEPARATOR;
use const PHP_INT_MIN;
use const PREG_OFFSET_CAPTURE;

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
        /** @var array<string> $requiredPackagesAndExtensions */
        $requiredPackagesAndExtensions = array_unique([
            ...array_keys($event->getComposer()->getPackage()->getRequires()),
            ...array_keys($event->getComposer()->getPackage()->getDevRequires()),
            ...self::retrieveRequiredPackagesAndExtensions(self::getVendorDir($event->getComposer()), true),
        ]);
        /** @var array<string> $requiredPackagesAndExtensionsWithoutDev */
        $requiredPackagesAndExtensionsWithoutDev = array_unique([
            ...array_keys($event->getComposer()->getPackage()->getRequires()),
            ...array_keys($event->getComposer()->getPackage()->getDevRequires()),
            ...self::retrieveRequiredPackagesAndExtensions(self::getVendorDir($event->getComposer()), false),
        ]);

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

        $supportedFeatures = self::extractSupportedFeatures($json, $requiredPackagesAndExtensionsWithoutDev);

        if (array_key_exists('name', $json) && $json['name'] === 'wyrihaximus/makefiles') {
            self::generateMakefile($event->getIO(), $rootPackagePath, true, $requiredPackagesAndExtensions, $supportedFeatures);

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
                self::generateMakefile($event->getIO(), $rootPackagePath, false, $requiredPackagesAndExtensions, $supportedFeatures);

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

    /**
     * @param array<string>       $requiredPackagesAndExtensions
     * @param array<string, bool> $supportedFeatures
     */
    private static function generateMakefile(IOInterface $io, string $rootPackagePath, bool $selfRoot, array $requiredPackagesAndExtensions, array $supportedFeatures): void
    {
        $io->write('<info>wyrihaximus/makefiles:</info> Supported features Matrix:');
        foreach ($supportedFeatures as $name => $supported) {
            $io->write('<info>wyrihaximus/makefiles:</info> ' . $name . ': ' . ($supported ? '✅' : '❌'));
        }

        $referenceRoot    = $rootPackagePath . ($selfRoot ? '' : 'vendor' . DIRECTORY_SEPARATOR . 'wyrihaximus' . DIRECTORY_SEPARATOR . 'makefiles' . DIRECTORY_SEPARATOR);
        $makefileContents = file_get_contents($referenceRoot . 'templates' . DIRECTORY_SEPARATOR . 'Makefile.PHP');

        if (! is_string($makefileContents)) {
            return;
        }

        $io->write('<info>wyrihaximus/makefiles:</info> Generating Makefile');

        $makefileContents = self::loadIncludes($io, $rootPackagePath, $referenceRoot, $makefileContents);
        $makefileContents = self::injectExtraServicesFlag($makefileContents, $rootPackagePath);
        $makefileContents = self::injectServiceLifecycle($makefileContents, $rootPackagePath);
        $makefileContents = self::injectTaskLists($makefileContents, $supportedFeatures);
        $makefileContents = self::injectHelp($makefileContents);
        $makefileContents = self::injectRequirementConditionals($makefileContents, $requiredPackagesAndExtensions);
        $makefileContents = self::injectLowestVersions($makefileContents, $rootPackagePath);
        $makefileContents = self::injectSupportedFeatures($makefileContents, $supportedFeatures);
        $makefileContents = self::injectBase64Files($makefileContents);

        file_put_contents($rootPackagePath . 'Makefile', $makefileContents);

        $io->write('<info>wyrihaximus/makefiles:</info> Generating Makefile took less than a second');
    }

    /** Replaces `include includes/*` directives with the contents of the referenced include files. */
    private static function loadIncludes(IOInterface $io, string $rootPackagePath, string $referenceRoot, string $makefileContents): string
    {
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

        if (! is_string($makefileContents)) {
            throw new RuntimeException('Failed load in includes: ' . preg_last_error_msg());
        }

        return $makefileContents;
    }

    /**
     * Resolves `when_target_exists_in_extra(...)` placeholders depending on whether the package
     * defines the given target in `etc/Makefile`.
     */
    private static function injectExtraServicesFlag(string $makefileContents, string $rootPackagePath): string
    {
        preg_match_all(
            '/([A-Z_]+)=when_target_exists_in_extra\(([a-z0-9-]+),\s+([A-Za-z0-9\"-]+),\s+([A-Za-z0-9,\"-]+)\)/',
            $makefileContents,
            $matches,
            PREG_OFFSET_CAPTURE,
        );

        if ($matches[0] === []) {
            return $makefileContents;
        }

        $etcMakefilePath = $rootPackagePath . 'etc' . DIRECTORY_SEPARATOR . 'Makefile';
        $etcMakefile     = is_file($etcMakefilePath) ? file_get_contents($etcMakefilePath) : false;

        foreach ($matches[0] as $i => $fullLine) {
            $targetName = $matches[2][$i][0];
            $hasTarget  = is_string($etcMakefile)
                && preg_match('/^' . preg_quote($targetName, '/') . ':/m', $etcMakefile) === 1;

            $makefileContents = str_replace(
                $fullLine[0],
                $matches[1][$i][0] . '=' . ($hasTarget ? $matches[3][$i][0] : $matches[4][$i][0]),
                $makefileContents,
            );
        }

        return $makefileContents;
    }

    /**
     * Expands `service_start(target)` / `service_cleanup(target)` placeholders into a bash recipe
     * with EXIT trap when the named targets exist in the compiled Makefile.
     */
    private static function injectServiceLifecycle(string $makefileContents, string $rootPackagePath): string
    {
        if (! str_contains($makefileContents, 'service_start(') && ! str_contains($makefileContents, 'service_cleanup(')) {
            return $makefileContents;
        }

        $availableTargets = self::extractMakefileTargets($makefileContents);

        $lines  = explode("\n", $makefileContents);
        $output = [];
        $count  = count($lines);
        $index  = 0;

        while ($index < $count) {
            $line = $lines[$index];

            if (preg_match('/^([a-z0-9-]+):/', $line) !== 1) {
                $output[] = $line;
                $index++;

                continue;
            }

            $targetLine = $line;
            $index++;
            $recipeLines = [];

            while ($index < $count && str_starts_with($lines[$index], "\t")) {
                $recipeLines[] = $lines[$index];
                $index++;
            }

            $recipeBody = implode("\n", $recipeLines);

            if (! str_contains($recipeBody, 'service_start(') && ! str_contains($recipeBody, 'service_cleanup(')) {
                $output[] = $targetLine;
                foreach ($recipeLines as $recipeLine) {
                    $output[] = $recipeLine;
                }

                continue;
            }

            $output[] = $targetLine;
            $output[] = self::expandServiceLifecycleRecipe($recipeLines, $availableTargets);
        }

        return implode("\n", $output);
    }

    /**
     * @param list<string> $recipeLines
     * @param list<string> $availableTargets
     */
    private static function expandServiceLifecycleRecipe(array $recipeLines, array $availableTargets): string
    {
        $startTargets   = [];
        $cleanupTargets = [];
        $middleLines    = [];

        foreach ($recipeLines as $recipeLine) {
            if (preg_match('/^\tservice_start\(([a-z0-9-]+)\)/', $recipeLine, $matches) === 1) {
                if (in_array($matches[1], $availableTargets, true)) {
                    $startTargets[] = $matches[1];
                }

                continue;
            }

            if (preg_match('/^\tservice_cleanup\(([a-z0-9-]+)\)/', $recipeLine, $matches) === 1) {
                if (in_array($matches[1], $availableTargets, true)) {
                    $cleanupTargets[] = $matches[1];
                }

                continue;
            }

            $middleLines[] = ltrim($recipeLine, "\t");
        }

        if ($startTargets === [] && $cleanupTargets === []) {
            return implode("\n", array_map(static fn (string $middleLine): string => "\t" . $middleLine, $middleLines));
        }

        $middleCommand = implode(' && ', array_map(trim(...), $middleLines));
        $ciRecipe      = "\t" . $middleCommand;

        $bashLines = [];

        if ($startTargets !== []) {
            $bashLines[] = "\t$(MAKE) " . implode(' ', $startTargets) . '; \\';
        }

        if ($cleanupTargets !== []) {
            $bashLines[] = "\ttrap \"$(MAKE) " . implode(' ', $cleanupTargets) . ' || true" EXIT; \\';
        }

        $bashLines[] = "\t" . $middleCommand;
        $localRecipe = "\t@bash -ec '" . implode("\n", $bashLines) . "'";

        return implode("\n", [
            'ifeq ("$(IN_CI)","TRUE")',
            $ciRecipe,
            'else',
            $localRecipe,
            'endif',
        ]);
    }

    /** @return list<string> */
    private static function extractMakefileTargets(string $makefileContents): array
    {
        preg_match_all('/^([a-z0-9-]+):/m', $makefileContents, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * Populates the aggregate task targets (all, ci-*, on-install-or-update) by scanning
     * annotated targets and expands the `make-list(...)` / `task-list(...)` placeholders.
     *
     * @param array<string, bool> $supportedFeatures
     */
    private static function injectTaskLists(string $makefileContents, array $supportedFeatures): string
    {
        $hashCountMap   = [
            2 => [
                'all',
                'on-install-or-update',
            ],
            4 => ['on-install-or-update'],
        ];
        $typesToTaskMap = [
            'A' => [
                'all',
                'ci-all',
            ],
            'E' => ['contrib'],
            'D' => ['ci-dos'],
            'I' => [
                'all',
                'ci-all',
                'on-install-or-update',
            ],
            'L' => [
                'all',
                'ci-all',
                'ci-low',
            ],
            'C' => [
                'all',
                'ci-all',
                'ci-locked',
            ],
            'H' => [
                'all',
                'ci-all',
                'ci-high',
            ],
        ];
        $tasks          = [
            'all' => [],
            'contrib' => [],
            'ci-all' => [],
            'ci-dos' => [],
            'ci-low' => [],
            'ci-locked' => [],
            'ci-high' => [],
            'on-install-or-update' => [],
        ];

        preg_match_all(
            '/([A-Z0-9a-z-]+):\s([#{2,4}]+)(\s+([A-Za-z0-9\@\*\'\(\)\<\>\:.,_\`\/\-\\\\]+\s+)+)##\*([AEDILCH]+)\*(##\^([a-z-|]+)\^##)?/',
            $makefileContents,
            $matches,
            PREG_OFFSET_CAPTURE,
        );

        $counter = count($matches[0]);
        for ($i = 0; $i < $counter; $i++) {
            foreach ($typesToTaskMap as $type => $taskMap) {
                foreach ($taskMap as $task) {
                    if (! str_contains($matches[5][$i][0], $type)) {
                        continue;
                    }

                    if (in_array($matches[1][$i][0], $tasks[$task], true)) {
                        continue;
                    }

                    if (
                        $type === 'I' &&
                        array_key_exists(strlen($matches[2][$i][0]), $hashCountMap) &&
                        ! in_array($task, $hashCountMap[strlen($matches[2][$i][0])], true)
                    ) {
                        continue;
                    }

                    if ($matches[7][$i][0] !== '') {
                        foreach (explode('|', $matches[7][$i][0]) as $feature) {
                            if (! array_key_exists($feature, $supportedFeatures) || $supportedFeatures[$feature] === false) {
                                continue 2;
                            }
                        }
                    }

                    $tasks[$task][] = $matches[1][$i][0];
                }
            }
        }

        foreach ($tasks as $taskTarget => $taskList) {
            $jsonTaskList = json_encode($taskList);
            if (! is_string($jsonTaskList)) {
                throw new RuntimeException('Failed to JSON encode task list: ' . json_last_error_msg());
            }

            $makefileContents = str_replace('make-list(' . $taskTarget . ')', '$(MAKE) ' . implode(' ', $taskList) . ' ## Count: ' . count($taskList), $makefileContents);
            $makefileContents = str_replace('task-list(' . $taskTarget . ')', '@echo "' . str_replace('"', '\"', $jsonTaskList) . '" ## Count: ' . count($taskList), $makefileContents);
        }

        $makefileContents = self::injectAggregateDirectDockerFlags($makefileContents, $tasks);

        return self::injectExtraServicesDirectDockerFlags($makefileContents);
    }

    /**
     * Resolves `when_aggregate_has_direct_docker_tasks(...)` placeholders depending on whether any target
     * in the given aggregate task list calls `docker` directly in its recipe.
     *
     * @param array<string, list<string>> $tasks
     */
    private static function injectAggregateDirectDockerFlags(string $makefileContents, array $tasks): string
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
                static fn (string $target): bool => self::targetCallsDockerDirectly($makefileContents, $target, []),
            );
            $makefileContents     = str_replace(
                $fullLine[0],
                $matches[1][$i][0] . '=' . ($hasDirectDockerTasks ? $matches[3][$i][0] : $matches[4][$i][0]),
                $makefileContents,
            );
        }

        return $makefileContents;
    }

    /**
     * When a package declares extra services, aggregate targets must run on the host so
     * service lifecycle recipes can invoke `docker compose` outside the PHP container.
     */
    private static function injectExtraServicesDirectDockerFlags(string $makefileContents): string
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

    private const array DOCKER_FRAMEWORK_VARIABLES = [
        'DOCKER_RUN',
        'DOCKER_RUN_WITHOUT_NETWORK_FOR_COMPOSER',
        'DOCKER_RUN_WITH_SOCKET',
        'DOCKER_SHELL',
        'DOCKER_INTERACTIVE_SHELL',
    ];

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
        if (
            preg_match_all(
                '/^([A-Z_][A-Z0-9_]*)(?:[:?+]?=)(.+)$/m',
                $makefileContents,
                $matches,
            ) === false
        ) {
            return $variables;
        }

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

    /** Expands the `help(...)` placeholders with pregenerated target listings for awk formatting. */
    private static function injectHelp(string $makefileContents): string
    {
        /** @var array<string, list<array{0: string, 1: string}>> $helpTargets */
        $helpTargets = [
            'main' => [],
            'migrations' => [],
            'contrib' => [],
        ];

        preg_match_all(
            '/^([a-zA-Z0-9_-]+):.*?## .+$/m',
            $makefileContents,
            $matches,
        );

        foreach ($matches[0] as $i => $fullLine) {
            if (str_contains($fullLine, '##U##')) {
                continue;
            }

            if (preg_match('/^([^:]+):.*?## (.+)$/', $fullLine, $parts) !== 1) {
                continue;
            }

            $target         = $matches[1][$i];
            $haspos         = strpos($parts[2], '#');
            $helpLine       = trim(
                $target . ': ## ' . substr(
                    $parts[2],
                    0,
                    $haspos !== false ? $haspos : strlen($parts[2]),
                ),
            );
            $isMigration    = str_starts_with($target, 'migrations-');
            $hasContribFlag = preg_match('/##\*([AEDILCH]+)\*/', $fullLine, $typeMatch) === 1 && str_contains($typeMatch[1], 'E');

            if (! $isMigration) {
                $helpTargets['main'][] = [
                    $target,
                    $helpLine,
                ];
            }

            if ($isMigration) {
                $helpTargets['migrations'][] = [
                    $target,
                    $helpLine,
                ];
            }

            if ($isMigration || ! $hasContribFlag) {
                continue;
            }

            $helpTargets['contrib'][] = [
                $target,
                $helpLine,
            ];
        }

        foreach ($helpTargets as $helpType => $entries) {
            usort($entries, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

            $helpList = implode(
                '\n',
                array_map(
                    static fn (array $entry): string => str_replace("'", "'\\''", $entry[1]),
                    $entries,
                ),
            );

            $makefileContents = str_replace(
                'help(' . $helpType . ')',
                "'" . $helpList . "'",
                $makefileContents,
            );
        }

        return $makefileContents;
    }

    /**
     * Resolves `when_in_requirements(...)` placeholders to one of two values depending on whether
     * any of the listed packages/extensions are present in the requirements.
     *
     * @param array<string> $requiredPackagesAndExtensions
     */
    private static function injectRequirementConditionals(string $makefileContents, array $requiredPackagesAndExtensions): string
    {
        preg_match_all(
            '/([A-Z_]+)=when_in_requirements\(([A-Za-z0-9,\/\[\]\"-]+),\s+([A-Za-z0-9\"-]+),\s+([A-Za-z0-9,\"-]+)\)/',
            $makefileContents,
            $matchesSecondPass,
            PREG_OFFSET_CAPTURE,
        );

        foreach ($matchesSecondPass[0] as $i => $fullLine) {
            $requiredPackagesJson = json_decode($matchesSecondPass[2][$i][0], true);
            $requiredPackages     = is_array($requiredPackagesJson) ? array_values(array_filter($requiredPackagesJson, is_string(...))) : [];

            $makefileContents = str_replace(
                $fullLine[0],
                $matchesSecondPass[1][$i][0] . '=' . (count(array_intersect(
                    $requiredPackages,
                    $requiredPackagesAndExtensions,
                )) > 0 ? $matchesSecondPass[3][$i][0] : $matchesSecondPass[4][$i][0]),
                $makefileContents,
            );
        }

        return $makefileContents;
    }

    /** Resolves `lowest_cleaned_version_in_tree_from_file(...)` placeholders to the major.minor version found at the given JSON path. */
    private static function injectLowestVersions(string $makefileContents, string $rootPackagePath): string
    {
        preg_match_all(
            '/([A-Z_]+)=lowest_cleaned_version_in_tree_from_file\(\"([A-Za-z0-9.-\/]+)\",\s+\"([A-Za-z0-9-.]+)\"\)/',
            $makefileContents,
            $matchesThirdPass,
            PREG_OFFSET_CAPTURE,
        );

        foreach ($matchesThirdPass[0] as $i => $fullLine) {
            $fileContents = file_get_contents($rootPackagePath . $matchesThirdPass[2][$i][0]);
            $json         = is_string($fileContents) ? json_decode($fileContents, true) : null;
            $version      = self::cleanVersionToMajorMinor(self::getValueFromTree(
                is_array($json) ? $json : [],
                explode('.', $matchesThirdPass[3][$i][0]),
            ));

            $makefileContents = str_replace(
                $fullLine[0],
                $matchesThirdPass[1][$i][0] . '="' . $version . '"',
                $makefileContents,
            );
        }

        return $makefileContents;
    }

    /**
     * Expands the `supported-features(list)` and `supported-features(raw)` placeholders.
     *
     * @param array<string, bool> $supportedFeatures
     */
    private static function injectSupportedFeatures(string $makefileContents, array $supportedFeatures): string
    {
        $supportedFeaturesList = array_keys(array_filter($supportedFeatures, static fn (bool $featureSupported): bool => $featureSupported));
        $supportedFeaturesJson = json_encode($supportedFeaturesList);
        if (! is_string($supportedFeaturesJson)) {
            throw new RuntimeException('Failed to JSON encode supported features json: ' . json_last_error_msg());
        }

        $makefileContents = str_replace('supported-features(list)', '@echo "' . str_replace('"', '\"', $supportedFeaturesJson) . '" ## Count: ' . count($supportedFeaturesList), $makefileContents);

        return str_replace('supported-features(raw)', $supportedFeaturesJson, $makefileContents);
    }

    /** Replaces every `base64(<filename>)` placeholder with the base64 encoded contents of the matching file in etc/base64. */
    private static function injectBase64Files(string $makefileContents): string
    {
        $files = glob(dirname(__FILE__, 3) . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'base64' . DIRECTORY_SEPARATOR . '*');
        if (! is_array($files)) {
            return $makefileContents;
        }

        $base64FileContents = [];
        foreach ($files as $file) {
            $fileContents = file_get_contents($file);
            if (! is_string($fileContents)) {
                continue;
            }

            $base64FileContents['base64(' . basename($file) . ')'] = base64_encode($fileContents);
        }

        return str_replace(array_keys($base64FileContents), array_values($base64FileContents), $makefileContents);
    }

    private static function loadInclude(IOInterface $io, string $makefilesPackageRoot, string $filename): string
    {
        $candidatePath = $makefilesPackageRoot . $filename;
        if (! is_file($candidatePath)) {
            return '';
        }

        $makefileIncludePath = realpath($candidatePath);
        if (! str_starts_with($makefileIncludePath, $makefileIncludePath) || ! file_exists($makefileIncludePath)) {
            return '';
        }

        $makefileContents =  file_get_contents($makefileIncludePath);
        if (! is_string($makefileContents)) {
            return '';
        }

        $io->write('<info>wyrihaximus/makefiles:</info> Including: ' . $filename);

        return $makefileContents;
    }

    /**
     * @param non-empty-string $vendorDir
     *
     * @return iterable<string>
     */
    private static function retrieveRequiredPackagesAndExtensions(string $vendorDir, bool $includeDev): iterable
    {
        foreach (new GlobIterator($vendorDir . '/*/*/composer.json', FilesystemIterator::KEY_AS_FILENAME | FilesystemIterator::SKIP_DOTS) as $node) {
            assert($node instanceof SplFileInfo);
            $composerJson = file_get_contents($node->getRealPath());
            if ($composerJson === false) {
                continue;
            }

            $json = json_decode($composerJson, true);
            if (! is_array($json)) {
                continue;
            }

            if (array_key_exists('require', $json) && is_array($json['require'])) {
                yield from array_filter(array_keys($json['require']), is_string(...));
            }

            if (! array_key_exists('require-dev', $json) || ! is_array($json['require-dev'])) {
                continue;
            }

            if (! $includeDev) {
                continue;
            }

            yield from array_filter(array_keys($json['require-dev']), is_string(...));
        }
    }

    /**
     * @param array<mixed>  $json
     * @param array<string> $requiredPackagesAndExtensions
     *
     * @return array<string, bool>
     */
    private static function extractSupportedFeatures(array $json, array $requiredPackagesAndExtensions): array
    {
        /** @var array<string, bool> $supportedFeatures */
        $supportedFeatures = SupportedFeatures::DEFAULTS;

        foreach ($requiredPackagesAndExtensions as $packageOrExtension) {
            if ($packageOrExtension === 'ext-parallel') {
                $supportedFeatures[SupportedFeatures::FEATURE_MACOS]   = false;
                $supportedFeatures[SupportedFeatures::FEATURE_WINDOWS] = false;
                $supportedFeatures[SupportedFeatures::FEATURE_ZTS]     = true;
            }

            if ($packageOrExtension !== 'ext-pcntl') {
                continue;
            }

            $supportedFeatures[SupportedFeatures::FEATURE_MACOS]   = false;
            $supportedFeatures[SupportedFeatures::FEATURE_WINDOWS] = false;
        }

        if (
            array_key_exists('extra', $json)
            && is_array($json['extra'])
            && array_key_exists('wyrihaximus', $json['extra'])
            && is_array($json['extra']['wyrihaximus'])
            && array_key_exists('supported-features', $json['extra']['wyrihaximus'])
            && is_array($json['extra']['wyrihaximus']['supported-features'])
        ) {
            foreach ($json['extra']['wyrihaximus']['supported-features'] as $feature => $featureSupported) {
                if (! array_key_exists($feature, SupportedFeatures::DEFAULTS)) {
                    continue;
                }

                if (! is_bool($featureSupported)) {
                    continue;
                }

                $supportedFeatures[$feature] = $featureSupported;
            }
        }

        return $supportedFeatures;
    }

    /**
     * @param array<mixed>  $array
     * @param array<string> $keys
     */
    private static function getValueFromTree(array $array, array $keys): string
    {
        $current = $array;
        foreach ($keys as $key) {
            if (! is_array($current) || ! array_key_exists($key, $current)) {
                return '0';
            }

            $current = $current[$key];
        }

        if (is_string($current) || is_int($current) || is_float($current)) {
            return (string) $current;
        }

        return '0';
    }

    private static function cleanVersionToMajorMinor(string $version): string
    {
        [$major, $minor] = explode('.', $version);

        return $major . '.' . $minor;
    }
}
