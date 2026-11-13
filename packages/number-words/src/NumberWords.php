<?php

declare(strict_types=1);

namespace Phil\NumberWords;

use Locale;
use NumberFormatter;
use Phil\NumberWords\Contract\LanguageRules;
use Phil\NumberWords\Exception\IntlUnavailable;
use Phil\NumberWords\Exception\InvalidAmount;
use Phil\NumberWords\Exception\MissingRule;
use Phil\NumberWords\Exception\NumberOutOfRange;
use Phil\NumberWords\Exception\UnsupportedLocale;
use Phil\NumberWords\Language\English;
use Phil\NumberWords\Language\French;
use Phil\NumberWords\Language\German;
use Phil\NumberWords\Language\Italian;
use Phil\NumberWords\Language\Portuguese;
use Phil\NumberWords\Language\Spanish;
use ValueError;

/**
 * Numbers in words in one locale, by ICU's spelling rules (`NumberFormatter::SPELLOUT` of `ext-intl`): cardinals said
 * on their own or agreeing with a noun's gender, ordinals, and amounts of money in words, with the corrections of the
 * language where ICU differs from its usage.
 *
 * Any language ICU spells works for cardinals and ordinals; corrections and amounts come from the rules of the
 * language, held by the library for French, English, Spanish, German, Italian and Portuguese, or passed in. Nothing
 * falls back in silence: a locale ICU does not spell, a gender or a form the language lacks, a number too large for
 * words, each throws.
 *
 * The object holds its formatters once built and nothing else: it can serve every request of a worker.
 */
final class NumberWords
{
    private readonly string $language;

    private readonly ?LanguageRules $rules;

    /** @var list<string> */
    private readonly array $ruleSets;

    /** @var array<string, NumberFormatter> */
    private array $formatters = [];

    /**
     * @param string             $locale the locale, such as `fr_CA` or `de-CH`
     * @param LanguageRules|null $rules  the rules of the language; the library's own for its six languages when null
     *
     * @throws IntlUnavailable   when `ext-intl` is not loaded
     * @throws UnsupportedLocale when ICU does not spell numbers in the locale's language, or the rules are for another
     */
    public function __construct(
        private readonly string $locale,
        private readonly SpellingOptions $options = new SpellingOptions(),
        ?LanguageRules $rules = null,
    ) {
        if (!\extension_loaded('intl')) {
            throw IntlUnavailable::create();
        }

        try {
            $formatter = new NumberFormatter($locale, NumberFormatter::SPELLOUT);
        } catch (ValueError) {
            throw UnsupportedLocale::noRules($locale);
        }

        $this->language = '' === $locale ? '' : Locale::getPrimaryLanguage($locale) ?? '';

        if (Locale::getPrimaryLanguage($formatter->getLocale(Locale::VALID_LOCALE)) !== $this->language) {
            throw UnsupportedLocale::noRules($locale);
        }

        $this->ruleSets = explode(';', $formatter->getTextAttribute(NumberFormatter::PUBLIC_RULESETS));
        $this->rules = $rules ?? match ($this->language) {
            'de' => new German(),
            'en' => new English(),
            'es' => new Spanish(),
            'fr' => new French(),
            'it' => new Italian(),
            'pt' => new Portuguese(),
            default => null,
        };

        if ($this->rules instanceof LanguageRules && $this->rules->language() !== $this->language) {
            throw UnsupportedLocale::otherLanguage($locale, $this->rules->language());
        }
    }

    /**
     * A cardinal. Without a gender, the form said on its own, when counting (« vingt et un », « eins »,
     * « veintiuno »); with one, the form that agrees with a noun of that gender after it (« vingt et une »,
     * « ein », « veintiún »). A language without genders, such as English, has one form for all.
     *
     * @throws MissingRule      a gender the language lacks, or a verbose form it lacks
     * @throws NumberOutOfRange a number too large for words
     */
    public function cardinal(int $number, ?Gender $gender = null): string
    {
        if (!$gender instanceof Gender) {
            return $this->spell($number, Kind::Counting, '%spellout-numbering');
        }

        return $this->spell($number, Kind::Cardinal, $this->gendered('%spellout-cardinal', $gender, false));
    }

    /**
     * An ordinal, zero or more. Without a gender, the language's plain form when it has one (« twenty-first »,
     * « einundzwanzigste »), the masculine otherwise (« vingt et unième », « vigésimo primero »); `$plural` for the
     * plural forms of French, Spanish and Italian, and of German in recent ICU data (« premières »).
     *
     * @throws MissingRule      a gender, a plural or a verbose form the language lacks
     * @throws NumberOutOfRange a negative number, or one too large for words
     */
    public function ordinal(int $number, ?Gender $gender = null, bool $plural = false): string
    {
        if ($number < 0) {
            throw NumberOutOfRange::negativeOrdinal($number);
        }

        $suffix = $plural ? '-plural' : '';

        if (!$gender instanceof Gender && \in_array('%spellout-ordinal'.$suffix, $this->ruleSets, true)) {
            return $this->spell($number, Kind::Ordinal, '%spellout-ordinal'.$suffix);
        }

        return $this->spell($number, Kind::Ordinal, $this->gendered('%spellout-ordinal', $gender ?? Gender::Masculine, $plural));
    }

    /**
     * An amount of money in words: its units, then its sub-units if any, each number agreeing with its noun
     * (« cent vingt dollars et cinq cents », « una libra con veinte peniques »). Zero units are left out when there
     * are sub-units; zero in all is said in units (« zéro euro »).
     *
     * @param int|string          $amount   a whole amount, or a decimal string with a point: "120.05", "-3", "0.5"
     * @param string|CurrencyName $currency an ISO 4217 code the language's rules name, or the names themselves
     *
     * @throws UnsupportedLocale a language without rules for amounts
     * @throws MissingRule       a currency the rules do not name
     * @throws InvalidAmount     not a decimal number, more than 18 digits of units, more decimals than the currency
     */
    public function amount(int|string $amount, string|CurrencyName $currency): string
    {
        if (!$this->rules instanceof LanguageRules) {
            throw UnsupportedLocale::noAmounts($this->locale);
        }

        $name = $currency instanceof CurrencyName
            ? $currency
            : $this->rules->currency(strtoupper($currency)) ?? throw MissingRule::currency($this->locale, $currency);

        $text = (string) $amount;

        if (1 !== preg_match('/^(-?)(\d{1,18})(?:\.(\d+))?$/D', $text, $matches)) {
            throw InvalidAmount::notDecimal($text);
        }

        $digits = $name->fractionDigits;
        $fraction = $matches[3] ?? '';

        if ('' !== trim(substr($fraction, $digits), '0')) {
            throw InvalidAmount::tooPrecise($text, $digits);
        }

        $units = (int) $matches[2];
        $subunits = (int) str_pad(substr($fraction, 0, $digits), $digits, '0');
        $parts = [];

        if (0 !== $units || 0 === $subunits) {
            $parts[] = $this->rules->count($this->cardinal($units, $name->gender), $units, $name->one, $name->other);
        }

        if (0 !== $subunits) {
            $one = (string) $name->subunitOne;
            $parts[] = $this->rules->count($this->cardinal($subunits, $name->subunitGender), $subunits, $one, $name->subunitOther ?? $one);
        }

        $words = 2 === \count($parts) ? $this->rules->joinAmount($parts[0], $parts[1]) : $parts[0];

        return '-' === $matches[1] && (0 !== $units || 0 !== $subunits) ? $this->rules->negative($words) : $words;
    }

    private function gendered(string $base, Gender $gender, bool $plural): string
    {
        $suffix = $plural ? '-plural' : '';

        foreach ($this->ruleSets as $ruleSet) {
            foreach (Gender::cases() as $any) {
                if (str_starts_with($ruleSet, $base.'-'.$any->value)) {
                    return $base.'-'.$gender->value.$suffix;
                }
            }
        }

        return $base.$suffix;
    }

    private function spell(int $number, Kind $kind, string $ruleSet): string
    {
        if ($this->options->verbose) {
            $ruleSet .= '-verbose';
        }

        if (!\in_array($ruleSet, $this->ruleSets, true)) {
            throw MissingRule::ruleSet($this->locale, $ruleSet);
        }

        $words = (string) $this->formatter($ruleSet)->format($number);

        if (1 === preg_match('/\p{Nd}/u', $words)) {
            throw NumberOutOfRange::tooLarge($number, $this->locale);
        }

        if (!$this->options->softHyphens) {
            $words = str_replace("\u{AD}", '', $words);
        }

        return $this->rules?->correct($words, $kind, $this->options) ?? $words;
    }

    private function formatter(string $ruleSet): NumberFormatter
    {
        if (!isset($this->formatters[$ruleSet])) {
            $formatter = new NumberFormatter($this->locale, NumberFormatter::SPELLOUT);
            $formatter->setTextAttribute(NumberFormatter::DEFAULT_RULESET, $ruleSet);
            $this->formatters[$ruleSet] = $formatter;
        }

        return $this->formatters[$ruleSet];
    }
}
