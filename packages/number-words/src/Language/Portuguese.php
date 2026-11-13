<?php

declare(strict_types=1);

namespace Phil\NumberWords\Language;

use Phil\NumberWords\Gender;

/**
 * Portuguese: `de` after `milhão` and its kin (« um milhão de dólares », « dois mil milhões de euros »), `e` between units and sub-units, `menos` before a negative amount.
 */
final class Portuguese extends AbstractLanguageRules
{
    public function language(): string
    {
        return 'pt';
    }

    protected function currencies(): array
    {
        $dollar = ['dólar', 'dólares', Gender::Masculine, 2, 'centavo', 'centavos', Gender::Masculine];

        return [
            'AUD' => $dollar,
            'BRL' => ['real', 'reais', Gender::Masculine, 2, 'centavo', 'centavos', Gender::Masculine],
            'CAD' => $dollar,
            'CHF' => ['franco', 'francos', Gender::Masculine, 2, 'cêntimo', 'cêntimos', Gender::Masculine],
            'EUR' => ['euro', 'euros', Gender::Masculine, 2, 'cêntimo', 'cêntimos', Gender::Masculine],
            'GBP' => ['libra', 'libras', Gender::Feminine, 2, 'penny', 'pence', Gender::Masculine],
            'JPY' => ['iene', 'ienes', Gender::Masculine, 0, null, null, Gender::Masculine],
            'MXN' => ['peso', 'pesos', Gender::Masculine, 2, 'centavo', 'centavos', Gender::Masculine],
            'NZD' => $dollar,
            'USD' => $dollar,
        ];
    }

    protected function connector(): string
    {
        return 'e';
    }

    protected function minus(): string
    {
        return 'menos';
    }

    protected function largeNouns(): array
    {
        return ['milhão', 'milhões', 'bilhão', 'bilhões', 'bilião', 'biliões', 'trilhão', 'trilhões', 'trilião', 'triliões', 'quatrilhão', 'quatrilhões'];
    }

    protected function partitive(string $noun): string
    {
        return 'de ';
    }
}
