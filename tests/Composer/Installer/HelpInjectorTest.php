<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Makefiles\Composer\Installer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionMethod;
use WyriHaximus\Makefiles\Composer\Installer\HelpInjector;
use WyriHaximus\Tests\Makefiles\TestCase;

use function mkdir;
use function str_ends_with;
use function strlen;
use function sys_get_temp_dir;

final class HelpInjectorTest extends TestCase
{
    private const string GOLDEN_MAKEFILE = <<<'MAKEFILE'
help(main)
help(migrations)
help(contrib)
zebra: ## Zebra task ####
alpha: ## Alpha task ####
migrations-run: ## Run migration ####
contrib-a: ## Contrib A ##*E*##
plain: ## Plain ##*A*##
hidden: ## Hidden ##U##
gamma: ## Hash#suffix ####
MAKEFILE;

    #[Test]
    public function injectReplacesHelpPlaceholdersWithGoldenOutput(): void
    {
        $root = sys_get_temp_dir();
        if (! str_ends_with($root, '/')) {
            $root .= '/';
        }

        $expected = <<<'EXPECTED'
@printf "\033[33mUsage:\033[0m\n"
	@printf "  make [target]\n"
	@printf "\n"
	@printf "\033[33mTargets:\033[0m\n"
	@printf "  \033[32m%-32s\033[0m %s\n" 'alpha' 'Alpha task'
	@printf "  \033[32m%-32s\033[0m %s\n" 'contrib-a' 'Contrib A'
	@printf "  \033[32m%-32s\033[0m %s\n" 'gamma' 'Hash'
	@printf "  \033[32m%-32s\033[0m %s\n" 'plain' 'Plain'
	@printf "  \033[32m%-32s\033[0m %s\n" 'zebra' 'Zebra task'
@printf "%s\n" ''
	@printf "%s\n" ' ._ _    o    _    ._    _.   _|_   o    _    ._     _'
	@printf "%s\n" ' | | |   |   (_|   |    (_|    |_   |   (_)   | |   _>'
	@printf "%s\n" '              _|'
	@printf "\n"
	@printf "\033[33mUsage:\033[0m\n"
	@printf "  make [target]\n"
	@printf "\n"
	@printf "\033[33mTargets:\033[0m\n"
	@printf "  \033[32m%-32s\033[0m %s\n" 'migrations-run' 'Run migration'
@printf "%s\n" ''
	@printf "%s\n" '  _    _    ._    _|_   ._   o   |_'
	@printf "%s\n" ' (_   (_)   | |    |_   |    |   |_)'
	@printf "%s\n" ''
	@printf "\n"
	@printf "\033[33mUsage:\033[0m\n"
	@printf "  make [target]\n"
	@printf "\n"
	@printf "\033[33mTargets:\033[0m\n"
	@printf "  \033[32m%-32s\033[0m %s\n" 'contrib-a' 'Contrib A'
zebra: ## Zebra task ####
alpha: ## Alpha task ####
migrations-run: ## Run migration ####
contrib-a: ## Contrib A ##*E*##
plain: ## Plain ##*A*##
hidden: ## Hidden ##U##
gamma: ## Hash#suffix ####
EXPECTED;

        self::assertSame($expected, HelpInjector::inject(self::GOLDEN_MAKEFILE, $root));
    }

    #[Test]
    public function injectListsPlainTargetInMainButNotContribSection(): void
    {
        $root = $this->getTmpDir() . 'help-contrib-filter/';
        mkdir($root);

        $input = <<<'MAKEFILE'
help(main)
help(contrib)
plain: ## Plain ##*A*##
contrib-only: ## Contrib only ##*E*##
MAKEFILE;

        $expected = <<<'EXPECTED'
@printf "\033[33mUsage:\033[0m\n"
	@printf "  make [target]\n"
	@printf "\n"
	@printf "\033[33mTargets:\033[0m\n"
	@printf "  \033[32m%-32s\033[0m %s\n" 'contrib-only' 'Contrib only'
	@printf "  \033[32m%-32s\033[0m %s\n" 'plain' 'Plain'
@printf "%s\n" ''
	@printf "%s\n" '  _    _    ._    _|_   ._   o   |_'
	@printf "%s\n" ' (_   (_)   | |    |_   |    |   |_)'
	@printf "%s\n" ''
	@printf "\n"
	@printf "\033[33mUsage:\033[0m\n"
	@printf "  make [target]\n"
	@printf "\n"
	@printf "\033[33mTargets:\033[0m\n"
	@printf "  \033[32m%-32s\033[0m %s\n" 'contrib-only' 'Contrib only'
plain: ## Plain ##*A*##
contrib-only: ## Contrib only ##*E*##
EXPECTED;

        self::assertSame($expected, HelpInjector::inject($input, $root));
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function provideBuildHelpLineCases(): iterable
    {
        yield 'stops at hash in suffix' => ['gamma', 'Hash#suffix ####', 'gamma: ## Hash'];
        yield 'uses full suffix without hash' => ['plain', 'Full description without hash ####', 'plain: ## Full description without hash'];
    }

    /** @return iterable<string, array{string, bool}> */
    public static function provideContribExecutorFlagCases(): iterable
    {
        yield 'executor flag present' => ['beta: ## Beta ##*E*##', true];
        yield 'executor flag absent' => ['plain: ## Plain ##*A*##', false];
        yield 'executor flag in multi letter group' => ['beta: ## Beta ##*AE*##', true];
        yield 'executor absent without e in captured flags' => ['beta: ## Beta ##*HC*##', false];
    }

    #[Test]
    public function sortHelpEntriesByTargetNameOrdersByTargetKey(): void
    {
        $method = new ReflectionMethod(HelpInjector::class, 'sortHelpEntriesByTargetName');
        $sorted = $method->invoke(null, [
            ['aa', 'aa: ## Z'],
            ['a', 'a: ## A'],
        ]);

        self::assertSame(
            [
                ['a', 'a: ## A'],
                ['aa', 'aa: ## Z'],
            ],
            $sorted,
        );
    }

    #[Test]
    public function sortHelpEntriesByTargetNameKeepsLastEntryPerTarget(): void
    {
        $method = new ReflectionMethod(HelpInjector::class, 'sortHelpEntriesByTargetName');

        self::assertSame(
            [['dup', 'dup: ## Second']],
            $method->invoke(null, [
                ['dup', 'dup: ## First'],
                ['dup', 'dup: ## Second'],
            ]),
        );
    }

    #[Test]
    public function helpSuffixSegmentEndUsesFullLengthWhenHashAbsent(): void
    {
        $method = new ReflectionMethod(HelpInjector::class, 'helpSuffixSegmentEnd');
        self::assertSame(strlen('no hash here'), $method->invoke(null, 'no hash here'));
    }

    #[Test]
    public function helpSuffixSegmentEndStopsAtFirstHash(): void
    {
        $method = new ReflectionMethod(HelpInjector::class, 'helpSuffixSegmentEnd');
        self::assertSame(4, $method->invoke(null, 'Hash#suffix ####'));
    }

    #[Test]
    public function formatHelpRecipeBuildsExactTargetLineForSingleEntry(): void
    {
        $method = new ReflectionMethod(HelpInjector::class, 'formatHelpRecipe');

        self::assertSame(
            '@printf "\033[33mUsage:\033[0m\n"'
            . "\n\t@printf \"  make [target]\\n\""
            . "\n\t@printf \"\\n\""
            . "\n\t@printf \"\\033[33mTargets:\\033[0m\\n\""
            . "\n\t@printf \"  \\033[32m%-32s\\033[0m %s\\n\" 'alpha' 'Alpha task'",
            $method->invoke(null, [['alpha', 'alpha: ## Alpha task']], []),
        );
    }

    #[Test]
    #[DataProvider('provideBuildHelpLineCases')]
    public function buildHelpLine(string $target, string $rawHelpSuffix, string $expected): void
    {
        $method = new ReflectionMethod(HelpInjector::class, 'buildHelpLine');
        self::assertSame($expected, $method->invoke(null, $target, $rawHelpSuffix));
    }

    #[Test]
    #[DataProvider('provideContribExecutorFlagCases')]
    public function helpLineHasContribExecutorFlag(string $fullLine, bool $expected): void
    {
        $method = new ReflectionMethod(HelpInjector::class, 'helpLineHasContribExecutorFlag');
        self::assertSame($expected, $method->invoke(null, $fullLine));
    }

    #[Test]
    public function executorFlagsIncludeEChecksCapturedFlagsNotFullMatch(): void
    {
        $method = new ReflectionMethod(HelpInjector::class, 'executorFlagsIncludeE');
        self::assertTrue($method->invoke(null, 'E'));
        self::assertTrue($method->invoke(null, 'AE'));
        self::assertFalse($method->invoke(null, '##*E*##'));
        self::assertFalse($method->invoke(null, 'HC'));
    }

    #[Test]
    public function shellQuoteEscapesApostropheInDescription(): void
    {
        $reflection = new ReflectionClass(HelpInjector::class);
        $method     = $reflection->getMethod('shellQuote');

        self::assertSame("'it'\\''s'", $method->invoke(null, "it's"));
    }

    #[Test]
    public function injectSortsMainHelpByTargetNameNotHelpLinePrefix(): void
    {
        $root = $this->getTmpDir() . 'help-sort/';
        mkdir($root);

        $input = <<<'MAKEFILE'
help(main)
aa: ## Z description ####
a: ## A description ####
MAKEFILE;

        $expected = <<<'EXPECTED'
@printf "\033[33mUsage:\033[0m\n"
	@printf "  make [target]\n"
	@printf "\n"
	@printf "\033[33mTargets:\033[0m\n"
	@printf "  \033[32m%-32s\033[0m %s\n" 'a' 'A description'
	@printf "  \033[32m%-32s\033[0m %s\n" 'aa' 'Z description'
aa: ## Z description ####
a: ## A description ####
EXPECTED;

        self::assertSame($expected, HelpInjector::inject($input, $root));
    }

    #[Test]
    public function injectKeepsSingleHelpEntryWhenDuplicateTargetLinesAppear(): void
    {
        $root = $this->getTmpDir() . 'help-dedupe/';
        mkdir($root);

        $input = <<<'MAKEFILE'
help(main)
dup: ## First ####
dup: ## Second ####
MAKEFILE;

        $expected = <<<'EXPECTED'
@printf "\033[33mUsage:\033[0m\n"
	@printf "  make [target]\n"
	@printf "\n"
	@printf "\033[33mTargets:\033[0m\n"
	@printf "  \033[32m%-32s\033[0m %s\n" 'dup' 'Second'
dup: ## First ####
dup: ## Second ####
EXPECTED;

        self::assertSame($expected, HelpInjector::inject($input, $root));
    }

    #[Test]
    public function injectUsesFullDescriptionWhenHelpTextHasNoHash(): void
    {
        $root = $this->getTmpDir() . 'help-no-hash/';
        mkdir($root);

        $input = <<<'MAKEFILE'
help(main)
plain: ## Full description without hash ####
MAKEFILE;

        $expected = <<<'EXPECTED'
@printf "\033[33mUsage:\033[0m\n"
	@printf "  make [target]\n"
	@printf "\n"
	@printf "\033[33mTargets:\033[0m\n"
	@printf "  \033[32m%-32s\033[0m %s\n" 'plain' 'Full description without hash'
plain: ## Full description without hash ####
EXPECTED;

        self::assertSame($expected, HelpInjector::inject($input, $root));
    }
}
