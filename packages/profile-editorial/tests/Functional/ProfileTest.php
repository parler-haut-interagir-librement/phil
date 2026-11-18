<?php

declare(strict_types=1);

namespace Phil\Profile\Editorial\Tests\Functional;

use Override;
use Phil\Citations\CitationProcessor;
use Phil\ContentWarnings\Catalogue;
use Phil\ContentWarningsBundle\PhilContentWarningsBundle;
use Phil\FootnotesSidenotes\NoteResolver;
use Phil\Markup\Markup;
use Phil\MarkupBundle\PhilMarkupBundle;
use Phil\Profile\Editorial\Tests\Functional\App\TestKernel;
use Phil\StructuredData\Schema;
use Phil\StructuredDataBundle\PhilStructuredDataBundle;
use PHPUnit\Framework\Attributes\CoversNothing;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The profile has no code: what it promises is that its members install together and that an application boots with
 * every bundle they bring.
 */
#[CoversNothing]
final class ProfileTest extends KernelTestCase
{
    /** The bundles the members bring, those of the profile's own requires and those they require in turn. */
    private const array BUNDLES = [
        PhilMarkupBundle::class,
        PhilContentWarningsBundle::class,
        PhilStructuredDataBundle::class,
    ];

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testTheApplicationBootsWithEveryBundleOfTheMembers(): void
    {
        $kernel = self::bootKernel();

        // A kernel names each bundle after its class, whatever object stands for it.
        foreach (self::BUNDLES as $bundle) {
            self::assertArrayHasKey(new ReflectionClass($bundle)->getShortName(), $kernel->getBundles(), $bundle.' is registered.');
        }

        self::assertTrue(self::getContainer()->has('kernel'));
    }

    public function testEveryBundleRequiredByTheProfileIsRegistered(): void
    {
        foreach ($this->members() as $package) {
            if (str_ends_with($package, '-bundle')) {
                self::assertContains($this->bundleClass($package), self::BUNDLES, $package.' brings a bundle the application registers.');
            }
        }
    }

    public function testTheLibrariesOfTheMembersAreInstalled(): void
    {
        foreach ([Markup::class, CitationProcessor::class, NoteResolver::class, Catalogue::class, Schema::class] as $class) {
            self::assertFileExists((string) new ReflectionClass($class)->getFileName(), $class.' is installed.');
        }
    }

    public function testEveryMemberIsAPhilPackageAtAPublishedMinor(): void
    {
        $members = $this->members();

        self::assertSame(['phil/citations', 'phil/content-warnings-bundle', 'phil/footnotes-sidenotes', 'phil/markup-bundle', 'phil/structured-data-bundle'], $members);

        foreach ($this->constraints() as $package => $constraint) {
            self::assertMatchesRegularExpression('/^\^0\.\d+$/', $constraint, $package.' follows its latest published minor, without a stability flag.');
        }
    }

    /**
     * The `phil/*` packages the profile requires, sorted.
     *
     * @return list<string>
     */
    private function members(): array
    {
        $members = array_keys($this->constraints());
        sort($members);

        return $members;
    }

    /**
     * @return array<string, string>
     */
    private function constraints(): array
    {
        $composer = json_decode((string) file_get_contents(\dirname(__DIR__, 2).'/composer.json'), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($composer);
        self::assertSame('metapackage', $composer['type'] ?? null);
        $require = $composer['require'] ?? null;
        self::assertIsArray($require);
        $constraints = [];

        foreach ($require as $package => $constraint) {
            if (\is_string($package) && \is_string($constraint) && str_starts_with($package, 'phil/')) {
                $constraints[$package] = $constraint;
            }
        }

        return $constraints;
    }

    /**
     * The class of a bundle from its package name: `phil/content-warnings-bundle` registers
     * `Phil\ContentWarningsBundle\PhilContentWarningsBundle`.
     */
    private function bundleClass(string $package): string
    {
        $name = str_replace(' ', '', ucwords(str_replace('-', ' ', substr($package, \strlen('phil/'), -\strlen('-bundle')))));

        return \sprintf('Phil\\%sBundle\\Phil%sBundle', $name, $name);
    }
}
