<?php

declare(strict_types=1);

namespace Phil\Typography\Hyphenation;

use Phil\Typography\Exception\InvalidLocale;
use Phil\Typography\Exception\UnsupportedLanguage;
use Phil\Typography\Locale;

/**
 * The hyphenation patterns shipped with the library, found by locale or by pattern tag, read once and kept: those
 * imported from hyph-utf8 under licences that permit them in an MIT package, and those written for this library from
 * the syllabification rules of languages whose TeX patterns are under a reciprocal licence or none (`data/LICENSES`
 * gives each one). The experimental languages, whose written rules still wait for a reading by a speaker, are served
 * only when the dictionary is built with them.
 */
final class Dictionary
{
    /**
     * Each pattern tag, to its language, the fewest letters before and after a break, and the licence it is used under.
     *
     * @var array<string, array{string, int, int, string}>
     */
    public const array LANGUAGES = [
        'af' => ['Afrikaans', 1, 2, 'MIT'],
        'as' => ['Assamese', 1, 1, 'MIT'],
        'be' => ['Belarusian', 2, 2, 'MIT'],
        'bg' => ['Bulgarian', 2, 2, 'BSD-like notice'],
        'ca' => ['Catalan', 2, 2, 'LPPL'],
        'cop' => ['Coptic', 1, 1, 'MIT'],
        'cs' => ['Czech', 2, 2, 'MIT, written for this library'],
        'cu' => ['Church Slavonic', 1, 2, 'MIT'],
        'cy' => ['Welsh', 2, 3, 'LPPL'],
        'da' => ['Danish', 2, 2, 'LPPL'],
        'de-1901' => ['German, traditional spelling', 2, 2, 'MIT'],
        'de-1996' => ['German, reformed spelling', 2, 2, 'MIT'],
        'de-ch-1901' => ['German, traditional Swiss spelling', 2, 2, 'MIT'],
        'el-monoton' => ['Modern Greek, monotonic spelling', 1, 1, 'LPPL'],
        'el-polyton' => ['Modern Greek, polytonic spelling', 1, 1, 'LPPL'],
        'en-gb' => ['English, British spelling', 2, 3, 'MIT'],
        'en-us' => ['English, American spelling', 2, 3, 'All-permissive notice'],
        'eo' => ['Esperanto', 2, 2, 'LPPL'],
        'es' => ['Spanish', 2, 2, 'MIT/X11'],
        'et' => ['Estonian', 2, 3, 'MIT'],
        'eu' => ['Basque', 2, 2, 'Unicode-style data licence'],
        'fi' => ['Finnish', 2, 2, 'Free distribution notice'],
        'fi-x-school' => ['Finnish, school hyphenation', 1, 1, 'MIT'],
        'fr' => ['French', 2, 2, 'MIT'],
        'fur' => ['Friulan', 2, 2, 'MIT'],
        'ga' => ['Irish', 2, 3, 'MIT'],
        'gl' => ['Galician', 2, 2, 'Unlicence'],
        'grc' => ['Ancient Greek', 1, 1, 'LPPL'],
        'gu' => ['Gujarati', 1, 1, 'MIT'],
        'hr' => ['Croatian', 2, 2, 'LPPL'],
        'hsb' => ['Upper Sorbian', 2, 2, 'LPPL'],
        'hu' => ['Hungarian', 2, 2, 'MIT, written for this library'],
        'hy' => ['Armenian', 2, 2, 'MIT, written for this library'],
        'ia' => ['Interlingua', 2, 2, 'LPPL'],
        'id' => ['Indonesian', 2, 2, 'MIT, written for this library'],
        'is' => ['Icelandic', 2, 2, 'LPPL'],
        'it' => ['Italian', 2, 2, 'LPPL'],
        'ka' => ['Georgian', 1, 2, 'MIT'],
        'kk' => ['Kazakh', 2, 2, 'Public domain'],
        'kmr' => ['Kurmanji, Northern Kurdish', 2, 2, 'LPPL'],
        'kn' => ['Kannada', 1, 1, 'MIT'],
        'la' => ['Latin', 2, 2, 'MIT'],
        'la-x-classic' => ['Classical Latin', 2, 2, 'MIT'],
        'la-x-liturgic' => ['Liturgical Latin', 2, 2, 'MIT'],
        'lt' => ['Lithuanian', 2, 2, 'MIT'],
        'lv' => ['Latvian', 2, 2, 'MIT, written for this library'],
        'mk' => ['Macedonian', 2, 2, 'MIT, written for this library'],
        'ml' => ['Malayalam', 1, 1, 'MIT'],
        'mn-cyrl' => ['Mongolian, Cyrillic script', 2, 2, 'LPPL'],
        'mr' => ['Marathi', 1, 1, 'MIT'],
        'mul-ethi' => ['Multiple languages using the Ethiopic script', 1, 1, 'MIT'],
        'nl' => ['Dutch', 2, 2, 'MIT'],
        'nn' => ['Norwegian Nynorsk, nynorsk', 2, 2, 'All-permissive notice'],
        'oc' => ['Occitan', 2, 2, 'MIT'],
        'or' => ['Odia, Oriya', 1, 1, 'MIT'],
        'pa' => ['Panjabi, Punjabi', 1, 1, 'MIT'],
        'pi' => ['Pāli', 1, 2, 'MIT'],
        'pl' => ['Polish', 2, 2, 'MIT'],
        'pms' => ['Piedmontese', 2, 2, 'MIT'],
        'pt' => ['Portuguese', 2, 3, 'BSD 3-clause licence'],
        'rm' => ['Romansh', 2, 2, 'MIT'],
        'ro' => ['Romanian', 2, 2, 'MIT, written for this library'],
        'ru' => ['Russian', 2, 2, 'LPPL'],
        'sa' => ['Sanskrit', 1, 3, 'Free use notice'],
        'sh-cyrl' => ['Serbocroatian, Cyrillic script', 1, 2, 'LPPL'],
        'sh-latn' => ['Serbocroatian, Latin script', 2, 2, 'LPPL'],
        'sk' => ['Slovak', 2, 3, 'MIT'],
        'sl' => ['Slovenian', 2, 2, 'LPPL'],
        'sq' => ['Albanian', 2, 2, 'MIT'],
        'sv' => ['Swedish', 2, 2, 'LPPL'],
        'ta' => ['Tamil', 1, 1, 'MIT'],
        'te' => ['Telugu', 1, 1, 'MIT'],
        'tk' => ['Turkmen', 2, 2, 'MIT'],
        'tr' => ['Turkish', 2, 2, 'LPPL'],
        'uk' => ['Ukrainian', 2, 2, 'MIT'],
        'zh-latn-pinyin' => ['Mandarin Chinese, pinyin transliteration', 2, 2, 'MIT'],
    ];

    /**
     * The pattern tags served only when they are asked for by name: Armenian and Macedonian, whose rules are the least
     * certain of those written for this library.
     *
     * @var list<string>
     */
    public const array EXPERIMENTAL = ['hy', 'mk'];

    /**
     * The locales whose patterns are not under their own language code.
     *
     * @var array<string, string>
     */
    public const array ALIASES = [
        'am' => 'mul-ethi',
        'bs' => 'sh-latn',
        'de' => 'de-1996',
        'de_CH' => 'de-ch-1901',
        'de_LI' => 'de-ch-1901',
        'el' => 'el-monoton',
        'en' => 'en-us',
        'en_AU' => 'en-gb',
        'en_GB' => 'en-gb',
        'en_IE' => 'en-gb',
        'en_IN' => 'en-gb',
        'en_NZ' => 'en-gb',
        'en_UK' => 'en-gb',
        'en_ZA' => 'en-gb',
        'ku' => 'kmr',
        'mn' => 'mn-cyrl',
        'nb' => 'nn',
        'no' => 'nn',
        'sh' => 'sh-latn',
        'sh_Cyrl' => 'sh-cyrl',
        'sr' => 'sh-cyrl',
        'sr_Latn' => 'sh-latn',
        'ti' => 'mul-ethi',
        'zh_Latn' => 'zh-latn-pinyin',
    ];

    /**
     * @var array<string, Patterns>
     */
    private array $loaded = [];

    private readonly string $directory;

    /**
     * @var array<string, true>
     */
    private readonly array $disabled;

    /**
     * @param string|null  $directory    where the `<tag>.pat.txt` and `<tag>.hyp.txt` files are; the library's own when null
     * @param list<string> $experimental the tags of `EXPERIMENTAL` to serve; the others are ignored
     */
    public function __construct(?string $directory = null, array $experimental = [])
    {
        $this->directory = $directory ?? \dirname(__DIR__, 2).'/data/hyphenation';
        $this->disabled = array_fill_keys(array_diff(self::EXPERIMENTAL, $experimental), true);
    }

    /**
     * The pattern tag of a locale or of a tag, or null when there are no patterns for it or they are experimental and not
     * asked for.
     *
     * @throws InvalidLocale when it is neither a tag nor a locale
     */
    public function tag(Locale|string $language): ?string
    {
        if (\is_string($language) && isset(self::LANGUAGES[$language])) {
            return $this->served($language);
        }

        foreach (($language instanceof Locale ? $language : Locale::parse($language))->candidates() as $candidate) {
            $tag = self::ALIASES[$candidate] ?? $candidate;

            if (isset(self::LANGUAGES[$tag])) {
                return $this->served($tag);
            }
        }

        return null;
    }

    private function served(string $tag): ?string
    {
        return isset($this->disabled[$tag]) ? null : $tag;
    }

    /**
     * Whether there are patterns for it in the directory.
     *
     * @throws InvalidLocale
     */
    public function supports(Locale|string $language): bool
    {
        $tag = $this->tag($language);

        return null !== $tag && is_file($this->directory.'/'.$tag.'.pat.txt');
    }

    /**
     * @throws InvalidLocale
     * @throws UnsupportedLanguage when there are no patterns for it, or their file cannot be read
     */
    public function patterns(Locale|string $language): Patterns
    {
        $tag = $this->tag($language) ?? throw UnsupportedLanguage::of(\is_string($language) ? $language : $language->__toString());

        return $this->loaded[$tag] ??= $this->load($tag);
    }

    private function load(string $tag): Patterns
    {
        $patterns = @file_get_contents($this->directory.'/'.$tag.'.pat.txt');

        if (false === $patterns) {
            throw UnsupportedLanguage::of($tag);
        }

        $exceptions = @file_get_contents($this->directory.'/'.$tag.'.hyp.txt');
        [, $left, $right] = self::LANGUAGES[$tag];

        return Patterns::parse($patterns, false === $exceptions ? '' : $exceptions, $left, $right);
    }
}
