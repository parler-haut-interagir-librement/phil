<?php

declare(strict_types=1);

namespace Phil\LocaleDimensions\Tests\Unit;

use Phil\LocaleDimensions\DeclaredLocales;
use Phil\LocaleDimensions\Exception\InvalidLocaleDimension;
use Phil\LocaleDimensions\Locale;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeclaredLocales::class)]
#[CoversClass(InvalidLocaleDimension::class)]
final class DeclaredLocalesTest extends TestCase
{
    public function testEveryServedLocaleIsListedInDeclarationOrder(): void
    {
        $locales = DeclaredLocales::fromArray(['fr_CA' => ['fr'], 'fr' => [], 'en_CA' => []]);

        self::assertSame(['fr_CA', 'fr', 'en_CA'], array_map(static fn (Locale $locale): string => $locale->value, $locales->all()));
    }

    public function testTheChainIsTheLocaleThenItsFallbacksInOrder(): void
    {
        $locales = DeclaredLocales::fromArray(['fr_CA' => ['fr', 'en_CA'], 'fr' => [], 'en_CA' => []]);

        self::assertSame('fr_CA → fr → en_CA', $locales->chainFor(Locale::fromString('fr_CA'))->toString());
        self::assertSame('fr', $locales->chainFor(Locale::fromString('fr'))->toString());
    }

    public function testOnlyADeclaredLocaleIsServed(): void
    {
        $locales = DeclaredLocales::fromArray(['fr' => []]);

        self::assertTrue($locales->isServed(Locale::fromString('fr')));
        self::assertFalse($locales->isServed(Locale::fromString('fr_CA')));
    }

    public function testAnUnservedLocaleHasNoChain(): void
    {
        $this->expectException(InvalidLocaleDimension::class);
        $this->expectExceptionMessageIsOrContains('The locale "en" is not served.');

        DeclaredLocales::fromArray(['fr' => []])->chainFor(Locale::fromString('en'));
    }

    public function testALocaleWithoutAChainGetsItsParentsThenTheDefaultsThatAreServed(): void
    {
        $locales = DeclaredLocales::fromArray(['fr_CA' => null, 'en_CA' => null, 'pt_BR' => null, 'fr' => null, 'en' => [], 'de' => []], ['en', 'fr']);

        self::assertSame('fr_CA → fr → en', $locales->chainFor(Locale::fromString('fr_CA'))->toString());
        self::assertSame('en_CA → en → fr', $locales->chainFor(Locale::fromString('en_CA'))->toString());
        self::assertSame('pt_BR → en → fr', $locales->chainFor(Locale::fromString('pt_BR'))->toString());
        self::assertSame('fr → en', $locales->chainFor(Locale::fromString('fr'))->toString());
        self::assertSame('en', $locales->chainFor(Locale::fromString('en'))->toString(), 'an empty list declares no fallback');
        self::assertSame('de', $locales->chainFor(Locale::fromString('de'))->toString(), 'the defaults end only the default chains');
    }

    public function testADefaultChainWithoutDefaultsHoldsTheServedParentsOnly(): void
    {
        $locales = DeclaredLocales::fromArray(['es_MX' => null, 'es' => [], 'fr_CA' => null]);

        self::assertSame('es_MX → es', $locales->chainFor(Locale::fromString('es_MX'))->toString(), 'es_419 is skipped: it is not served');
        self::assertSame('fr_CA', $locales->chainFor(Locale::fromString('fr_CA'))->toString());
    }

    public function testNothingDeclaredServesNothing(): void
    {
        self::assertSame([], DeclaredLocales::fromArray([])->all());
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function refusedDeclarations(): iterable
    {
        yield 'a malformed locale' => [['fr-CA' => []], '"fr-CA" is not a locale'];
        yield 'an integer key' => [[12 => []], '"12" is not a locale'];
        yield 'a fallback that is not served' => [['fr_CA' => ['fr']], 'The fallback "fr" of "fr_CA" is not a served locale.'];
        yield 'a fallback that is not a string' => [['fr' => [3]], 'The fallback "int" of "fr" is not a served locale.'];
        yield 'fallbacks that are not a list' => [['fr' => 'en'], 'The fallbacks of "fr" are not a list of locales.'];
        yield 'a chain through its own locale' => [['fr' => ['fr']], 'The locale "fr" appears twice'];
        yield 'a fallback named twice' => [['fr_CA' => ['fr', 'fr'], 'fr' => []], 'The locale "fr" appears twice'];
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, array<array-key, mixed>, string}>
     */
    public static function refusedDefaults(): iterable
    {
        yield 'a default that is not served' => [['fr' => null], ['en'], 'The default fallback "en" is not a served locale.'];
        yield 'a default that is not a string' => [['fr' => null], [false], 'The default fallback "bool" is not a served locale.'];
        yield 'a malformed default' => [['fr' => null], ['fr<'], 'The default fallback "fr<" is not a served locale.'];
    }

    /**
     * @param array<array-key, mixed> $declared
     * @param array<array-key, mixed> $defaults
     */
    #[DataProvider('refusedDefaults')]
    public function testItRefusesTheDefaults(array $declared, array $defaults, string $message): void
    {
        $this->expectException(InvalidLocaleDimension::class);
        $this->expectExceptionMessageIsOrContains($message);

        DeclaredLocales::fromArray($declared, $defaults);
    }

    /**
     * @param array<array-key, mixed> $declared
     */
    #[DataProvider('refusedDeclarations')]
    public function testItRefuses(array $declared, string $message): void
    {
        $this->expectException(InvalidLocaleDimension::class);
        $this->expectExceptionMessageIsOrContains($message);

        DeclaredLocales::fromArray($declared);
    }
}
