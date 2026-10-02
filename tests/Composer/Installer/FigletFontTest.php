<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Makefiles\Composer\Installer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use WyriHaximus\Makefiles\Composer\Installer\FigletFont;
use WyriHaximus\Tests\Makefiles\TestCase;

final class FigletFontTest extends TestCase
{
    /** @return iterable<string, array{string, list<string>}> */
    public static function provideRenderCases(): iterable
    {
        yield 'empty string' => [
            '',
            [
                '',
                '',
                '',
                '',
            ],
        ];

        yield 'single character omits trailing space padding' => [
            'a',
            [
                '',
                '  _.',
                ' (_|',
                '',
            ],
        ];

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

        yield 'narrow glyphs with inter character spacing' => [
            'il',
            [
                '',
                ' o   |',
                ' |   |',
                '',
            ],
        ];

        yield 'repeated narrow glyphs omit trailing space padding' => [
            'ii',
            [
                '',
                ' o   o',
                ' |   |',
                '',
            ],
        ];

        yield 'adjacent glyphs insert single space between rows' => [
            'ab',
            [
                '',
                '  _.   |_',
                ' (_|   |_)',
                '',
            ],
        ];

        yield 'mixed wide glyphs preserve row width and spacing' => [
            '@W',
            [
                '   __',
                '  /  \\   \\    /',
                ' | (|/    \\/\\/',
                '  \\__',
            ],
        ];
    }

    #[Test]
    public function constructorIsPrivate(): void
    {
        $reflection  = new ReflectionClass(FigletFont::class);
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPrivate());
    }

    /** @param list<string> $expectedLines */
    #[Test]
    #[DataProvider('provideRenderCases')]
    public function render(string $text, array $expectedLines): void
    {
        self::assertSame($expectedLines, FigletFont::mini()->render($text));
    }
}
