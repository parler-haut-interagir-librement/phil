<?php

declare(strict_types=1);

namespace Phil\NumberWords\Language;

use Normalizer;
use Phil\NumberWords\FrenchSpelling;
use Phil\NumberWords\Gender;
use Phil\NumberWords\Kind;
use Phil\NumberWords\SpellingOptions;

/**
 * French, for France, Canada, Belgium and Switzerland alike, ICU giving `septante` and `huitante` where they are said.
 *
 * ICU writes `vingt-et-un` with the hyphens of 1990 but `deux cent mille` with the spaces of the traditional spelling,
 * and leaves `cent` and `vingt` without their plural before `millions` (« deux cent millions »): the words are made
 * whole in the spelling the options name, and `cents` and `quatre-vingts` take their `s` before a noun of large
 * numbers, an ordinal (« deux cent millionième ») keeping them bare. Zero and one take the singular (« zéro dollar »), `de` comes after `million` and its kin (« un million
 * d'euros »), `et` joins units and sub-units, `moins` comes before a negative amount.
 */
final class French extends AbstractLanguageRules
{
    private const array LARGE_NOUNS = [
        'million', 'millions', 'milliard', 'milliards', 'billion', 'billions', 'billiard', 'billiards',
        'trillion', 'trillions', 'trilliard', 'trilliards',
    ];

    public function language(): string
    {
        return 'fr';
    }

    public function correct(string $words, Kind $kind, SpellingOptions $options): string
    {
        $tokens = $this->pluralBeforeLargeNouns(explode(' ', $words));

        if (FrenchSpelling::Traditional === $options->french) {
            return str_replace('-et-', ' et ', implode(' ', $tokens));
        }

        $previous = $tokens[0];
        $spelled = $previous;

        foreach (\array_slice($tokens, 1) as $token) {
            $apart = 'moins' === $previous || $this->isLargeNoun($previous) || $this->isLargeNoun($token);
            $spelled .= ($apart ? ' ' : '-').$token;
            $previous = $token;
        }

        return $spelled;
    }

    protected function currencies(): array
    {
        $dollar = ['dollar', 'dollars', Gender::Masculine, 2, 'cent', 'cents', Gender::Masculine];

        return [
            'AUD' => $dollar,
            'BRL' => ['real', 'reais', Gender::Masculine, 2, 'centavo', 'centavos', Gender::Masculine],
            'CAD' => $dollar,
            'CHF' => ['franc', 'francs', Gender::Masculine, 2, 'centime', 'centimes', Gender::Masculine],
            'EUR' => ['euro', 'euros', Gender::Masculine, 2, 'centime', 'centimes', Gender::Masculine],
            'GBP' => ['livre sterling', 'livres sterling', Gender::Feminine, 2, 'penny', 'pence', Gender::Masculine],
            'JPY' => ['yen', 'yens', Gender::Masculine, 0, null, null, Gender::Masculine],
            'MXN' => ['peso', 'pesos', Gender::Masculine, 2, 'centavo', 'centavos', Gender::Masculine],
            'NZD' => $dollar,
            'USD' => $dollar,
        ];
    }

    protected function connector(): string
    {
        return 'et';
    }

    protected function minus(): string
    {
        return 'moins';
    }

    protected function isSingular(int $count): bool
    {
        return $count < 2;
    }

    protected function largeNouns(): array
    {
        return self::LARGE_NOUNS;
    }

    protected function partitive(string $noun): string
    {
        $decomposed = (string) Normalizer::normalize($noun, Normalizer::FORM_D);

        return str_contains('aeiouAEIOU', $decomposed[0] ?? '-') ? "d'" : 'de ';
    }

    /**
     * @param list<string> $tokens
     *
     * @return list<string>
     */
    private function pluralBeforeLargeNouns(array $tokens): array
    {
        foreach ($tokens as $index => $token) {
            $multiplied = $index > 0 && 'moins' !== $tokens[$index - 1] && !$this->isLargeNoun($tokens[$index - 1]);
            $beforeNoun = $this->isLargeNoun($tokens[$index + 1] ?? '');

            if ('cent' === $token && $multiplied && $beforeNoun) {
                $tokens[$index] = 'cents';
            } elseif ($beforeNoun && str_ends_with($token, 'quatre-vingt')) {
                $tokens[$index] .= 's';
            }
        }

        return $tokens;
    }

    private function isLargeNoun(string $word): bool
    {
        return \in_array($word, self::LARGE_NOUNS, true);
    }
}
