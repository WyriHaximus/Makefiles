<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Makefiles\Composer\Installer\TestUtilities;

use Composer\IO\NullIO;

use function is_string;

final class CapturingNullIO extends NullIO
{
    private string $buffer = '';

    public function output(): string
    {
        return $this->buffer;
    }

    /** @inheritDoc */
    public function write($messages, bool $newline = true, int $verbosity = self::NORMAL): void
    {
        foreach (is_string($messages) ? [$messages] : $messages as $message) {
            $this->buffer .= $message;

            if (! $newline) {
                continue;
            }

            $this->buffer .= "\n";
        }
    }
}
