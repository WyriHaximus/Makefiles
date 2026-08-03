<?php

declare(strict_types=1);

namespace WyriHaximus\Makefiles\Composer\Installer;

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
                            if (! array_key_exists($feature, $context->supportedFeatures) || $context->supportedFeatures[$feature] === false) {
                                continue 2;
                            }
                        }
                    }

                    $tasks[$task][] = $matches[1][$i][0];
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
}
