<?php

declare(strict_types=1);

namespace Phil\NumberWords\Language;

use Phil\NumberWords\Gender;

/**
 * Italian: `di` after `milione` and its kin (« un milione di euro »), `e` between units and sub-units, `meno` before a negative amount.
 */
final class Italian extends AbstractLanguageRules
{
    public function language(): string
    {
        return 'it';
    }

    protected function currencies(): array
    {
        $dollar = ['dollaro', 'dollari', Gender::Masculine, 2, 'centesimo', 'centesimi', Gender::Masculine];

        return [
            'AUD' => $dollar,
            'BRL' => ['real', 'reais', Gender::Masculine, 2, 'centavo', 'centavos', Gender::Masculine],
            'CAD' => $dollar,
            'CHF' => ['franco', 'franchi', Gender::Masculine, 2, 'centesimo', 'centesimi', Gender::Masculine],
            'EUR' => ['euro', 'euro', Gender::Masculine, 2, 'centesimo', 'centesimi', Gender::Masculine],
            'GBP' => ['sterlina', 'sterline', Gender::Feminine, 2, 'penny', 'pence', Gender::Masculine],
            'JPY' => ['yen', 'yen', Gender::Masculine, 0, null, null, Gender::Masculine],
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
        return 'meno';
    }

    protected function largeNouns(): array
    {
        return ['milione', 'milioni', 'miliardo', 'miliardi', 'bilione', 'bilioni', 'biliardo', 'biliardi', 'trilione', 'trilioni'];
    }

    protected function partitive(string $noun): string
    {
        return 'di ';
    }
}
