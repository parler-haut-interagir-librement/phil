<?php

declare(strict_types=1);

namespace Phil\NumberWords\Contract;

use Phil\NumberWords\CurrencyName;
use Phil\NumberWords\Kind;
use Phil\NumberWords\SpellingOptions;

/**
 * What a language adds to ICU's spelling: the corrections of its words, and the grammar of amounts in words. The
 * library holds rules for French, English, Spanish, German, Italian and Portuguese; another language, or other
 * choices for one of them, is a class that implements this interface.
 */
interface LanguageRules
{
    /**
     * The primary language the rules are for, as a lowercase ISO 639 code: « fr ».
     */
    public function language(): string;

    /**
     * ICU's words for a number, soft hyphens already left out unless the options keep them, corrected where they
     * differ from the language's usage.
     */
    public function correct(string $words, Kind $kind, SpellingOptions $options): string;

    /**
     * The names of a currency, by its ISO 4217 code in uppercase; null for a currency the rules do not name.
     */
    public function currency(string $code): ?CurrencyName;

    /**
     * A count followed by its noun, the noun singular or plural for that count, with what the language puts between
     * them: « deux millions de dollars », « one cent ».
     *
     * @param string $number the count in words, as the noun's gender wants it
     * @param int    $count  the count, zero or more
     */
    public function count(string $number, int $count, string $one, string $other): string;

    /**
     * The units and the sub-units of an amount joined: « cent vingt dollars et cinq cents ».
     */
    public function joinAmount(string $units, string $subunits): string;

    /**
     * An amount below zero: « moins cinq cents ».
     */
    public function negative(string $amount): string;
}
