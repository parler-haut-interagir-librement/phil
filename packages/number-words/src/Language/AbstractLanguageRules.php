<?php

declare(strict_types=1);

namespace Phil\NumberWords\Language;

use Phil\NumberWords\Contract\LanguageRules;
use Phil\NumberWords\CurrencyName;
use Phil\NumberWords\Gender;
use Phil\NumberWords\Kind;
use Phil\NumberWords\SpellingOptions;

/**
 * The common grammar of amounts in the languages the library holds: a count takes the singular at one, a partitive
 * word comes between a count that ends with `million` or its kin and the noun, the units and sub-units are joined by
 * one word, a negative amount starts with one word. A language sets its words and its table of currencies.
 */
abstract class AbstractLanguageRules implements LanguageRules
{
    /**
     * The currencies the language names, by ISO 4217 code: unit singular and plural, its gender, the decimals, and
     * the sub-unit singular and plural with its gender.
     *
     * @return array<string, array{string, string, Gender, int, ?string, ?string, Gender}>
     */
    abstract protected function currencies(): array;

    /**
     * The word that joins units and sub-units.
     */
    abstract protected function connector(): string;

    /**
     * The word before a negative amount.
     */
    abstract protected function minus(): string;

    /**
     * The nouns of large numbers after which the language puts a partitive before the counted noun; none by default.
     *
     * @return list<string>
     */
    protected function largeNouns(): array
    {
        return [];
    }

    /**
     * The partitive put between a count that ends with a large noun and the counted noun: « de », « di ».
     */
    protected function partitive(string $noun): string
    {
        return '';
    }

    protected function isSingular(int $count): bool
    {
        return 1 === $count;
    }

    public function correct(string $words, Kind $kind, SpellingOptions $options): string
    {
        return $words;
    }

    public function currency(string $code): ?CurrencyName
    {
        $names = $this->currencies()[$code] ?? null;

        return null === $names ? null : new CurrencyName(...$names);
    }

    public function count(string $number, int $count, string $one, string $other): string
    {
        $noun = $this->isSingular($count) ? $one : $other;
        $words = explode(' ', $number);

        if (\in_array(end($words), $this->largeNouns(), true)) {
            return $number.' '.$this->partitive($noun).$noun;
        }

        return $number.' '.$noun;
    }

    public function joinAmount(string $units, string $subunits): string
    {
        return $units.' '.$this->connector().' '.$subunits;
    }

    public function negative(string $amount): string
    {
        return $this->minus().' '.$amount;
    }
}
