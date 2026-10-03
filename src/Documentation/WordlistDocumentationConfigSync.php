<?php

declare(strict_types=1);

namespace WyriHaximus\Makefiles\Documentation;

use JsonException;
use RuntimeException;

use function array_filter;
use function array_keys;
use function array_push;
use function array_unique;
use function array_values;
use function dirname;
use function explode;
use function file_get_contents;
use function file_put_contents;
use function get_declared_classes;
use function get_declared_interfaces;
use function get_declared_traits;
use function get_defined_constants;
use function get_defined_functions;
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
use function ltrim;
use function rtrim;
use function sort;
use function trim;

use const DIRECTORY_SEPARATOR;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * Keeps documentation spell-check wordlists aligned.
 *
 * Public API (Makefile migrations and documented call sites only):
 * {@see sync()}, {@see enrichQaWordlistsWithPhpSymbols()}, {@see addWordsToQaWordlists()}.
 */
final class WordlistDocumentationConfigSync
{
    private const string WORDLIST_RELATIVE_PATH = 'etc' . DIRECTORY_SEPARATOR . 'wordlist.txt';

    private const string VALE_VOCAB_RELATIVE_PATH = 'etc' . DIRECTORY_SEPARATOR . 'base64' . DIRECTORY_SEPARATOR . 'vale-vocab.txt';

    private const string CSPELL_RELATIVE_PATH = 'etc' . DIRECTORY_SEPARATOR . 'base64' . DIRECTORY_SEPARATOR . 'cspell.json';

    private const string QA_CSPELL_RELATIVE_PATH = 'etc' . DIRECTORY_SEPARATOR . 'qa' . DIRECTORY_SEPARATOR . 'cspell.json';

    private const string QA_VALE_VOCAB_RELATIVE_PATH = 'etc' . DIRECTORY_SEPARATOR . 'qa' . DIRECTORY_SEPARATOR . 'vale-vocab.txt';

    private const int CSPELL_JSON_DECODE_DEPTH = 512;

    private function __construct()
    {
    }

    /** Copy `etc/wordlist.txt` into `etc/base64/cspell.json` and `etc/base64/vale-vocab.txt`. */
    public static function sync(string $projectRoot): void
    {
        $root = rtrim($projectRoot, '/\\');

        $words = self::readWordlist($root . DIRECTORY_SEPARATOR . self::WORDLIST_RELATIVE_PATH);
        self::writeValeVocab($root . DIRECTORY_SEPARATOR . self::VALE_VOCAB_RELATIVE_PATH, $words);
        self::writeCspellWords($root . DIRECTORY_SEPARATOR . self::CSPELL_RELATIVE_PATH, $words);
    }

    /**
     * Merge declared PHP symbols into `etc/qa/cspell.json` and `etc/qa/vale-vocab.txt`.
     * Runs from `migrations-docs-enrich-qa-wordlists-with-php-symbols` on install and update.
     */
    public static function enrichQaWordlistsWithPhpSymbols(string $projectRoot): void
    {
        self::mergeAdditionalWordsIntoQaWordlists(rtrim($projectRoot, '/\\'), self::phpSymbolWords());
    }

    /**
     * Merge custom spellings into `etc/qa/cspell.json` and `etc/qa/vale-vocab.txt`.
     * Duplicate and empty strings are ignored; the `words` list and Vale vocab stay sorted.
     */
    public static function addWordsToQaWordlists(string $projectRoot, string ...$words): void
    {
        $trimmed = [];

        foreach ($words as $word) {
            $word = trim($word);
            if ($word === '') {
                continue;
            }

            $trimmed[] = $word;
        }

        if ($trimmed === []) {
            return;
        }

        self::mergeAdditionalWordsIntoQaWordlists(rtrim($projectRoot, '/\\'), self::mergeSortedUniqueWords($trimmed));
    }

    /** @param list<string> $additionalWords */
    private static function mergeAdditionalWordsIntoQaWordlists(string $root, array $additionalWords): void
    {
        $cspellPath = $root . DIRECTORY_SEPARATOR . self::QA_CSPELL_RELATIVE_PATH;
        if (is_file($cspellPath)) {
            $config        = self::readCspellConfig($cspellPath);
            $existingWords = [];
            if (is_array($config['words'] ?? null)) {
                /** @var list<mixed> $rawWords */
                $rawWords      = $config['words'];
                $existingWords = self::stringListFromMixedList($rawWords);
            }

            $config['words'] = self::mergeSortedUniqueWords($existingWords, $additionalWords);
            self::writeCspellConfig($cspellPath, $config);
        }

        $valeVocabPath = $root . DIRECTORY_SEPARATOR . self::QA_VALE_VOCAB_RELATIVE_PATH;
        if (! is_file($valeVocabPath)) {
            return;
        }

        self::writeValeVocab(
            $valeVocabPath,
            self::mergeSortedUniqueWords(self::readWordlist($valeVocabPath), $additionalWords),
        );
    }

    /** @return list<string> */
    private static function phpSymbolWords(): array
    {
        $words = [];

        $definedConstants = get_defined_constants(true);
        if (is_array($definedConstants['user'] ?? null)) {
            foreach (array_keys($definedConstants['user']) as $constant) {
                array_push($words, ...self::symbolNameSegments($constant));
            }
        }

        foreach (get_defined_functions() as $functions) {
            foreach ($functions as $function) {
                array_push($words, ...self::symbolNameSegments($function));
            }
        }

        foreach (get_declared_interfaces() as $interface) {
            array_push($words, ...self::symbolNameSegments($interface));
        }

        foreach (get_declared_traits() as $trait) {
            array_push($words, ...self::symbolNameSegments($trait));
        }

        foreach (get_declared_classes() as $class) {
            array_push($words, ...self::symbolNameSegments($class));
        }

        $words = array_unique($words);
        sort($words);

        return $words;
    }

    /**
     * @param list<string> $wordLists
     *
     * @return list<string>
     */
    private static function mergeSortedUniqueWords(array ...$wordLists): array
    {
        $words = [];

        foreach ($wordLists as $wordList) {
            foreach ($wordList as $word) {
                if ($word === '' || in_array($word, $words, true)) {
                    continue;
                }

                $words[] = $word;
            }
        }

        sort($words);

        return $words;
    }

    /** @return list<string> */
    private static function readWordlist(string $wordlistPath): array
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
            if ($word === '' || in_array($word, $words, true)) {
                continue;
            }

            $words[] = $word;
        }

        $words = array_unique($words);
        sort($words);

        return $words;
    }

    /** @param list<string> $words */
    private static function writeValeVocab(string $valeVocabPath, array $words): void
    {
        if (is_dir($valeVocabPath) || ! self::isWritablePath($valeVocabPath)) {
            throw new RuntimeException('Failed to write Vale vocabulary file: ' . $valeVocabPath);
        }

        file_put_contents($valeVocabPath, implode("\n", $words) . "\n");
    }

    /** @param list<string> $words */
    private static function writeCspellWords(string $cspellConfigPath, array $words): void
    {
        if (! is_file($cspellConfigPath)) {
            throw new RuntimeException('CSpell config file is missing: ' . $cspellConfigPath);
        }

        if (! is_readable($cspellConfigPath)) {
            throw new RuntimeException('CSpell config file is not readable: ' . $cspellConfigPath);
        }

        $config          = self::readCspellConfig($cspellConfigPath);
        $config['words'] = $words;
        self::writeCspellConfig($cspellConfigPath, $config);
    }

    /**
     * @return array<string, mixed>
     * @phpstan-return array<string, mixed>
     */
    private static function readCspellConfig(string $cspellConfigPath): array
    {
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

        /** @var array<string, mixed> $typedConfig */
        $typedConfig = $config;

        return $typedConfig;
    }

    /** @param array<string, mixed> $config */
    private static function writeCspellConfig(string $cspellConfigPath, array $config): void
    {
        $encoded = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        if (! self::isWritablePath($cspellConfigPath)) {
            throw new RuntimeException('Failed to write CSpell config file: ' . $cspellConfigPath);
        }

        file_put_contents($cspellConfigPath, $encoded . "\n");
    }

    /**
     * @param list<mixed> $values
     *
     * @return list<string>
     */
    private static function stringListFromMixedList(array $values): array
    {
        $words = [];

        foreach ($values as $value) {
            if (! is_string($value)) {
                continue;
            }

            $word = trim($value);
            if ($word === '' || in_array($word, $words, true)) {
                continue;
            }

            $words[] = $word;
        }

        return $words;
    }

    /** @return list<string> */
    private static function symbolNameSegments(string $symbolName): array
    {
        return array_values(array_filter(
            explode('\\', ltrim($symbolName, '\\')),
            static fn (string $chunk): bool => $chunk !== '',
        ));
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
