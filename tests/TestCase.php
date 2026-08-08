<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Makefiles;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use WyriHaximus\Tests\Makefiles\Composer\Installer\TestUtilities\ProjectWorkspaceGuard;
use WyriHaximus\TestUtilities\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    #[Before]
    final protected function backupProjectWorkspaceOnce(): void
    {
        ProjectWorkspaceGuard::backupOnce();
    }

    #[After]
    final protected function restoreProjectWorkspace(): void
    {
        ProjectWorkspaceGuard::restore();
    }
}
