<?php

declare(strict_types=1);

namespace WyriHaximus\Makefiles\Composer\Installer;

final readonly class Requirements
{
    /**
     * @param list<string> $all
     * @param list<string> $withoutDev
     */
    public function __construct(
        public array $all,
        public array $withoutDev,
    ) {
    }
}
