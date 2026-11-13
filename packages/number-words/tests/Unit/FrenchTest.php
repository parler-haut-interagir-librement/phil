<?php

declare(strict_types=1);

namespace Phil\NumberWords\Tests\Unit;

use Phil\NumberWords\FrenchSpelling;
use Phil\NumberWords\Gender;
use Phil\NumberWords\Language\AbstractLanguageRules;
use Phil\NumberWords\Language\French;
use Phil\NumberWords\NumberWords;
use Phil\NumberWords\SpellingOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(French::class)]
#[CoversClass(AbstractLanguageRules::class)]
#[CoversClass(NumberWords::class)]
final class FrenchTest extends TestCase
{
    /**
     * @return iterable<string, array{int, string, string}>
     */
    public static function cardinals(): iterable
    {
        yield 'et un' => [21, 'vingt et un', 'vingt-et-un'];
        yield 'et onze' => [71, 'soixante et onze', 'soixante-et-onze'];
        yield 'eighty-one has no et' => [81, 'quatre-vingt-un', 'quatre-vingt-un'];
        yield 'eighty takes its s' => [80, 'quatre-vingts', 'quatre-vingts'];
        yield 'two hundred takes its s' => [200, 'deux cents', 'deux-cents'];
        yield 'a thousand and one' => [1001, 'mille un', 'mille-un'];
        yield 'eighty thousand keeps vingt bare' => [80_000, 'quatre-vingt mille', 'quatre-vingt-mille'];
        yield 'hundreds before millions' => [200_000_000, 'deux cents millions', 'deux-cents millions'];
        yield 'eighty before millions' => [80_000_000, 'quatre-vingts millions', 'quatre-vingts millions'];
        yield 'a hundred and eighty before millions' => [180_000_000, 'cent quatre-vingts millions', 'cent-quatre-vingts millions'];
        yield 'hundreds before milliards' => [300_000_000_000, 'trois cents milliards', 'trois-cents milliards'];
        yield 'one hundred millions' => [100_000_000, 'cent millions', 'cent millions'];
        yield 'a hundred millions after a milliard' => [1_100_000_000, 'un milliard cent millions', 'un milliard cent millions'];
        yield 'nouns apart' => [1_234_567, 'un million deux cent trente-quatre mille cinq cent soixante-sept', 'un million deux-cent-trente-quatre-mille-cinq-cent-soixante-sept'];
        yield 'negative' => [-21, 'moins vingt et un', 'moins vingt-et-un'];
        yield 'negative hundred millions' => [-100_000_000, 'moins cent millions', 'moins cent millions'];
        yield 'negative hundreds of millions' => [-200_000_000, 'moins deux cents millions', 'moins deux-cents millions'];
    }

    #[DataProvider('cardinals')]
    public function testCardinals(int $number, string $traditional, string $rectified): void
    {
        self::assertSame($traditional, new NumberWords('fr_CA')->cardinal($number));
        self::assertSame($rectified, new NumberWords('fr', new SpellingOptions(french: FrenchSpelling::Rectified1990))->cardinal($number));
    }

    public function testGenderAndOrdinals(): void
    {
        $words = new NumberWords('fr');
        $rectified = new NumberWords('fr', new SpellingOptions(french: FrenchSpelling::Rectified1990));

        self::assertSame('vingt et une', $words->cardinal(21, Gender::Feminine));
        self::assertSame('premier', $words->ordinal(1));
        self::assertSame('première', $words->ordinal(1, Gender::Feminine));
        self::assertSame('premières', $words->ordinal(1, Gender::Feminine, true));
        self::assertSame('vingt et unième', $words->ordinal(21));
        self::assertSame('vingt-et-unième', $rectified->ordinal(21));
        self::assertSame('deux cent millionième', $words->ordinal(200_000_000));
        self::assertSame('deux-cent-millionième', $rectified->ordinal(200_000_000));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function amounts(): iterable
    {
        yield 'dollars and cents' => ['120.05', 'CAD', 'cent vingt dollars et cinq cents'];
        yield 'zero takes the singular' => ['0', 'EUR', 'zéro euro'];
        yield 'one takes the singular' => ['1', 'USD', 'un dollar'];
        yield 'two take the plural' => ['2', 'USD', 'deux dollars'];
        yield 'one cent' => ['0.01', 'EUR', 'un centime'];
        yield 'a feminine unit' => ['21.01', 'GBP', 'vingt et une livres sterling et un penny'];
        yield 'de after million' => ['1000000', 'USD', 'un million de dollars'];
        yield "d' before a vowel" => ['2000000', 'EUR', "deux millions d'euros"];
        yield 'no elision before y' => ['1000000', 'JPY', 'un million de yens'];
        yield 'hundreds of millions' => ['200000000', 'CHF', 'deux cents millions de francs'];
        yield 'no de when the number goes on' => ['1200000', 'USD', 'un million deux cent mille dollars'];
        yield 'negative' => ['-0.05', 'USD', 'moins cinq cents'];
        yield 'pesos' => ['3', 'MXN', 'trois pesos'];
        yield 'reais' => ['3', 'BRL', 'trois reais'];
        yield 'Australian dollars' => ['3', 'AUD', 'trois dollars'];
        yield 'New Zealand dollars' => ['3', 'NZD', 'trois dollars'];
    }

    #[DataProvider('amounts')]
    public function testAmounts(string $amount, string $currency, string $expected): void
    {
        self::assertSame($expected, new NumberWords('fr')->amount($amount, $currency));
    }

    public function testElisionBeforeAnAccentedVowel(): void
    {
        $french = new French();

        self::assertSame("un million d'écus", $french->count('un million', 1_000_000, 'écu', 'écus'));
        self::assertSame("un million d'Euros", $french->count('un million', 1_000_000, 'Euro', 'Euros'));
        self::assertSame('un million de ', $french->count('un million', 1_000_000, '', ''));
        self::assertSame("un million de \xFF", $french->count('un million', 1_000_000, "\xFF", "\xFF"));
    }

    public function testZeroAndOneTakeTheSingular(): void
    {
        $french = new French();

        self::assertSame('zéro euro', $french->count('zéro', 0, 'euro', 'euros'));
        self::assertSame('un euro', $french->count('un', 1, 'euro', 'euros'));
        self::assertSame('deux euros', $french->count('deux', 2, 'euro', 'euros'));
    }

    public function testRectifiedAmounts(): void
    {
        $words = new NumberWords('fr', new SpellingOptions(french: FrenchSpelling::Rectified1990));

        self::assertSame('deux-cent-vingt-et-un dollars et vingt-et-un cents', $words->amount('221.21', 'USD'));
    }
}
