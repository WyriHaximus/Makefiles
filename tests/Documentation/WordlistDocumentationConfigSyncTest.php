<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Makefiles\Documentation;

use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use RuntimeException;
use WyriHaximus\Makefiles\Documentation\WordlistDocumentationConfigSync;
use WyriHaximus\Tests\Makefiles\Composer\Installer\TestUtilities\ProjectSandbox;
use WyriHaximus\Tests\Makefiles\TestCase;

use function chmod;
use function file_get_contents;
use function file_put_contents;
use function json_decode;
use function mkdir;
use function rtrim;
use function unlink;

use const DIRECTORY_SEPARATOR;

final class WordlistDocumentationConfigSyncTest extends TestCase
{
    #[Test]
    public function syncThrowsWithNormalizedPathWhenWordlistIsMissingAndRootHasTrailingSeparators(): void
    {
        $root = $this->getTmpDir() . 'no-wordlist/';
        mkdir($root . 'etc/base64', 0777, true);

        $normalizedRoot = rtrim($root, '/\\');
        $expectedPath   = $normalizedRoot . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'wordlist.txt';

        try {
            WordlistDocumentationConfigSync::sync($root . '///');
            self::fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $exception) {
            self::assertSame('Wordlist file is missing: ' . $expectedPath, $exception->getMessage());
        }
    }

    #[Test]
    public function syncUpdatesValeVocabAndCspellFromWordlist(): void
    {
        $root = $this->createProjectTree(
            "alpha\n\nbeta\nalpha\n",
            '{"version":"0.2","language":"en","words":["old"]}',
        );

        WordlistDocumentationConfigSync::sync($root);

        self::assertSame("alpha\nbeta\n", file_get_contents($root . 'etc/base64/vale-vocab.txt'));

        $cspellPath    = $root . 'etc/base64/cspell.json';
        $cspellEncoded = (string) file_get_contents($cspellPath);
        self::assertStringEndsWith("\n", $cspellEncoded);
        self::assertStringContainsString("\n    \"version\": \"0.2\",\n", $cspellEncoded);

        $cspell = json_decode($cspellEncoded, true);
        self::assertIsArray($cspell);
        self::assertSame('0.2', $cspell['version']);
        self::assertSame(['alpha', 'beta'], $cspell['words']);
    }

    #[Test]
    public function writeCspellWordsPreservesUnescapedSlashesInConfig(): void
    {
        $path = $this->getTmpDir() . 'cspell-slashes.json';
        file_put_contents(
            $path,
            '{"version":"0.2","ignorePaths":["https://example.com/foo/bar"],"words":["old"]}',
        );

        WordlistDocumentationConfigSync::writeCspellWords($path, ['new']);

        $encoded = (string) file_get_contents($path);
        self::assertStringContainsString('https://example.com/foo/bar', $encoded);
        self::assertStringNotContainsString('https:\\/\\/example.com', $encoded);
    }

    #[Test]
    public function cspellJsonDecodeDepthIs512(): void
    {
        $reflection = new ReflectionClass(WordlistDocumentationConfigSync::class);
        self::assertSame(512, $reflection->getConstant('CSPELL_JSON_DECODE_DEPTH'));
    }

    #[Test]
    public function readWordlistReturnsTrimmedUniqueWords(): void
    {
        $path = $this->getTmpDir() . 'wordlist.txt';
        file_put_contents($path, " one\n\none\ntwo \n");

        self::assertSame(['one', 'two'], WordlistDocumentationConfigSync::readWordlist($path));
    }

    #[Test]
    public function privateConstructorIsNotInstantiable(): void
    {
        $reflection  = new ReflectionClass(WordlistDocumentationConfigSync::class);
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPrivate());
        $constructor->invoke($reflection->newInstanceWithoutConstructor());
    }

    #[Test]
    public function writeValeVocabCreatesFileWhenParentDirectoryIsWritable(): void
    {
        $directory = $this->getTmpDir() . 'vale-vocab-new/';
        mkdir($directory);
        $path = $directory . 'vale-vocab.txt';

        WordlistDocumentationConfigSync::writeValeVocab($path, ['word']);

        self::assertSame("word\n", file_get_contents($path));
    }

    #[Test]
    public function writeValeVocabThrowsWhenParentDirectoryDoesNotExist(): void
    {
        $path = $this->getTmpDir() . 'missing-parent/vale-vocab.txt';

        $this->assertRuntimeExceptionContainsPath(
            static function () use ($path): void {
                WordlistDocumentationConfigSync::writeValeVocab($path, ['word']);
            },
            $path,
            'Failed to write Vale vocabulary file',
        );
    }

    #[Test]
    public function writeValeVocabThrowsWhenParentPathIsAFile(): void
    {
        $parent = $this->getTmpDir() . 'parent-is-a-file';
        file_put_contents($parent, 'not a directory');
        $path = $parent . '/vale-vocab.txt';

        $this->assertRuntimeExceptionContainsPath(
            static function () use ($path): void {
                WordlistDocumentationConfigSync::writeValeVocab($path, ['word']);
            },
            $path,
            'Failed to write Vale vocabulary file',
        );
    }

    #[Test]
    public function writeValeVocabThrowsWhenTargetIsNotWritable(): void
    {
        $directory = $this->getTmpDir() . 'vale-vocab-directory/';
        mkdir($directory);

        $this->assertRuntimeExceptionContainsPath(
            static function () use ($directory): void {
                WordlistDocumentationConfigSync::writeValeVocab($directory, ['word']);
            },
            $directory,
            'Failed to write Vale vocabulary file',
        );
    }

    #[Test]
    public function readWordlistThrowsWhenFileIsMissing(): void
    {
        $path = $this->getTmpDir() . 'missing.txt';

        $this->assertRuntimeExceptionContainsPath(
            static fn (): array => WordlistDocumentationConfigSync::readWordlist($path),
            $path,
            'Wordlist file is missing',
        );
    }

    #[Test]
    public function writeCspellWordsThrowsWhenConfigIsMissing(): void
    {
        $path = $this->getTmpDir() . 'missing.json';

        $this->assertRuntimeExceptionContainsPath(
            static function () use ($path): void {
                WordlistDocumentationConfigSync::writeCspellWords($path, ['word']);
            },
            $path,
            'CSpell config file is missing',
        );
    }

    #[Test]
    #[DataProvider('provideInvalidCspellContents')]
    public function writeCspellWordsThrowsWhenConfigIsInvalid(string $contents, string $message, bool $expectsJsonException): void
    {
        $path = $this->getTmpDir() . 'cspell.json';
        file_put_contents($path, $contents);

        try {
            WordlistDocumentationConfigSync::writeCspellWords($path, ['word']);
            self::fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $exception) {
            self::assertSame(0, $exception->getCode());
            self::assertSame($message . ': ' . $path, $exception->getMessage());
            if ($expectsJsonException) {
                self::assertInstanceOf(JsonException::class, $exception->getPrevious());
            } else {
                self::assertNull($exception->getPrevious());
            }
        }
    }

    /** @return iterable<string, array{0: string, 1: string, 2: bool}> */
    public static function provideInvalidCspellContents(): iterable
    {
        yield 'invalid json' => ['{', 'CSpell config file is not valid JSON', true];
        yield 'json null' => ['null', 'CSpell config file must decode to an object', false];
    }

    #[Test]
    public function readWordlistThrowsWhenFileIsUnreadable(): void
    {
        if (! ProjectSandbox::canSimulateUnreadableFiles()) {
            self::markTestSkipped('File permission tests cannot run on Windows or as root.');
        }

        $path = $this->getTmpDir() . 'unreadable-wordlist.txt';
        file_put_contents($path, 'word');
        chmod($path, 0000);

        try {
            $this->assertRuntimeExceptionContainsPath(
                static fn (): array => WordlistDocumentationConfigSync::readWordlist($path),
                $path,
                'Wordlist file is not readable',
            );
        } finally {
            chmod($path, 0644);
            unlink($path);
        }
    }

    #[Test]
    public function writeCspellWordsThrowsWhenConfigIsNotWritable(): void
    {
        $path = $this->getTmpDir() . 'read-only-cspell.json';
        file_put_contents($path, '{"words":[]}');
        chmod($path, 0444);

        try {
            $this->assertRuntimeExceptionContainsPath(
                static function () use ($path): void {
                    WordlistDocumentationConfigSync::writeCspellWords($path, ['word']);
                },
                $path,
                'Failed to write CSpell config file',
            );
        } finally {
            chmod($path, 0644);
            unlink($path);
        }
    }

    #[Test]
    public function writeCspellWordsThrowsWhenConfigIsUnreadable(): void
    {
        if (! ProjectSandbox::canSimulateUnreadableFiles()) {
            self::markTestSkipped('File permission tests cannot run on Windows or as root.');
        }

        $path = $this->getTmpDir() . 'unreadable-cspell.json';
        file_put_contents($path, '{"words":[]}');
        chmod($path, 0000);

        try {
            $this->assertRuntimeExceptionContainsPath(
                static function () use ($path): void {
                    WordlistDocumentationConfigSync::writeCspellWords($path, ['word']);
                },
                $path,
                'CSpell config file is not readable',
            );
        } finally {
            chmod($path, 0644);
            unlink($path);
        }
    }

    /** @param callable(): mixed $action */
    private function assertRuntimeExceptionContainsPath(callable $action, string $path, string $message): void
    {
        try {
            $action();
            self::fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $exception) {
            self::assertSame(0, $exception->getCode());
            self::assertSame($message . ': ' . $path, $exception->getMessage());
        }
    }

    private function createProjectTree(string $wordlistContents, string $cspellContents): string
    {
        $root = $this->getTmpDir() . 'wordlist-sync/';
        mkdir($root . 'etc/base64', 0777, true);
        file_put_contents($root . 'etc/wordlist.txt', $wordlistContents);
        file_put_contents($root . 'etc/base64/cspell.json', $cspellContents);

        return $root;
    }
}
