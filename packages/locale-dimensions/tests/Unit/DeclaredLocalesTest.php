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
