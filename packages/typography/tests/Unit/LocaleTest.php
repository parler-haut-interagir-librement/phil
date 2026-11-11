<?php

declare(strict_types=1);

namespace Phil\Typography\Tests\Unit;

use Phil\Typography\Exception\InvalidLocale;
use Phil\Typography\Exception\TypographyException;
use Phil\Typography\Locale;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Locale::class)]
#[CoversClass(InvalidLocale::class)]
final class LocaleTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string, ?string, ?string, list<string>}>
     */
    public static function locales(): iterable
    {
        yield 'language' => ['FR', 'fr', 'fr', null, null, ['fr']];
        yield 'region with an underscore' => ['fr_CA', 'fr_CA', 'fr', null, 'CA', ['fr_CA', 'fr']];
        yield 'region with a hyphen, in lower case' => ['fr-ca', 'fr_CA', 'fr', null, 'CA', ['fr_CA', 'fr']];
        yield 'script' => ['sr-latn', 'sr_Latn', 'sr', 'Latn', null, ['sr_Latn', 'sr']];
        yield 'script and region' => ['sr_LATN_rs', 'sr_Latn_RS', 'sr', 'Latn', 'RS', ['sr_Latn_RS', 'sr_RS', 'sr_Latn', 'sr']];
        yield 'numeric region' => ['es_419', 'es_419', 'es', null, '419', ['es_419', 'es']];
        yield 'three letters' => ['grc', 'grc', 'grc', null, null, ['grc']];
    }

    /**
     * @param list<string> $candidates
     */
    #[DataProvider('locales')]
    public function testItReadsTheLocale(string $written, string $canonical, string $language, ?string $script, ?string $region, array $candidates): void
    {
        $locale = Locale::parse($written);

        self::assertSame($canonical, $locale->__toString());
        self::assertSame($language, $locale->language);
        self::assertSame($script, $locale->script);
        self::assertSame($region, $locale->region);
        self::assertSame($candidates, $locale->candidates());
    }

    /**
     * @return iterable<array{string}>
     */
    public static function invalid(): iterable
    {
        yield [''];
        yield ['f'];
        yield ['fren'];
        yield ['fr_C'];
        yield ['fr_CAN'];
        yield ['fr_CA_x'];
        yield [' fr'];
        yield ["fr\n"];
        yield ['fr__CA'];
        yield ['fr_1234'];
        yield ['fr_Latnn'];
    }

    #[DataProvider('invalid')]
    public function testItRefusesWhatIsNotALocale(string $locale): void
    {
        try {
            Locale::parse($locale);
            self::fail($locale);
        } catch (InvalidLocale $invalidLocale) {
            self::assertInstanceOf(TypographyException::class, $invalidLocale);
            self::assertSame(\sprintf('"%s" is not a locale: write a language, then an optional script and region, as "fr", "fr_CA" or "sr_Latn_RS".', $locale), $invalidLocale->getMessage());
        }
    }
}
