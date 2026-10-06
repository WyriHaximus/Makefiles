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
use function define;
use function explode;
use function file_get_contents;
use function file_put_contents;
use function get_declared_classes;
use function json_decode;
use function mkdir;
use function rtrim;
use function str_contains;
use function str_starts_with;
use function strrpos;
use function substr;
use function trim;
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
            "alpha\n\nbeta\nalpha\n one\n\none\ntwo \n",
            '{"version":"0.2","language":"en","words":["old"]}',
        );

        WordlistDocumentationConfigSync::sync($root);

        self::assertSame("alpha\nbeta\none\ntwo\n", file_get_contents($root . 'etc/base64/vale-vocab.txt'));

        $cspellPath    = $root . 'etc/base64/cspell.json';
        $cspellEncoded = (string) file_get_contents($cspellPath);
        self::assertStringEndsWith("\n", $cspellEncoded);
        self::assertStringContainsString("\n    \"version\": \"0.2\",\n", $cspellEncoded);

        $cspell = json_decode($cspellEncoded, true);
        self::assertIsArray($cspell);
        self::assertSame('0.2', $cspell['version']);
        self::assertSame(['alpha', 'beta', 'one', 'two'], $cspell['words']);
    }

    #[Test]
    public function syncPreservesUnescapedSlashesInExistingCspellConfig(): void
    {
        $root = $this->createProjectTree(
            "term\n",
            '{"version":"0.2","ignorePaths":["https://example.com/foo/bar"],"words":["old"]}',
        );

        WordlistDocumentationConfigSync::sync($root);

        $encoded = (string) file_get_contents($root . 'etc/base64/cspell.json');
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
    public function enrichQaWordlistsWithPhpSymbolsMergesIntoCspellAndValeVocab(): void
    {
        $root = $this->getTmpDir() . 'enrich-qa-wordlists/';
        mkdir($root . 'etc/qa', 0777, true);
        file_put_contents($root . 'etc/qa/cspell.json', '{"words":["manual"]}');
        file_put_contents($root . 'etc/qa/vale-vocab.txt', "manual\n");

        WordlistDocumentationConfigSync::enrichQaWordlistsWithPhpSymbols($root);

        $cspell = json_decode((string) file_get_contents($root . 'etc/qa/cspell.json'), true);
        self::assertIsArray($cspell);
        self::assertIsArray($cspell['words']);
        /** @var list<string> $cspellWords */
        $cspellWords = $cspell['words'];
        self::assertContains('manual', $cspellWords);
        self::assertContains('WordlistDocumentationConfigSync', $cspellWords);

        $vale = (string) file_get_contents($root . 'etc/qa/vale-vocab.txt');
        self::assertStringContainsString("manual\n", $vale);
        self::assertStringContainsString("WordlistDocumentationConfigSync\n", $vale);
    }

    #[Test]
    public function enrichQaWordlistsWithPhpSymbolsUpdatesCspellWhenValeVocabIsMissing(): void
    {
        $root = $this->getTmpDir() . 'enrich-cspell-only/';
        mkdir($root . 'etc/qa', 0777, true);
        file_put_contents($root . 'etc/qa/cspell.json', '{"words":["manual",1,"","manual"]}');

        WordlistDocumentationConfigSync::enrichQaWordlistsWithPhpSymbols($root);

        $cspell = json_decode((string) file_get_contents($root . 'etc/qa/cspell.json'), true);
        self::assertIsArray($cspell);
        self::assertIsArray($cspell['words']);
        /** @var list<string> $cspellWords */
        $cspellWords = $cspell['words'];
        self::assertContains('manual', $cspellWords);
        self::assertContains('WordlistDocumentationConfigSync', $cspellWords);
    }

    #[Test]
    public function enrichQaWordlistsWithPhpSymbolsOmitsVolatileComposerAutoloadClasses(): void
    {
        $staleAutoloader = 'ComposerAutoloaderInit539be914951063f7d5090af0796f6e6e';
        $staleStatic     = 'ComposerStaticInit539be914951063f7d5090af0796f6e6e';

        $root = $this->getTmpDir() . 'enrich-qa-no-composer-autoload/';
        mkdir($root . 'etc/qa', 0777, true);
        file_put_contents(
            $root . 'etc/qa/cspell.json',
            '{"words":["manual","' . $staleAutoloader . '","' . $staleStatic . '"]}',
        );
        file_put_contents($root . 'etc/qa/vale-vocab.txt', "manual\n" . $staleAutoloader . "\n" . $staleStatic . "\n");

        WordlistDocumentationConfigSync::enrichQaWordlistsWithPhpSymbols($root);

        $cspell = json_decode((string) file_get_contents($root . 'etc/qa/cspell.json'), true);
        self::assertIsArray($cspell);
        self::assertIsArray($cspell['words']);
        /** @var list<string> $cspellWords */
        $cspellWords = $cspell['words'];
        self::assertContains('manual', $cspellWords);
        self::assertNotContains($staleAutoloader, $cspellWords);
        self::assertNotContains($staleStatic, $cspellWords);

        foreach (get_declared_classes() as $class) {
            $segment = str_contains($class, '\\') ? substr($class, (int) strrpos($class, '\\') + 1) : $class;
            if (! str_starts_with($segment, 'ComposerAutoloaderInit') && ! str_starts_with($segment, 'ComposerStaticInit')) {
                continue;
            }

            self::assertNotContains($segment, $cspellWords);
        }

        $valeLines = explode("\n", trim((string) file_get_contents($root . 'etc/qa/vale-vocab.txt')));
        self::assertContains('manual', $valeLines);
        self::assertNotContains($staleAutoloader, $valeLines);
        self::assertNotContains($staleStatic, $valeLines);
    }

    #[Test]
    public function enrichQaWordlistsWithPhpSymbolsMergesComposerVendorAndPackageNames(): void
    {
        $root = $this->getTmpDir() . 'enrich-qa-composer-names/';
        mkdir($root . 'etc/qa', 0777, true);
        file_put_contents($root . 'etc/qa/cspell.json', '{"words":["manual"]}');
        file_put_contents($root . 'etc/qa/vale-vocab.txt', "manual\n");
        file_put_contents(
            $root . 'composer.lock',
            '{"packages":[{"name":"acme-corp/super-widget"}],"packages-dev":[{"name":"dev-vendor/dev-package"}]}',
        );
        file_put_contents($root . 'composer.json', '{"name":"root-vendor/root-package"}');

        WordlistDocumentationConfigSync::enrichQaWordlistsWithPhpSymbols($root);

        $cspell = json_decode((string) file_get_contents($root . 'etc/qa/cspell.json'), true);
        self::assertIsArray($cspell);
        self::assertIsArray($cspell['words']);
        /** @var list<string> $cspellWords */
        $cspellWords = $cspell['words'];
        self::assertContains('manual', $cspellWords);
        self::assertContains('acme-corp', $cspellWords);
        self::assertContains('super-widget', $cspellWords);
        self::assertContains('dev-vendor', $cspellWords);
        self::assertContains('dev-package', $cspellWords);
        self::assertContains('root-vendor', $cspellWords);
        self::assertContains('root-package', $cspellWords);

        $vale = (string) file_get_contents($root . 'etc/qa/vale-vocab.txt');
        self::assertStringContainsString("acme-corp\n", $vale);
        self::assertStringContainsString("super-widget\n", $vale);
    }

    #[Test]
    public function enrichQaWordlistsWithPhpSymbolsSkipsMalformedComposerMetadata(): void
    {
        $root = $this->getTmpDir() . 'enrich-qa-malformed-composer/';
        mkdir($root . 'etc/qa', 0777, true);
        file_put_contents($root . 'etc/qa/cspell.json', '{"words":[]}');
        file_put_contents(
            $root . 'composer.lock',
            '{"packages":"skip","packages-dev":["skip",{"name":""},{"name":1},{"name":"ok-vendor/ok-package"}]}',
        );
        file_put_contents($root . 'composer.json', '{');

        WordlistDocumentationConfigSync::enrichQaWordlistsWithPhpSymbols($root);

        $cspell = json_decode((string) file_get_contents($root . 'etc/qa/cspell.json'), true);
        self::assertIsArray($cspell);
        self::assertIsArray($cspell['words']);
        /** @var list<string> $cspellWords */
        $cspellWords = $cspell['words'];
        self::assertContains('ok-vendor', $cspellWords);
        self::assertContains('ok-package', $cspellWords);
        self::assertNotContains('skip', $cspellWords);
    }

    #[Test]
    public function enrichQaWordlistsWithPhpSymbolsIgnoresInvalidComposerLockJson(): void
    {
        $root = $this->getTmpDir() . 'enrich-qa-bad-composer-lock/';
        mkdir($root . 'etc/qa', 0777, true);
        file_put_contents($root . 'etc/qa/cspell.json', '{"words":["manual"]}');
        file_put_contents($root . 'composer.lock', '{');

        WordlistDocumentationConfigSync::enrichQaWordlistsWithPhpSymbols($root);

        $cspell = json_decode((string) file_get_contents($root . 'etc/qa/cspell.json'), true);
        self::assertIsArray($cspell);
        self::assertIsArray($cspell['words']);
        /** @var list<string> $cspellWords */
        $cspellWords = $cspell['words'];
        self::assertContains('manual', $cspellWords);
        self::assertContains('WordlistDocumentationConfigSync', $cspellWords);
        self::assertNotContains('acme-corp', $cspellWords);
    }

    /** @param list<string> $expected */
    #[Test]
    #[DataProvider('provideComposerPackageNameSegments')]
    public function composerPackageNameSegmentsSplitsVendorAndPackage(string $packageName, array $expected): void
    {
        self::assertSame(
            $expected,
            $this->invokePrivateStatic('composerPackageNameSegments', [$packageName]),
        );
    }

    /** @return iterable<string, array{0: string, 1: list<string>}> */
    public static function provideComposerPackageNameSegments(): iterable
    {
        yield 'vendor and package' => ['acme/widget', ['acme', 'widget']];
        yield 'hyphenated segments' => ['acme-corp/super-widget', ['acme-corp', 'super-widget']];
        yield 'vendor only' => ['solo-vendor', ['solo-vendor']];
        yield 'trims whitespace' => [' spaced / name ', ['spaced', 'name']];
    }

    #[Test]
    public function enrichQaWordlistsWithPhpSymbolsIncludesUserDefinedConstantSegments(): void
    {
        define('WyriHaximus\\Tests\\Makefiles\\Documentation\\COVERAGE_CONST', 1);

        $root = $this->getTmpDir() . 'enrich-qa-const/';
        mkdir($root . 'etc/qa', 0777, true);
        file_put_contents($root . 'etc/qa/cspell.json', '{"words":[]}');
        file_put_contents($root . 'etc/qa/vale-vocab.txt', "\n");

        WordlistDocumentationConfigSync::enrichQaWordlistsWithPhpSymbols($root);

        $cspell = json_decode((string) file_get_contents($root . 'etc/qa/cspell.json'), true);
        self::assertIsArray($cspell);
        self::assertIsArray($cspell['words']);
        /** @var list<string> $cspellWords */
        $cspellWords = $cspell['words'];
        self::assertContains('COVERAGE_CONST', $cspellWords);
    }

    #[Test]
    public function addWordsToQaWordlistsMergesSortedIntoCspellAndValeVocab(): void
    {
        $root = $this->getTmpDir() . 'add-qa-words/';
        mkdir($root . 'etc/qa', 0777, true);
        file_put_contents($root . 'etc/qa/cspell.json', '{"words":["manual"]}');
        file_put_contents($root . 'etc/qa/vale-vocab.txt', "manual\n");

        WordlistDocumentationConfigSync::addWordsToQaWordlists($root, ' zebra', 'Acme', 'Acme', '');

        $cspell = json_decode((string) file_get_contents($root . 'etc/qa/cspell.json'), true);
        self::assertIsArray($cspell);
        self::assertSame(['Acme', 'manual', 'zebra'], $cspell['words']);

        $valeLines = explode("\n", trim((string) file_get_contents($root . 'etc/qa/vale-vocab.txt')));
        self::assertSame(['Acme', 'manual', 'zebra'], $valeLines);
    }

    #[Test]
    public function addWordsToQaWordlistsIsNoOpWhenNoWordsArePassed(): void
    {
        $root = $this->getTmpDir() . 'add-qa-words-none/';
        mkdir($root . 'etc/qa', 0777, true);
        file_put_contents($root . 'etc/qa/cspell.json', '{"words":["manual"]}');
        file_put_contents($root . 'etc/qa/vale-vocab.txt', "manual\n");

        WordlistDocumentationConfigSync::addWordsToQaWordlists($root);

        self::assertSame('{"words":["manual"]}', trim((string) file_get_contents($root . 'etc/qa/cspell.json')));
    }

    #[Test]
    public function addWordsToQaWordlistsIsNoOpWhenEveryArgumentIsEmpty(): void
    {
        $root = $this->getTmpDir() . 'add-qa-words-empty/';
        mkdir($root . 'etc/qa', 0777, true);
        file_put_contents($root . 'etc/qa/cspell.json', '{"words":["manual"]}');
        file_put_contents($root . 'etc/qa/vale-vocab.txt', "manual\n");

        WordlistDocumentationConfigSync::addWordsToQaWordlists($root, '', '   ');

        self::assertSame('{"words":["manual"]}', trim((string) file_get_contents($root . 'etc/qa/cspell.json')));
        self::assertSame("manual\n", file_get_contents($root . 'etc/qa/vale-vocab.txt'));
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

        $this->invokePrivateStatic('writeValeVocab', [$path, ['word']]);

        self::assertSame("word\n", file_get_contents($path));
    }

    #[Test]
    public function writeValeVocabThrowsWhenParentDirectoryDoesNotExist(): void
    {
        $path = $this->getTmpDir() . 'missing-parent/vale-vocab.txt';

        $this->assertRuntimeExceptionContainsPath(
            fn (): mixed => $this->invokePrivateStatic('writeValeVocab', [$path, ['word']]),
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
            fn (): mixed => $this->invokePrivateStatic('writeValeVocab', [$path, ['word']]),
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
            fn (): mixed => $this->invokePrivateStatic('writeValeVocab', [$directory, ['word']]),
            $directory,
            'Failed to write Vale vocabulary file',
        );
    }

    #[Test]
    public function readWordlistThrowsWhenFileIsMissing(): void
    {
        $path = $this->getTmpDir() . 'missing.txt';

        $this->assertRuntimeExceptionContainsPath(
            fn (): mixed => $this->invokePrivateStatic('readWordlist', [$path]),
            $path,
            'Wordlist file is missing',
        );
    }

    #[Test]
    public function writeCspellWordsThrowsWhenConfigIsMissing(): void
    {
        $path = $this->getTmpDir() . 'missing.json';

        $this->assertRuntimeExceptionContainsPath(
            fn (): mixed => $this->invokePrivateStatic('writeCspellWords', [$path, ['word']]),
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
            $this->invokePrivateStatic('writeCspellWords', [$path, ['word']]);
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
                fn (): mixed => $this->invokePrivateStatic('readWordlist', [$path]),
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
        if (! ProjectSandbox::canSimulateUnreadableFiles()) {
            self::markTestSkipped('File permission tests cannot run on Windows or as root.');
        }

        $path = $this->getTmpDir() . 'read-only-cspell.json';
        file_put_contents($path, '{"words":[]}');
        chmod($path, 0444);

        try {
            $this->assertRuntimeExceptionContainsPath(
                fn (): mixed => $this->invokePrivateStatic('writeCspellWords', [$path, ['word']]),
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
                fn (): mixed => $this->invokePrivateStatic('writeCspellWords', [$path, ['word']]),
                $path,
                'CSpell config file is not readable',
            );
        } finally {
            chmod($path, 0644);
            unlink($path);
        }
    }

    /** @param list<mixed> $arguments */
    private function invokePrivateStatic(string $method, array $arguments = []): mixed
    {
        $reflectionMethod = new ReflectionClass(WordlistDocumentationConfigSync::class)->getMethod($method);

        return $reflectionMethod->invokeArgs(null, $arguments);
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
