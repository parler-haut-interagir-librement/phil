<?php

declare(strict_types=1);

namespace Phil\NumberWords\Language;

use Phil\NumberWords\Gender;

/**
 * Spanish: `de` after `millón` and its kin (« un millón de dólares »), `con` between units and sub-units, `menos` before a negative amount.
 */
final class Spanish extends AbstractLanguageRules
{
    public function language(): string
    {
        return 'es';
    }

    protected function currencies(): array
    {
        $dollar = ['dólar', 'dólares', Gender::Masculine, 2, 'centavo', 'centavos', Gender::Masculine];

        return [
            'AUD' => $dollar,
            'BRL' => ['real', 'reales', Gender::Masculine, 2, 'centavo', 'centavos', Gender::Masculine],
            'CAD' => $dollar,
            'CHF' => ['franco', 'francos', Gender::Masculine, 2, 'céntimo', 'céntimos', Gender::Masculine],
            'EUR' => ['euro', 'euros', Gender::Masculine, 2, 'céntimo', 'céntimos', Gender::Masculine],
            'GBP' => ['libra', 'libras', Gender::Feminine, 2, 'penique', 'peniques', Gender::Masculine],
            'JPY' => ['yen', 'yenes', Gender::Masculine, 0, null, null, Gender::Masculine],
            'MXN' => ['peso', 'pesos', Gender::Masculine, 2, 'centavo', 'centavos', Gender::Masculine],
            'NZD' => $dollar,
            'USD' => $dollar,
        ];
    }

    protected function connector(): string
    {
        return 'con';
    }

    protected function minus(): string
    {
        return 'menos';
    }

    protected function largeNouns(): array
    {
        return ['millón', 'millones', 'billón', 'billones', 'trillón', 'trillones'];
    }

    protected function partitive(string $noun): string
    {
        return 'de ';
    }
}
