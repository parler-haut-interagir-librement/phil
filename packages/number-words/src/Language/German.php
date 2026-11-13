<?php

declare(strict_types=1);

namespace Phil\NumberWords\Language;

use Phil\NumberWords\Gender;

/**
 * German: `und` between units and sub-units, `minus` before a negative amount; the soft hyphens ICU puts in long numbers are left out unless the options keep them.
 */
final class German extends AbstractLanguageRules
{
    public function language(): string
    {
        return 'de';
    }

    protected function currencies(): array
    {
        $dollar = ['Dollar', 'Dollar', Gender::Masculine, 2, 'Cent', 'Cent', Gender::Masculine];

        return [
            'AUD' => $dollar,
            'BRL' => ['Real', 'Reais', Gender::Masculine, 2, 'Centavo', 'Centavos', Gender::Masculine],
            'CAD' => $dollar,
            'CHF' => ['Franken', 'Franken', Gender::Masculine, 2, 'Rappen', 'Rappen', Gender::Masculine],
            'EUR' => ['Euro', 'Euro', Gender::Masculine, 2, 'Cent', 'Cent', Gender::Masculine],
            'GBP' => ['Pfund', 'Pfund', Gender::Neuter, 2, 'Penny', 'Pence', Gender::Masculine],
            'JPY' => ['Yen', 'Yen', Gender::Masculine, 0, null, null, Gender::Masculine],
            'MXN' => ['Peso', 'Pesos', Gender::Masculine, 2, 'Centavo', 'Centavos', Gender::Masculine],
            'NZD' => $dollar,
            'USD' => $dollar,
        ];
    }

    protected function connector(): string
    {
        return 'und';
    }

    protected function minus(): string
    {
        return 'minus';
    }
}
