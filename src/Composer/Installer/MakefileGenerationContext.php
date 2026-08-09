<?php

declare(strict_types=1);

namespace WyriHaximus\Makefiles\Composer\Installer;

use Composer\IO\IOInterface;

final readonly class MakefileGenerationContext
{
    /** @param array<string, bool> $supportedFeatures */
    public function __construct(
        public IOInterface $io,
        public string $rootPackagePath,
        public string $referenceRoot,
        public Requirements $requirements,
        public array $supportedFeatures,
    ) {
    }
}
