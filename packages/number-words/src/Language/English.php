<?php

declare(strict_types=1);

namespace Phil\NumberWords\Language;

use Phil\NumberWords\Gender;

/**
 * English: ICU's words as they are, `and` between units and sub-units, `minus` before a negative amount.
 */
final class English extends AbstractLanguageRules
{
    public function language(): string
    {
        return 'en';
    }

    protected function currencies(): array
    {
        $dollar = ['dollar', 'dollars', Gender::Masculine, 2, 'cent', 'cents', Gender::Masculine];

        return [
            'AUD' => $dollar,
            'BRL' => ['real', 'reais', Gender::Masculine, 2, 'centavo', 'centavos', Gender::Masculine],
            'CAD' => $dollar,
            'CHF' => ['franc', 'francs', Gender::Masculine, 2, 'centime', 'centimes', Gender::Masculine],
            'EUR' => ['euro', 'euros', Gender::Masculine, 2, 'cent', 'cents', Gender::Masculine],
            'GBP' => ['pound', 'pounds', Gender::Masculine, 2, 'penny', 'pence', Gender::Masculine],
            'JPY' => ['yen', 'yen', Gender::Masculine, 0, null, null, Gender::Masculine],
            'MXN' => ['peso', 'pesos', Gender::Masculine, 2, 'centavo', 'centavos', Gender::Masculine],
            'NZD' => $dollar,
            'USD' => $dollar,
        ];
    }

    protected function connector(): string
    {
        return 'and';
    }

    protected function minus(): string
    {
        return 'minus';
    }
}
