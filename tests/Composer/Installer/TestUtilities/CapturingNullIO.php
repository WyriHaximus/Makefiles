<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Makefiles\Composer\Installer\TestUtilities;

use Composer\IO\NullIO;
use RuntimeException;
use Symfony\Component\Console\Output\StreamOutput;

use function fopen;
use function fseek;
use function is_resource;
use function stream_get_contents;

final class CapturingNullIO extends NullIO
{
    private readonly StreamOutput $output;

    public function __construct()
    {
        $stream = fopen('php://memory', 'rw');
        if (! is_resource($stream)) {
            throw new RuntimeException('Failed to open stream');
        }

        $this->output = new StreamOutput($stream, decorated: false);
    }

    public function output(): string
    {
        fseek($this->output->getStream(), 0);

        $contents = stream_get_contents($this->output->getStream());

        return $contents === false ? '' : $contents;
    }

    /** @inheritDoc */
    public function write($messages, bool $newline = true, int $verbosity = self::NORMAL): void
    {
        $this->output->write($messages, $newline, $verbosity & StreamOutput::OUTPUT_RAW);
    }
}
