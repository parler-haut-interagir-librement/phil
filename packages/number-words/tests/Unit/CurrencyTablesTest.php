<?php

declare(strict_types=1);

namespace Phil\NumberWords\Tests\Unit;

use Phil\NumberWords\Contract\LanguageRules;
use Phil\NumberWords\CurrencyName;
use Phil\NumberWords\Gender;
use Phil\NumberWords\Language\English;
use Phil\NumberWords\Language\French;
use Phil\NumberWords\Language\German;
use Phil\NumberWords\Language\Italian;
use Phil\NumberWords\Language\Portuguese;
use Phil\NumberWords\Language\Spanish;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CurrencyName::class)]
#[CoversClass(English::class)]
#[CoversClass(French::class)]
#[CoversClass(German::class)]
#[CoversClass(Italian::class)]
#[CoversClass(Portuguese::class)]
#[CoversClass(Spanish::class)]
final class CurrencyTablesTest extends TestCase
{
    /**
     * @return iterable<string, array{LanguageRules, string, CurrencyName}>
     */
    public static function currencies(): iterable
    {
        $m = Gender::Masculine;
        $f = Gender::Feminine;
        $tables = [
            'English' => [new English(), [
                'AUD' => ['dollar', 'dollars', $m, 2, 'cent', 'cents', $m],
                'BRL' => ['real', 'reais', $m, 2, 'centavo', 'centavos', $m],
                'CAD' => ['dollar', 'dollars', $m, 2, 'cent', 'cents', $m],
                'CHF' => ['franc', 'francs', $m, 2, 'centime', 'centimes', $m],
                'EUR' => ['euro', 'euros', $m, 2, 'cent', 'cents', $m],
                'GBP' => ['pound', 'pounds', $m, 2, 'penny', 'pence', $m],
                'JPY' => ['yen', 'yen', $m, 0, null, null, $m],
                'MXN' => ['peso', 'pesos', $m, 2, 'centavo', 'centavos', $m],
                'NZD' => ['dollar', 'dollars', $m, 2, 'cent', 'cents', $m],
                'USD' => ['dollar', 'dollars', $m, 2, 'cent', 'cents', $m],
            ]],
            'French' => [new French(), [
                'AUD' => ['dollar', 'dollars', $m, 2, 'cent', 'cents', $m],
                'BRL' => ['real', 'reais', $m, 2, 'centavo', 'centavos', $m],
                'CAD' => ['dollar', 'dollars', $m, 2, 'cent', 'cents', $m],
                'CHF' => ['franc', 'francs', $m, 2, 'centime', 'centimes', $m],
                'EUR' => ['euro', 'euros', $m, 2, 'centime', 'centimes', $m],
                'GBP' => ['livre sterling', 'livres sterling', $f, 2, 'penny', 'pence', $m],
                'JPY' => ['yen', 'yens', $m, 0, null, null, $m],
                'MXN' => ['peso', 'pesos', $m, 2, 'centavo', 'centavos', $m],
                'NZD' => ['dollar', 'dollars', $m, 2, 'cent', 'cents', $m],
                'USD' => ['dollar', 'dollars', $m, 2, 'cent', 'cents', $m],
            ]],
            'German' => [new German(), [
                'AUD' => ['Dollar', 'Dollar', $m, 2, 'Cent', 'Cent', $m],
                'BRL' => ['Real', 'Reais', $m, 2, 'Centavo', 'Centavos', $m],
                'CAD' => ['Dollar', 'Dollar', $m, 2, 'Cent', 'Cent', $m],
                'CHF' => ['Franken', 'Franken', $m, 2, 'Rappen', 'Rappen', $m],
                'EUR' => ['Euro', 'Euro', $m, 2, 'Cent', 'Cent', $m],
                'GBP' => ['Pfund', 'Pfund', Gender::Neuter, 2, 'Penny', 'Pence', $m],
                'JPY' => ['Yen', 'Yen', $m, 0, null, null, $m],
                'MXN' => ['Peso', 'Pesos', $m, 2, 'Centavo', 'Centavos', $m],
                'NZD' => ['Dollar', 'Dollar', $m, 2, 'Cent', 'Cent', $m],
                'USD' => ['Dollar', 'Dollar', $m, 2, 'Cent', 'Cent', $m],
            ]],
            'Italian' => [new Italian(), [
                'AUD' => ['dollaro', 'dollari', $m, 2, 'centesimo', 'centesimi', $m],
                'BRL' => ['real', 'reais', $m, 2, 'centavo', 'centavos', $m],
                'CAD' => ['dollaro', 'dollari', $m, 2, 'centesimo', 'centesimi', $m],
                'CHF' => ['franco', 'franchi', $m, 2, 'centesimo', 'centesimi', $m],
                'EUR' => ['euro', 'euro', $m, 2, 'centesimo', 'centesimi', $m],
                'GBP' => ['sterlina', 'sterline', $f, 2, 'penny', 'pence', $m],
                'JPY' => ['yen', 'yen', $m, 0, null, null, $m],
                'MXN' => ['peso', 'pesos', $m, 2, 'centavo', 'centavos', $m],
                'NZD' => ['dollaro', 'dollari', $m, 2, 'centesimo', 'centesimi', $m],
                'USD' => ['dollaro', 'dollari', $m, 2, 'centesimo', 'centesimi', $m],
            ]],
            'Portuguese' => [new Portuguese(), [
                'AUD' => ['dólar', 'dólares', $m, 2, 'centavo', 'centavos', $m],
                'BRL' => ['real', 'reais', $m, 2, 'centavo', 'centavos', $m],
                'CAD' => ['dólar', 'dólares', $m, 2, 'centavo', 'centavos', $m],
                'CHF' => ['franco', 'francos', $m, 2, 'cêntimo', 'cêntimos', $m],
                'EUR' => ['euro', 'euros', $m, 2, 'cêntimo', 'cêntimos', $m],
                'GBP' => ['libra', 'libras', $f, 2, 'penny', 'pence', $m],
                'JPY' => ['iene', 'ienes', $m, 0, null, null, $m],
                'MXN' => ['peso', 'pesos', $m, 2, 'centavo', 'centavos', $m],
                'NZD' => ['dólar', 'dólares', $m, 2, 'centavo', 'centavos', $m],
                'USD' => ['dólar', 'dólares', $m, 2, 'centavo', 'centavos', $m],
            ]],
            'Spanish' => [new Spanish(), [
                'AUD' => ['dólar', 'dólares', $m, 2, 'centavo', 'centavos', $m],
                'BRL' => ['real', 'reales', $m, 2, 'centavo', 'centavos', $m],
                'CAD' => ['dólar', 'dólares', $m, 2, 'centavo', 'centavos', $m],
                'CHF' => ['franco', 'francos', $m, 2, 'céntimo', 'céntimos', $m],
                'EUR' => ['euro', 'euros', $m, 2, 'céntimo', 'céntimos', $m],
                'GBP' => ['libra', 'libras', $f, 2, 'penique', 'peniques', $m],
                'JPY' => ['yen', 'yenes', $m, 0, null, null, $m],
                'MXN' => ['peso', 'pesos', $m, 2, 'centavo', 'centavos', $m],
                'NZD' => ['dólar', 'dólares', $m, 2, 'centavo', 'centavos', $m],
                'USD' => ['dólar', 'dólares', $m, 2, 'centavo', 'centavos', $m],
            ]],
        ];

        foreach ($tables as $language => [$rules, $currencies]) {
            foreach ($currencies as $code => $names) {
                yield $language.' '.$code => [$rules, $code, new CurrencyName(...$names)];
            }
        }
    }

    #[DataProvider('currencies')]
    public function testEachLanguageNamesEachCurrency(LanguageRules $rules, string $code, CurrencyName $expected): void
    {
        self::assertEquals($expected, $rules->currency($code));
    }

    public function testAnUnknownCurrencyHasNoName(): void
    {
        self::assertNotInstanceOf(CurrencyName::class, new English()->currency('XXX'));
    }

    public function testADefaultCurrencyNameHasTwoDecimals(): void
    {
        self::assertSame(2, new CurrencyName('a', 'b', subunitOne: 'c')->fractionDigits);
    }
}
