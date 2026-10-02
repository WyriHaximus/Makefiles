<?php

declare(strict_types=1);

namespace WyriHaximus\Makefiles\Documentation;

use JsonException;
use RuntimeException;

use function dirname;
use function explode;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function in_array;
use function is_array;
use function is_dir;
use function is_file;
use function is_readable;
use function is_string;
use function is_writable;
use function json_decode;
use function json_encode;
use function rtrim;
use function trim;

use const DIRECTORY_SEPARATOR;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

final class WordlistDocumentationConfigSync
{
    private const string WORDLIST_RELATIVE_PATH = 'etc' . DIRECTORY_SEPARATOR . 'wordlist.txt';

    private const string VALE_VOCAB_RELATIVE_PATH = 'etc' . DIRECTORY_SEPARATOR . 'base64' . DIRECTORY_SEPARATOR . 'vale-vocab.txt';

    private const string CSPELL_RELATIVE_PATH = 'etc' . DIRECTORY_SEPARATOR . 'base64' . DIRECTORY_SEPARATOR . 'cspell.json';

    private const int CSPELL_JSON_DECODE_DEPTH = 512;

    private function __construct()
    {
    }

    public static function sync(string $projectRoot): void
    {
        $root = rtrim($projectRoot, '/\\');

        $words = self::readWordlist($root . DIRECTORY_SEPARATOR . self::WORDLIST_RELATIVE_PATH);
        self::writeValeVocab($root . DIRECTORY_SEPARATOR . self::VALE_VOCAB_RELATIVE_PATH, $words);
        self::writeCspellWords($root . DIRECTORY_SEPARATOR . self::CSPELL_RELATIVE_PATH, $words);
    }

    /** @return list<string> */
    public static function readWordlist(string $wordlistPath): array
    {
        if (! is_file($wordlistPath)) {
            throw new RuntimeException('Wordlist file is missing: ' . $wordlistPath);
        }

        if (! is_readable($wordlistPath)) {
            throw new RuntimeException('Wordlist file is not readable: ' . $wordlistPath);
        }

        $content = file_get_contents($wordlistPath);
        if (! is_string($content)) {
            throw new RuntimeException('Wordlist file could not be read: ' . $wordlistPath);
        }

        $words = [];
        foreach (explode("\n", $content) as $line) {
            $word = trim($line);
            if ($word === '') {
                continue;
            }

            if (in_array($word, $words, true)) {
                continue;
            }

            $words[] = $word;
        }

        return $words;
    }

    /** @param list<string> $words */
    public static function writeValeVocab(string $valeVocabPath, array $words): void
    {
        if (is_dir($valeVocabPath) || ! self::isWritablePath($valeVocabPath)) {
            throw new RuntimeException('Failed to write Vale vocabulary file: ' . $valeVocabPath);
        }

        file_put_contents($valeVocabPath, implode("\n", $words) . "\n");
    }

    /** @param list<string> $words */
    public static function writeCspellWords(string $cspellConfigPath, array $words): void
    {
        if (! is_file($cspellConfigPath)) {
            throw new RuntimeException('CSpell config file is missing: ' . $cspellConfigPath);
        }

        if (! is_readable($cspellConfigPath)) {
            throw new RuntimeException('CSpell config file is not readable: ' . $cspellConfigPath);
        }

        $content = file_get_contents($cspellConfigPath);
        if (! is_string($content)) {
            throw new RuntimeException('CSpell config file could not be read: ' . $cspellConfigPath);
        }

        try {
            $config = json_decode($content, true, self::CSPELL_JSON_DECODE_DEPTH, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('CSpell config file is not valid JSON: ' . $cspellConfigPath, 0, $exception);
        }

        if (! is_array($config)) {
            throw new RuntimeException('CSpell config file must decode to an object: ' . $cspellConfigPath);
        }

        $config['words'] = $words;

        $encoded = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        if (! self::isWritablePath($cspellConfigPath)) {
            throw new RuntimeException('Failed to write CSpell config file: ' . $cspellConfigPath);
        }

        file_put_contents($cspellConfigPath, $encoded . "\n");
    }

    private static function isWritablePath(string $path): bool
    {
        if (is_file($path)) {
            return is_writable($path);
        }

        $directory = dirname($path);

        return is_dir($directory) && is_writable($directory);
    }
}
