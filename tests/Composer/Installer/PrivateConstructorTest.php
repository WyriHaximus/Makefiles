<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Makefiles\Composer\Installer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use WyriHaximus\Makefiles\Composer\Installer\MakefileGenerationContext;
use WyriHaximus\Makefiles\Composer\Installer\Requirements;
use WyriHaximus\Tests\Makefiles\TestCase;

use function basename;
use function class_exists;
use function glob;
use function in_array;

final class PrivateConstructorTest extends TestCase
{
    private const array EXCLUDED = [
        Requirements::class,
        MakefileGenerationContext::class,
    ];

    /** @return iterable<string, array{class-string}> */
    public static function provideStaticUtilityClasses(): iterable
    {
        $files = glob(__DIR__ . '/../../../src/Composer/Installer/*.php');
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            $class = 'WyriHaximus\\Makefiles\\Composer\\Installer\\' . basename($file, '.php');

            if (! class_exists($class) || in_array($class, self::EXCLUDED, true)) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            if (! $reflection->isFinal() || $reflection->getConstructor()?->isPrivate() !== true) {
                continue;
            }

            yield $class => [$class];
        }
    }

    /** @param class-string $class */
    #[Test]
    #[DataProvider('provideStaticUtilityClasses')]
    public function privateConstructorIsNotInstantiable(string $class): void
    {
        self::assertTrue(class_exists($class));

        $reflection  = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPrivate());

        $constructor->invoke($reflection->newInstanceWithoutConstructor());
    }
}
