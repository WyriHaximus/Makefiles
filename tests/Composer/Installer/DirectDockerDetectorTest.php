<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Makefiles\Composer\Installer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use RuntimeException;
use WyriHaximus\Makefiles\Composer\Installer\DirectDockerDetector;
use WyriHaximus\Tests\Makefiles\TestCase;

final class DirectDockerDetectorTest extends TestCase
{
    #[Test]
    #[DataProvider('provideTargetUsesDockerCases')]
    public function targetUsesDocker(string $makefileContents, string $target, bool $expected): void
    {
        self::assertSame($expected, DirectDockerDetector::targetUsesDocker($makefileContents, $target));
    }

    /** @return iterable<string, array{string, string, bool}> */
    public static function provideTargetUsesDockerCases(): iterable
    {
        yield 'literal docker in recipe' => [
            "terraform-fmt: ## fmt ##*I*##\n\tdocker run --rm hashicorp/terraform:1.14.8 fmt\n",
            'terraform-fmt',
            true,
        ];

        yield 'project docker wrapper variable' => [
            "TERRAFORM=docker run -i --network=host hashicorp/terraform:1.14.8\n\nterraform-fmt: ## fmt ##*I*##\n\t\$(TERRAFORM) -chdir=./.terraform/dev fmt\n",
            'terraform-fmt',
            true,
        ];

        yield 'project docker wrapper variable brace syntax' => [
            "TERRAFORM=docker run -i hashicorp/terraform:1.14.8\n\nterraform-fmt: ## fmt ##*I*##\n\t\${TERRAFORM} -chdir=./.terraform/dev fmt\n",
            'terraform-fmt',
            true,
        ];

        yield 'documentation markdownlint shared docker wrapper and image' => [
            "DOCKER_RUN_DOCUMENTATION=docker run --rm -i image:tag\nIMAGE_MARKDOWNLINT := markdown:tag\n\ndocumentation-markdownlint: ## lint ##*K*##\n\t\$(DOCKER_RUN_DOCUMENTATION) \$(IMAGE_MARKDOWNLINT) --config etc/qa/documentation.markdownlint-cli2.yaml\n",
            'documentation-markdownlint',
            true,
        ];

        yield 'documentation links shared docker wrapper and image' => [
            "DOCKER_RUN_DOCUMENTATION=docker run --rm -i image:tag\nIMAGE_LYCHEE := lychee:tag\n\ndocumentation-links: ## links ##*K*##\n\t\$(DOCKER_RUN_DOCUMENTATION) \$(IMAGE_LYCHEE) --config etc/qa/lychee.toml .\n",
            'documentation-links',
            true,
        ];

        yield 'documentation typos shared docker wrapper and image' => [
            "DOCKER_RUN_DOCUMENTATION=docker run --rm -i image:tag\nIMAGE_CSPELL := cspell:tag\n\ndocumentation-typos: ## typos ##*K*##\n\t\$(DOCKER_RUN_DOCUMENTATION) \$(IMAGE_CSPELL) --config etc/qa/cspell.json .\n",
            'documentation-typos',
            true,
        ];

        yield 'documentation vale shared docker wrapper and image' => [
            "DOCKER_RUN_DOCUMENTATION=docker run --rm -i image:tag\nIMAGE_VALE := vale:tag\n\ndocumentation-vale: ## vale ##*K*##\n\t\$(DOCKER_RUN_DOCUMENTATION) \$(IMAGE_VALE) --config etc/qa/vale.ini .\n",
            'documentation-vale',
            true,
        ];

        yield 'documentation qa aggregate prerequisite targets use docker' => [
            "DOCKER_RUN_DOCUMENTATION=docker run --rm -i image:tag\n\ndocumentation-markdownlint: ## lint ##*K*##\n\t\$(DOCKER_RUN_DOCUMENTATION) markdown .\n\ndocumentation-qa: documentation-markdownlint ## qa ##*E*##\n",
            'documentation-qa',
            true,
        ];

        yield 'docker on second recipe line' => [
            "two-step: ## x ##\n\techo local\n\tdocker run --rm image cmd\n",
            'two-step',
            true,
        ];

        yield 'embedded tab docker in echo line is not direct docker' => [
            "misleading: ## x ##\n\techo \"\\tdocker fake\"\n\tphp bin/local.php\n",
            'misleading',
            false,
        ];

        yield 'double tab before docker is not treated as direct docker recipe line' => [
            "double-tab: ## x ##\n\t\tdocker run --rm image cmd\n",
            'double-tab',
            false,
        ];

        yield 'regex special target names resolve the intended prerequisite recipe' => [
            <<<'MAKEFILE'
myXtarget: ## decoy ##
	echo local
my.target: ## real ##
	docker run --rm image cmd
parent: my.target ## p ##*E*##
MAKEFILE,
            'parent',
            true,
        ];

        yield 'docker wrapper value must start with docker after trim' => [
            "MYTOOL=x docker run --rm image\n\nfmt: ## x ##\n\t\$(MYTOOL) cmd\n",
            'fmt',
            false,
        ];

        yield 'docker wrapper assignment may use leading space before docker' => [
            "MYTOOL= docker run --rm image\n\nfmt: ## x ##\n\t\$(MYTOOL) cmd\n",
            'fmt',
            true,
        ];

        yield 'second custom docker wrapper variable is detected' => [
            <<<'MAKEFILE'
TOOLA=docker run --rm image-a
TOOLB=docker run --rm image-b
fmt-b: ## fmt ##*I*##
	$(TOOLB) fmt
MAKEFILE,
            'fmt-b',
            true,
        ];

        yield 'multiple make sub-targets in one recipe' => [
            <<<'MAKEFILE'
aggregate: ## x ##*I*##
	$(MAKE) step-a $(MAKE) step-b
step-a: ####
	echo local
step-b: ####
	docker run --rm image cmd
MAKEFILE,
            'aggregate',
            true,
        ];

        yield 'prerequisite chain finds docker in later prerequisite' => [
            <<<'MAKEFILE'
child: ## c ##*I*##
	docker run --rm image
parent: no-docker child ## p ##*E*##
no-docker: ## n ##*I*##
	echo local
MAKEFILE,
            'parent',
            true,
        ];

        yield 'framework docker run variable is ignored' => [
            "DOCKER_RUN:=docker run --rm ghcr.io/example/php:8.4-dev\n\ncomposer-normalize: ## normalize ##*I*##\n\t\$(DOCKER_RUN) composer normalize\n",
            'composer-normalize',
            false,
        ];

        yield 'make sub-target recursion finds docker' => [
            "terraform-fmt: ## fmt ##*I*##\n\t\$(MAKE) terraform-fmt-raw\n\nterraform-fmt-raw: ####\n\tdocker run --rm hashicorp/terraform:1.14.8 fmt\n",
            'terraform-fmt',
            true,
        ];

        yield 'circular make delegation returns false' => [
            "terraform-fmt: ## fmt ##*I*##\n\t\$(MAKE) terraform-fmt\n",
            'terraform-fmt',
            false,
        ];

        yield 'unknown target returns false' => [
            "other: ## x ##\n\tdocker run --rm image cmd\n",
            'missing-target',
            false,
        ];

        yield 'non-docker recipe' => [
            "update-k6-repositories: ## update ##*I*##\n\tphp bin/update-k6-repositories.php\n",
            'update-k6-repositories',
            false,
        ];

        yield 'empty recipe returns false' => [
            "empty-recipe: ## empty ##*I*##\nother-target: ## other ##\n\techo other\n",
            'empty-recipe',
            false,
        ];

        yield 'target at eof with no recipe' => [
            "solo-target: ## solo ##*I*##\n",
            'solo-target',
            false,
        ];

        yield 'prerequisite only target at eof without trailing newline' => [
            "child: ## c ##\n\tdocker run --rm image\nparent: child ## p ##*E*##",
            'parent',
            true,
        ];

        yield 'recipe after comment lines' => [
            "commented-recipe: ## recipe ##*I*##\n# prepare\n\tdocker run --rm image cmd\n",
            'commented-recipe',
            true,
        ];

        yield 'recipe after blank line' => [
            "blank-line-recipe: ## recipe ##*I*##\n\n\tdocker run --rm image cmd\n",
            'blank-line-recipe',
            true,
        ];

        yield 'custom docker wrapper after framework variable' => [
            <<<'MAKEFILE'
DOCKER_RUN:=docker run --rm ghcr.io/example/php:8.4-dev
CUSTOM_TOOL=docker run --rm hashicorp/terraform:1.14.8

terraform-fmt: ## fmt ##*I*##
	$(CUSTOM_TOOL) fmt
MAKEFILE,
            'terraform-fmt',
            true,
        ];

        yield 'defined docker wrapper unused in recipe' => [
            "MYTOOL=docker run image\n\nupdate-k6-repositories: ## update ##*I*##\n\tphp bin/update-k6-repositories.php\n",
            'update-k6-repositories',
            false,
        ];

        yield 'target with header only at eof' => [
            'solo-target: ## solo ##*I*##',
            'solo-target',
            false,
        ];

        yield 'recipe stops before next target' => [
            <<<'MAKEFILE'
fmt: ## fmt ##*I*##
	echo local fmt
next-target: ## next ##*I*##
	docker run --rm image fmt
MAKEFILE,
            'fmt',
            false,
        ];

        yield 'recipe ignores garbage before first recipe line' => [
            <<<'MAKEFILE'
fmt: ## fmt ##*I*##
garbage before recipe
	docker run --rm image fmt
MAKEFILE,
            'fmt',
            false,
        ];

        yield 'service lifecycle ifeq block recurses to docker compose' => [
            <<<'MAKEFILE'
before-unit-tests-service: ####
	docker compose up -d --wait

unit-testing: ## Run tests ##*AE*##
ifeq ("$(IN_CI)","TRUE")
	$(DOCKER_RUN_WITH_SOCKET) vendor/bin/phpunit
else
	@bash -ec '$(MAKE) before-unit-tests-service; trap "$(MAKE) after-unit-tests-service || true" EXIT; $(DOCKER_RUN_WITH_SOCKET) vendor/bin/phpunit'
endif
MAKEFILE,
            'unit-testing',
            true,
        ];
    }

    /** @return iterable<string, array{string, array<string, list<string>>, string, bool}> */
    public static function provideInjectFlagsCases(): iterable
    {
        yield 'docker tasks present' => [
            "ALL_HAS_DIRECT_DOCKER_TASKS=when_aggregate_has_direct_docker_tasks(all, TRUE, FALSE)\n\nall-target: ## x ##\n\tdocker build .\n",
            ['all' => ['all-target']],
            "ALL_HAS_DIRECT_DOCKER_TASKS=TRUE\n\nall-target: ## x ##\n\tdocker build .\n",
            false,
        ];

        yield 'no docker tasks' => [
            "ALL_HAS_DIRECT_DOCKER_TASKS=when_aggregate_has_direct_docker_tasks(all, TRUE, FALSE)\n\nall-target: ## x ##\n\tphp bin/test.php\n",
            ['all' => ['all-target']],
            "ALL_HAS_DIRECT_DOCKER_TASKS=FALSE\n\nall-target: ## x ##\n\tphp bin/test.php\n",
            false,
        ];

        yield 'unknown aggregate' => [
            'FLAG=when_aggregate_has_direct_docker_tasks(missing, TRUE, FALSE)',
            ['all' => []],
            '',
            true,
        ];

        yield 'ci-locked aggregate with documentation docker wrappers' => [
            <<<'MAKEFILE'
ALL_HAS_DIRECT_DOCKER_TASKS=when_aggregate_has_direct_docker_tasks(ci-locked, TRUE, FALSE)
DOCKER_RUN_DOCUMENTATION=docker run --rm -i image:tag
IMAGE_MARKDOWNLINT := markdown:tag
IMAGE_LYCHEE := lychee:tag
IMAGE_CSPELL := cspell:tag
IMAGE_VALE := vale:tag

documentation-markdownlint: ## Lint ##*K*##
	$(DOCKER_RUN_DOCUMENTATION) $(IMAGE_MARKDOWNLINT) --config etc/qa/documentation.markdownlint-cli2.yaml

documentation-links: ## Links ##*K*##
	$(DOCKER_RUN_DOCUMENTATION) $(IMAGE_LYCHEE) --config etc/qa/lychee.toml .

documentation-typos: ## Typos ##*K*##
	$(DOCKER_RUN_DOCUMENTATION) $(IMAGE_CSPELL) --config etc/qa/cspell.json .

documentation-vale: ## Vale ##*K*##
	$(DOCKER_RUN_DOCUMENTATION) $(IMAGE_VALE) --config etc/qa/vale.ini .
MAKEFILE,
            ['ci-locked' => ['documentation-markdownlint', 'documentation-links', 'documentation-typos', 'documentation-vale']],
            <<<'MAKEFILE'
ALL_HAS_DIRECT_DOCKER_TASKS=TRUE
DOCKER_RUN_DOCUMENTATION=docker run --rm -i image:tag
IMAGE_MARKDOWNLINT := markdown:tag
IMAGE_LYCHEE := lychee:tag
IMAGE_CSPELL := cspell:tag
IMAGE_VALE := vale:tag

documentation-markdownlint: ## Lint ##*K*##
	$(DOCKER_RUN_DOCUMENTATION) $(IMAGE_MARKDOWNLINT) --config etc/qa/documentation.markdownlint-cli2.yaml

documentation-links: ## Links ##*K*##
	$(DOCKER_RUN_DOCUMENTATION) $(IMAGE_LYCHEE) --config etc/qa/lychee.toml .

documentation-typos: ## Typos ##*K*##
	$(DOCKER_RUN_DOCUMENTATION) $(IMAGE_CSPELL) --config etc/qa/cspell.json .

documentation-vale: ## Vale ##*K*##
	$(DOCKER_RUN_DOCUMENTATION) $(IMAGE_VALE) --config etc/qa/vale.ini .
MAKEFILE,
            false,
        ];
    }

    /** @param array<string, list<string>> $aggregates */
    #[Test]
    #[DataProvider('provideInjectFlagsCases')]
    public function injectFlags(string $makefile, array $aggregates, string $expectedMakefile, bool $throws): void
    {
        if ($throws) {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageIsOrContains('Unknown task aggregate for direct docker detection: missing');
        }

        $result = DirectDockerDetector::injectFlags($makefile, $aggregates);

        if ($throws) {
            return;
        }

        self::assertSame($expectedMakefile, $result);
    }

    #[Test]
    public function recipeUsesDockerWrapperVariableReturnsFalseWhenNoVariables(): void
    {
        $method = new ReflectionMethod(DirectDockerDetector::class, 'recipeUsesDockerWrapperVariable');

        self::assertFalse($method->invoke(null, "\t\$(MYTOOL) fmt\n", []));
    }

    #[Test]
    public function recipeUsesDockerWrapperVariableQuotesRegexSpecialCharacters(): void
    {
        $method = new ReflectionMethod(DirectDockerDetector::class, 'recipeUsesDockerWrapperVariable');

        self::assertFalse($method->invoke(null, "\t\$(A1B) fmt\n", ['A.B' => true]));
        self::assertTrue($method->invoke(null, "\t\$(A.B) fmt\n", ['A.B' => true]));
        self::assertTrue($method->invoke(null, "\t\${A.B} fmt\n", ['A.B' => true]));
    }

    #[Test]
    public function extractTargetRecipeReturnsEmptyStringForHeaderOnlyTarget(): void
    {
        $method = new ReflectionMethod(DirectDockerDetector::class, 'extractTargetRecipe');

        self::assertSame('', $method->invoke(null, "solo-target: ## solo ##*I*##\n", 'solo-target'));
        self::assertSame('', $method->invoke(null, "empty-recipe: ## empty ##*I*##\nother-target: ## other ##\n\techo other\n", 'empty-recipe'));
    }

    #[Test]
    public function extractTargetPrerequisitesParsesDependenciesBeforeHelpMarker(): void
    {
        $method   = new ReflectionMethod(DirectDockerDetector::class, 'extractTargetPrerequisites');
        $makefile = "documentation-qa: documentation-markdownlint documentation-links ## Run all ##*E*##\n";

        self::assertSame(
            ['documentation-markdownlint', 'documentation-links'],
            $method->invoke(null, $makefile, 'documentation-qa'),
        );
        self::assertSame([], $method->invoke(null, $makefile, 'missing-target'));
        self::assertSame([], $method->invoke(null, "only-help: ## no deps ##*E*##\n", 'only-help'));
        self::assertSame(['doc'], $method->invoke(null, "meta: | doc ## m ##*E*##\n", 'meta'));
        self::assertSame(
            ['documentation-markdownlint'],
            $method->invoke(null, "documentation-qa:   documentation-markdownlint ## qa ##\n", 'documentation-qa'),
        );
        self::assertSame(['dep'], $method->invoke(null, "my.target: dep ## x ##\n", 'my.target'));
        self::assertSame(
            ['dep'],
            $method->invoke(null, "myXtarget: evil ## x ##\nmy.target: dep ## x ##\n", 'my.target'),
        );
        self::assertSame(['doc'], $method->invoke(null, "meta: doc   ## x ##\n", 'meta'));
    }

    #[Test]
    public function extractTargetRecipeReturnsRecipeWithTrailingNewline(): void
    {
        $method = new ReflectionMethod(DirectDockerDetector::class, 'extractTargetRecipe');

        self::assertSame(
            "\techo local\n\tdocker run --rm image cmd\n",
            $method->invoke(null, "two-step: ## x ##\n\techo local\n\tdocker run --rm image cmd\n", 'two-step'),
        );
        self::assertSame(
            "\tdocker run --rm image fmt\n",
            $method->invoke(null, "my.target: ## fmt ##\n\tdocker run --rm image fmt\n", 'my.target'),
        );
        self::assertSame(
            '',
            $method->invoke(null, 'parent: child ## p ##', 'parent'),
        );
    }

    #[Test]
    public function injectFlagsReplacesAllMatchingPlaceholders(): void
    {
        $input = <<<'MAKEFILE'
ALL_HAS_DIRECT_DOCKER_TASKS=when_aggregate_has_direct_docker_tasks(all, TRUE, FALSE)
CONTRIB_HAS_DIRECT_DOCKER_TASKS=when_aggregate_has_direct_docker_tasks(contrib, TRUE, FALSE)

all-task: ## x ##
	docker run image

contrib-task: ## y ##
	php bin/x.php
MAKEFILE;

        $expected = <<<'MAKEFILE'
ALL_HAS_DIRECT_DOCKER_TASKS=TRUE
CONTRIB_HAS_DIRECT_DOCKER_TASKS=FALSE

all-task: ## x ##
	docker run image

contrib-task: ## y ##
	php bin/x.php
MAKEFILE;

        self::assertSame($expected, DirectDockerDetector::injectFlags($input, [
            'all' => ['all-task'],
            'contrib' => ['contrib-task'],
        ]));
    }
}
