<?php

declare(strict_types=1);

namespace Phil\Typography;

use Phil\Typography\Exception\UnknownFixer;
use Phil\Typography\Exception\UnsupportedLanguage;
use Phil\Typography\Fixer\CurlyQuote;
use Phil\Typography\Fixer\Dash;
use Phil\Typography\Fixer\DashStyle;
use Phil\Typography\Fixer\Dimension;
use Phil\Typography\Fixer\Ellipsis;
use Phil\Typography\Fixer\Fixer;
use Phil\Typography\Fixer\FrenchSpacing;
use Phil\Typography\Fixer\Hyphen;
use Phil\Typography\Fixer\NoSpaceBeforeComma;
use Phil\Typography\Fixer\SmartQuotes;
use Phil\Typography\Fixer\SpaceBeforePunctuation;
use Phil\Typography\Fixer\Trademark;
use Phil\Typography\Fixer\UnicodeNormalization;
use Phil\Typography\Fixer\Unit;
use Phil\Typography\Fixer\WordBreak;
use Phil\Typography\Hyphenation\Dictionary;
use Phil\Typography\Hyphenation\Hyphenator;
use Phil\Typography\Quote\QuoteStyles;

/**
 * The fixers recommended for a locale, by name and in the order they must run: every language gets the same rules,
 * each set for it — its quotes, its dashes —, French adds its spacing, and hyphenation comes last when the library has
 * patterns for the language, or, for Thai, the zero-width spaces between its words.
 */
final class Preset
{
    /**
     * Every fixer, by name, in the order they run.
     */
    public const array FIXERS = [
        'unicode_normalization',
        'ellipsis',
        'dimension',
        'dash',
        'smart_quotes',
        'curly_quote',
        'no_space_before_comma',
        'space_before_punctuation',
        'trademark',
        'unit',
        'hyphen',
        'word_break',
    ];

    /**
     * The names of the fixers recommended for the locale.
     *
     * @return list<string>
     */
    public static function names(Locale $locale, bool $hyphenation = true, ?Dictionary $dictionary = null): array
    {
        $dictionary ??= new Dictionary();

        return array_values(array_filter(self::FIXERS, static fn (string $name): bool => match ($name) {
            'space_before_punctuation' => 'fr' === $locale->language,
            'hyphen' => $hyphenation && $dictionary->supports($locale),
            'word_break' => $hyphenation && WordBreak::LANGUAGE === $locale->language,
            default => true,
        }));
    }

    /**
     * The fixers recommended for the locale, ready to run.
     *
     * @return list<Fixer>
     */
    public static function fixers(Locale $locale, bool $hyphenation = true, ?Dictionary $dictionary = null): array
    {
        $dictionary ??= new Dictionary();

        return array_map(static fn (string $name): Fixer => self::fixer($name, $locale, $dictionary), self::names($locale, $hyphenation, $dictionary));
    }

    /**
     * One fixer, by name, set for the locale.
     *
     * @throws UnknownFixer
     * @throws UnsupportedLanguage when the hyphenation patterns of the locale are asked for and there are none
     */
    public static function fixer(string $name, Locale $locale, ?Dictionary $dictionary = null): Fixer
    {
        return match ($name) {
            'unicode_normalization' => new UnicodeNormalization(),
            'ellipsis' => new Ellipsis(),
            'dimension' => new Dimension(),
            'dash' => new Dash(DashStyle::for($locale)),
            'smart_quotes' => new SmartQuotes(QuoteStyles::for($locale)),
            'curly_quote' => new CurlyQuote(),
            'no_space_before_comma' => new NoSpaceBeforeComma(),
            'space_before_punctuation' => new SpaceBeforePunctuation(FrenchSpacing::for($locale)),
            'trademark' => new Trademark(),
            'unit' => new Unit(),
            'hyphen' => new Hyphen(Hyphenator::for($locale, $dictionary)),
            'word_break' => new WordBreak(),
            default => throw UnknownFixer::of($name, self::FIXERS),
        };
    }
}
