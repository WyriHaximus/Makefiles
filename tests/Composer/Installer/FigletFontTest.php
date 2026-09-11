<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Makefiles\Composer\Installer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\Makefiles\Composer\Installer\FigletFont;
use WyriHaximus\Tests\Makefiles\TestCase;

final class FigletFontTest extends TestCase
{
    /** @return iterable<string, array{string, list<string>}> */
    public static function provideRenderCases(): iterable
    {
        yield 'single word' => [
            'test',
            [
                '',
                ' _|_    _     _   _|_',
                '  |_   (/_   _>    |_',
                '',
            ],
        ];

        yield 'unknown character falls back to question mark' => [
            'a?b',
            [
                '       _',
                '  _.    )   |_',
                ' (_|   o    |_)',
                '',
            ],
        ];

        yield 'package name' => [
            'wyrihaximus/makefiles',
            [
                '                                                                                              _',
                '             ._   o   |_     _.        o   ._ _           _    /   ._ _     _.   |     _    _|_   o   |    _     _',
                ' \\/\\/   \\/   |    |   | |   (_|   ><   |   | | |   |_|   _>   /    | | |   (_|   |<   (/_    |    |   |   (/_   _>',
                '        /',
            ],
        ];

        yield 'migrations banner' => [
            'migrations',
            [
                '',
                ' ._ _    o    _    ._    _.   _|_   o    _    ._     _',
                ' | | |   |   (_|   |    (_|    |_   |   (_)   | |   _>',
                '              _|',
            ],
        ];

        yield 'contrib banner' => [
            'contrib',
            [
                '',
                '  _    _    ._    _|_   ._   o   |_',
                ' (_   (_)   | |    |_   |    |   |_)',
                '',
            ],
        ];
    }

    /** @param list<string> $expectedLines */
    #[Test]
    #[DataProvider('provideRenderCases')]
    public function render(string $text, array $expectedLines): void
    {
        self::assertSame($expectedLines, FigletFont::mini()->render($text));
    }
}
