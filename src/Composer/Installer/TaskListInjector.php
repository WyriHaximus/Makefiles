<?php

declare(strict_types=1);

namespace WyriHaximus\Makefiles\Composer\Installer;

use function array_all;
use function array_key_exists;
use function count;
use function explode;
use function implode;
use function in_array;
use function json_encode;
use function preg_match_all;
use function str_contains;
use function str_replace;
use function strlen;

use const JSON_THROW_ON_ERROR;
use const PREG_OFFSET_CAPTURE;

final class TaskListInjector
{
    private function __construct()
    {
    }

    public static function inject(MakefileGenerationContext $context, string $makefileContents): string
    {
        $hashCountMap = [
            2 => [
                'all',
                'on-install-or-update',
            ],
            4 => ['on-install-or-update'],
        ];
        // K: CI once on locked deps (ci-locked only), not ci-all or ci-dos.
        $typesToTaskMap = [
            'A' => [
                'all',
                'ci-all',
            ],
            'E' => [
                'all',
                'contrib',
            ],
            'D' => ['ci-dos'],
            'K' => ['ci-locked'],
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
            '/([A-Z0-9a-z-]+):\s(?:(?<prereqs>[a-z0-9-]+(?:\s+[a-z0-9-]+)*)\s+)?([#{2,4}]+)(\s+([A-Za-z0-9\@\*\'\(\)\<\>\:.,_\`\/\-\\\\]+\s+)+)##\*(?<types>[AEDILCHK]+)\*(?:##\^(?<features>[a-z-|]+)\^##)?/',
            $makefileContents,
            $matches,
            PREG_OFFSET_CAPTURE,
        );

        foreach (self::helpAnnotatedMatchIndices($matches[0]) as $matchIndex) {
            foreach ($typesToTaskMap as $type => $taskMap) {
                if (! str_contains($matches['types'][$matchIndex][0], $type)) {
                    continue;
                }

                foreach ($taskMap as $task) {
                    if (in_array($matches[1][$matchIndex][0], $tasks[$task], true)) {
                        continue;
                    }

                    if (
                        $type === 'I' &&
                        array_key_exists(strlen($matches[3][$matchIndex][0]), $hashCountMap) &&
                        ! in_array($task, $hashCountMap[strlen($matches[3][$matchIndex][0])], true)
                    ) {
                        continue;
                    }

                    $featureGate = $matches['features'][$matchIndex][0] ?? '';
                    if ($featureGate !== '' && ! self::allPipedFeaturesEnabled($featureGate, $context->supportedFeatures)) {
                        continue;
                    }

                    $tasks[$task][] = $matches[1][$matchIndex][0];
                }
            }
        }

        foreach ($tasks as $taskTarget => $taskList) {
            $jsonTaskList = json_encode($taskList, JSON_THROW_ON_ERROR);

            $makefileContents = str_replace('make-list(' . $taskTarget . ')', '$(MAKE) ' . implode(' ', $taskList) . ' ## Count: ' . count($taskList), $makefileContents);
            $makefileContents = str_replace('task-list(' . $taskTarget . ')', '@echo "' . str_replace('"', '\"', $jsonTaskList) . '" ## Count: ' . count($taskList), $makefileContents);
        }

        return DirectDockerDetector::injectFlags($makefileContents, $tasks);
    }

    private static function matchedLineIsHelpAnnotatedTarget(string $fullLine): bool
    {
        return str_contains($fullLine, '##*');
    }

    /**
     * @param list<array{0: string, 1: int}> $fullLineMatches
     *
     * @return list<int>
     */
    private static function helpAnnotatedMatchIndices(array $fullLineMatches): array
    {
        $indices = [];

        foreach ($fullLineMatches as $matchIndex => $fullLineMatch) {
            if (! self::matchedLineIsHelpAnnotatedTarget($fullLineMatch[0])) {
                continue;
            }

            $indices[] = $matchIndex;
        }

        return $indices;
    }

    /** @param array<string, bool> $supportedFeatures */
    private static function allPipedFeaturesEnabled(string $pipedFeatures, array $supportedFeatures): bool
    {
        return array_all(
            explode('|', $pipedFeatures),
            static fn (string $feature): bool => array_key_exists($feature, $supportedFeatures) && $supportedFeatures[$feature] !== false,
        );
    }
}
