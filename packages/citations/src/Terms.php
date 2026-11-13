<?php

declare(strict_types=1);

namespace Phil\Citations;

use Phil\Citations\Exception\MissingTerms;

/**
 * The words and the typography of one language that the styles write: `and`, `et al.`, the abbreviations of editor,
 * edition, page and volume, the quotation marks, the colon, the months and the ordinals of editions.
 *
 * The library holds English and French; another language is a `Terms` built with its own words.
 */
final readonly class Terms
{
    /**
     * @param string                                          $language                the language, for the collation of the bibliography
     * @param string                                          $and                     between the last two names in running text
     * @param string                                          $andSymbol               between the last two names of an author-date citation
     * @param bool                                            $serialComma             a comma before `and` in a list of three or more
     * @param string                                          $openQuote               before a quoted title, with its space if any
     * @param string                                          $closeQuote              after a quoted title, with its space if any
     * @param bool                                            $punctuationInsideQuotes a comma or period after a quoted title goes
     *                                                                                 inside the marks
     * @param string                                          $colon                   a colon with its spaces
     * @param bool                                            $capitalizeAbbreviations « Ed. » rather than « ed. » after a name or in
     *                                                                                 a parenthesis
     * @param string                                          $beforeCommonEra         after a year before the common era
     * @param list<string>                                    $months                  the twelve months
     * @param array<value-of<Locator>, array{string, string}> $locators                the label of each locator, singular and plural
     * @param array<int|string, string>                       $ordinalSuffixes         the suffix of an ordinal: by the whole number
     *                                                                                 after `=`, else by its last two digits, else by
     *                                                                                 its last digit, else `default`
     */
    public function __construct(
        public string $language,
        public string $and,
        public string $andSymbol,
        public bool $serialComma,
        public string $etAl,
        public string $noDate,
        public string $editor,
        public string $editors,
        public string $editedBy,
        public string $translatedBy,
        public string $in,
        public string $edition,
        public string $volume,
        public string $issue,
        public string $pages,
        public string $accessed,
        public string $online,
        public string $available,
        public string $openQuote,
        public string $closeQuote,
        public bool $punctuationInsideQuotes,
        public string $colon,
        public bool $capitalizeAbbreviations,
        public string $beforeCommonEra,
        public array $months,
        public array $locators,
        public array $ordinalSuffixes,
    ) {
    }

    public static function english(): self
    {
        return new self(
            language: 'en',
            and: 'and',
            andSymbol: '&',
            serialComma: true,
            etAl: 'et al.',
            noDate: 'n.d.',
            editor: 'ed.',
            editors: 'eds.',
            editedBy: 'edited by',
            translatedBy: 'translated by',
            in: 'in',
            edition: 'ed.',
            volume: 'vol.',
            issue: 'no.',
            pages: 'pp.',
            accessed: 'accessed',
            online: 'Online',
            available: 'Available',
            openQuote: '“',
            closeQuote: '”',
            punctuationInsideQuotes: true,
            colon: ': ',
            capitalizeAbbreviations: true,
            beforeCommonEra: 'BCE',
            months: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
            locators: [
                'page' => ['p.', 'pp.'], 'chapter' => ['chap.', 'chaps.'], 'section' => ['sec.', 'secs.'],
                'paragraph' => ['para.', 'paras.'], 'volume' => ['vol.', 'vols.'], 'figure' => ['fig.', 'figs.'],
                'line' => ['l.', 'll.'], 'note' => ['n.', 'nn.'],
            ],
            ordinalSuffixes: ['1' => 'st', '2' => 'nd', '3' => 'rd', '11' => 'th', '12' => 'th', '13' => 'th', 'default' => 'th'],
        );
    }

    public static function french(): self
    {
        return new self(
            language: 'fr',
            and: 'et',
            andSymbol: 'et',
            serialComma: false,
            etAl: 'et al.',
            noDate: 's. d.',
            editor: 'dir.',
            editors: 'dir.',
            editedBy: 'sous la direction de',
            translatedBy: 'traduit par',
            in: 'dans',
            edition: 'éd.',
            volume: 'vol.',
            issue: 'no',
            pages: 'p.',
            accessed: 'consulté le',
            online: 'En ligne',
            available: 'Disponible',
            openQuote: "«\u{00A0}",
            closeQuote: "\u{00A0}»",
            punctuationInsideQuotes: false,
            colon: "\u{00A0}: ",
            capitalizeAbbreviations: false,
            beforeCommonEra: 'av. J.-C.',
            months: ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'],
            locators: [
                'page' => ['p.', 'p.'], 'chapter' => ['chap.', 'chap.'], 'section' => ['sect.', 'sect.'],
                'paragraph' => ['paragr.', 'paragr.'], 'volume' => ['vol.', 'vol.'], 'figure' => ['fig.', 'fig.'],
                'line' => ['l.', 'l.'], 'note' => ['n.', 'n.'],
            ],
            ordinalSuffixes: ['=1' => 're', 'default' => 'e'],
        );
    }

    /**
     * The terms of a locale's language: `en_US` and `fr-CA` alike.
     *
     * @throws MissingTerms a language the library holds no terms for
     */
    public static function forLocale(string $locale): self
    {
        return match (strtolower(substr($locale, 0, 2))) {
            'en' => self::english(),
            'fr' => self::french(),
            default => throw MissingTerms::forLocale($locale),
        };
    }

    /**
     * An edition number as an ordinal: « 2nd », « 11th », « 1re », « 2e ».
     */
    public function ordinal(int $number): string
    {
        return $number.($this->ordinalSuffixes['='.$number]
            ?? $this->ordinalSuffixes[$number % 100]
            ?? $this->ordinalSuffixes[$number % 10]
            ?? $this->ordinalSuffixes['default'] ?? '');
    }

    /**
     * The label of a locator, plural when the locator holds a range or a list: « p. », « pp. ».
     */
    public function locatorLabel(Locator $locator, string $value): string
    {
        $plural = 1 === preg_match('/[-–,&]/u', $value);

        return $this->locators[$locator->value][$plural ? 1 : 0] ?? '';
    }

    public function month(int $month): string
    {
        return $this->months[$month - 1] ?? '';
    }
}
