# Hyphenation

## Liang's algorithm

`Hyphenator` implements the algorithm Frank Liang designed for TeX, written from scratch. A pattern is a string of
letters with digits between them — `.ach4`, `a1b`, `hy3ph` —, the dot marking the start or the end of a word. Every
pattern found in the word, framed by dots, raises the digits between its letters; a break is allowed where the highest
digit is odd, never closer to either end than the language's minimum lengths, and never before a combining mark. A word
listed among the exceptions — `ta-ble`, `project` — breaks where the exception says.

```php
$hyphenator = Hyphenator::for('en_US');
$hyphenator->breaks('hyphenation');          // [2, 6]
$hyphenator->hyphenate('hyphenation', '-');  // hy-phen-ation
$hyphenator->hyphenate('hyphenation');       // with soft hyphens (U+00AD), the default
```

A word whose lower case does not have as many characters as the word itself — `İstanbul` — is left whole. The
minimum lengths of the language can be replaced: `new Hyphenator($patterns, left: 3, right: 3)`; neither is below one.

## Languages

`Dictionary` finds the patterns of a locale through its candidates and a table of aliases, or takes a pattern tag
directly (`de-1901`, `la-x-classic`, `fi-x-school`). Most patterns are those of hyph-utf8 (version 2026-02-21), the
hyphenation patterns of TeX in UTF-8, imported for every language whose licence permits them in an MIT package; each
file is a verbatim copy, and `data/LICENSES` reproduces the header of the source file with its copyright and licence.
Where a file is offered under several licences, the permissive one is used. Seven languages whose TeX patterns carry
a reciprocal licence or none have patterns written for this library, under its MIT licence: see
[Patterns written for this library](#patterns-written-for-this-library).

| Tag | Language | Locales | Left / right | Licence used |
|---|---|---|---|---|
| `af` | Afrikaans | `af` | 1 / 2 | MIT |
| `as` | Assamese | `as` | 1 / 1 | MIT |
| `be` | Belarusian | `be` | 2 / 2 | MIT |
| `bg` | Bulgarian | `bg` | 2 / 2 | BSD-like notice |
| `ca` | Catalan | `ca` | 2 / 2 | LPPL |
| `cop` | Coptic | `cop` | 1 / 1 | MIT |
| `cs` | Czech | `cs` | 2 / 2 | MIT, written for this library |
| `cu` | Church Slavonic | `cu` | 1 / 2 | MIT |
| `cy` | Welsh | `cy` | 2 / 3 | LPPL |
| `da` | Danish | `da` | 2 / 2 | LPPL |
| `de-1901` | German, traditional spelling | — | 2 / 2 | MIT |
| `de-1996` | German, reformed spelling | `de` | 2 / 2 | MIT |
| `de-ch-1901` | German, traditional Swiss spelling | `de_CH`, `de_LI` | 2 / 2 | MIT |
| `el-monoton` | Modern Greek, monotonic spelling | `el` | 1 / 1 | LPPL |
| `el-polyton` | Modern Greek, polytonic spelling | — | 1 / 1 | LPPL |
| `en-gb` | English, British spelling | `en_AU`, `en_GB`, `en_IE`, `en_IN`, `en_NZ`, `en_UK`, `en_ZA` | 2 / 3 | MIT |
| `en-us` | English, American spelling | `en` | 2 / 3 | All-permissive notice |
| `eo` | Esperanto | `eo` | 2 / 2 | LPPL |
| `es` | Spanish | `es` | 2 / 2 | MIT/X11 |
| `et` | Estonian | `et` | 2 / 3 | MIT |
| `eu` | Basque | `eu` | 2 / 2 | Unicode-style data licence |
| `fi` | Finnish | `fi` | 2 / 2 | Free distribution notice |
| `fi-x-school` | Finnish, school hyphenation | — | 1 / 1 | MIT |
| `fr` | French | `fr` | 2 / 2 | MIT |
| `fur` | Friulan | `fur` | 2 / 2 | MIT |
| `ga` | Irish | `ga` | 2 / 3 | MIT |
| `gl` | Galician | `gl` | 2 / 2 | Unlicence |
| `grc` | Ancient Greek | `grc` | 1 / 1 | LPPL |
| `gu` | Gujarati | `gu` | 1 / 1 | MIT |
| `hr` | Croatian | `hr` | 2 / 2 | LPPL |
| `hsb` | Upper Sorbian | `hsb` | 2 / 2 | LPPL |
| `hu` | Hungarian | `hu` | 2 / 2 | MIT, written for this library |
| `hy` | Armenian | `hy` | 2 / 2 | MIT, written for this library; experimental, off by default |
| `ia` | Interlingua | `ia` | 2 / 2 | LPPL |
| `id` | Indonesian | `id` | 2 / 2 | MIT, written for this library |
| `is` | Icelandic | `is` | 2 / 2 | LPPL |
| `it` | Italian | `it` | 2 / 2 | LPPL |
| `ka` | Georgian | `ka` | 1 / 2 | MIT |
| `kk` | Kazakh | `kk` | 2 / 2 | Public domain |
| `kmr` | Kurmanji, Northern Kurdish | `kmr`, `ku` | 2 / 2 | LPPL |
| `kn` | Kannada | `kn` | 1 / 1 | MIT |
| `la` | Latin | `la` | 2 / 2 | MIT |
| `la-x-classic` | Classical Latin | — | 2 / 2 | MIT |
| `la-x-liturgic` | Liturgical Latin | — | 2 / 2 | MIT |
| `lt` | Lithuanian | `lt` | 2 / 2 | MIT |
| `lv` | Latvian | `lv` | 2 / 2 | MIT, written for this library |
| `mk` | Macedonian | `mk` | 2 / 2 | MIT, written for this library; experimental, off by default |
| `ml` | Malayalam | `ml` | 1 / 1 | MIT |
| `mn-cyrl` | Mongolian, Cyrillic script | `mn` | 2 / 2 | LPPL |
| `mr` | Marathi | `mr` | 1 / 1 | MIT |
| `mul-ethi` | Multiple languages using the Ethiopic script | `am`, `ti` | 1 / 1 | MIT |
| `nl` | Dutch | `nl` | 2 / 2 | MIT |
| `nn` | Norwegian Nynorsk, nynorsk | `nn`, `nb`, `no` | 2 / 2 | All-permissive notice |
| `oc` | Occitan | `oc` | 2 / 2 | MIT |
| `or` | Odia, Oriya | `or` | 1 / 1 | MIT |
| `pa` | Panjabi, Punjabi | `pa` | 1 / 1 | MIT |
| `pi` | Pāli | `pi` | 1 / 2 | MIT |
| `pl` | Polish | `pl` | 2 / 2 | MIT |
| `pms` | Piedmontese | `pms` | 2 / 2 | MIT |
| `pt` | Portuguese | `pt` | 2 / 3 | BSD 3-clause licence |
| `rm` | Romansh | `rm` | 2 / 2 | MIT |
| `ro` | Romanian | `ro` | 2 / 2 | MIT, written for this library |
| `ru` | Russian | `ru` | 2 / 2 | LPPL |
| `sa` | Sanskrit | `sa` | 1 / 3 | Free use notice |
| `sh-cyrl` | Serbocroatian, Cyrillic script | `sh_Cyrl`, `sr` | 1 / 2 | LPPL |
| `sh-latn` | Serbocroatian, Latin script | `bs`, `sh`, `sr_Latn` | 2 / 2 | LPPL |
| `sk` | Slovak | `sk` | 2 / 3 | MIT |
| `sl` | Slovenian | `sl` | 2 / 2 | LPPL |
| `sq` | Albanian | `sq` | 2 / 2 | MIT |
| `sv` | Swedish | `sv` | 2 / 2 | LPPL |
| `ta` | Tamil | `ta` | 1 / 1 | MIT |
| `te` | Telugu | `te` | 1 / 1 | MIT |
| `tk` | Turkmen | `tk` | 2 / 2 | MIT |
| `tr` | Turkish | `tr` | 2 / 2 | LPPL |
| `uk` | Ukrainian | `uk` | 2 / 2 | MIT |
| `zh-latn-pinyin` | Mandarin Chinese, pinyin transliteration | `zh_Latn` | 2 / 2 | MIT |

`de` gives the reformed spelling, `de_CH` and `de_LI` the traditional Swiss spelling, `de-1901` the traditional
spelling; `el` gives the monotonic spelling, `el-polyton` the polytonic one; Norwegian Bokmål and Nynorsk share the
patterns of hyph-no, under the Nynorsk tag; Serbian is read through the Serbo-Croatian patterns, in Cyrillic for `sr`
and in Latin script for `sr_Latn`.

## Patterns written for this library

The TeX patterns of Czech (GPL 2 or later only), Hungarian (MPL 1.1, GPL 2 or LGPL 2.1 only), Armenian (LGPL only),
Indonesian (GPL only), Latvian (LGPL 2.1 or GPL 2 only), Macedonian (GPL only) and Romanian (no licence stated) cannot
be shipped in an MIT package. These seven languages have patterns written from scratch instead, from the published
orthographic rules of each language — no pattern, exception list or word list under another licence was read to write
them. The rules are written as data in `tests/Generator/SyllableRules.php`, `tests/Generator/PatternGenerator.php`
turns them into Liang patterns, `php bin/generate-patterns.php` writes the `<tag>.pat.txt` files, and the tests check
that the shipped files are those the rules give.

They are **rules, not a dictionary — less complete than the TeX patterns**. A rule of syllabification sees letters,
not words: it cannot tell a compound or a prefix from a syllable, nor a diphthong from two vowels written alike. Where
the rules of a language depend on that, the patterns allow no break rather than a wrong one, so they miss breaks the
TeX patterns find, and a word outside the rules may still break where a dictionary would not. Each language is tested
on a list of common words that must break exactly where its rules allow.

| Tag | Rules followed | What the patterns do | Left unbroken |
|---|---|---|---|
| `cs` | The rules of the Institute of the Czech Language of the Academy of Sciences for dividing words | One consonant between vowels goes to the next line; of a group, the last, or a stop or fricative followed by `r`, `l` or `ř` (`ses-tra`); `ch` is one letter; a word-initial `r` or `l` between consonants carries a syllable (`vl-na`, `Br-no`) | The prefixes `roz`, `bez`, `nad`, `pod` and `před` before a vowel; clusters of `d` and a liquid, which a prefix may end |
| `hu` | The spelling rules of the Hungarian Academy of Sciences | One consonant between vowels goes to the next line; of a group, only the last (`temp-lom`, `prog-ram`); two vowels side by side are broken (`ki-a-dás`); `cs`, `dz`, `dzs`, `gy`, `ly`, `ny`, `sz`, `ty` and `zs` are single letters | Doubled digraphs, written short and restored at a break (`asszony`), which a soft hyphen cannot do; the prefixes `meg`, `fel` and `szét` before a vowel; compounds, where `sz` and `zs` meet (`egészség`) |
| `hy` | The line-break rules of Armenian school grammars, in the reformed orthography | One consonant between vowels goes to the next line; of a group, the last, with the `յ` that follows a consonant (`գի-տու-թյուն`); `ու` is one vowel | Breaks next to `և`; the first two letters of a word that begins with two consonants, where the vowel is not written |
| `id` | The general spelling guidelines of the Language Development and Cultivation Agency | One consonant between vowels goes to the next line; of two, the second; of three or more, all but the first (`in-stru-men`); `ng`, `ny`, `kh` and `sy` are single consonants; two vowels side by side are broken (`sa-at`) | The diphthongs `ai`, `au`, `ei` and `oi`, and `eu` and `ou`, even where they are two vowels (`main`) |
| `lv` | The spelling rules of the Latvian Academy of Sciences | One consonant between vowels goes to the next line; of a group, the last (`raks-tīt`); `dz` and `dž` are not broken | Diphthongs, `o` included; the prefixes `aiz` and `pār` before a vowel |
| `mk` | The orthography of the Macedonian literary language | One consonant between vowels goes to the next line; of a group, all but the first (`сес-тра`) | A syllabic `р` without a written vowel (`срце`); the prefixes `без`, `раз`, `под`, `над` and `пред` before a vowel |
| `ro` | The orthographic dictionary of the Romanian Academy | One consonant between vowels goes to the next line; of two, the second, except a stop or `f`, `h`, `v` before `l` or `r` (`ta-blou`); of three or more, all but the first, except `lpt`, `mpt`, `mpț`, `ncș`, `nct`, `ncț`, `ndv`, `rct`, `rtf` and `stm` (`sculp-tor`); `ch` and `gh` are single consonants | Vowels side by side, diphthong and hiatus being written alike; a final `i` after a consonant, which is not a syllable (`lupi`, `membri`) |

### Experimental languages

The rules of Armenian and Macedonian are the least certain of the seven: they still wait for a reading by a speaker
of each language. Their patterns ship, but they are **experimental and off by default**: a `Dictionary` serves them
only when it is built with their tags, listed in `Dictionary::EXPERIMENTAL`. Without them, `hy` and `mk` have no
patterns — `supports()` is false, the presets leave `hyphen` out and `patterns()` throws `UnsupportedLanguage`.

```php
$dictionary = new Dictionary(experimental: ['hy']);    // Armenian served, Macedonian still off
$dictionary = new Dictionary(experimental: Dictionary::EXPERIMENTAL);
Typographer::for('hy_AM', dictionary: $dictionary);
```

A tag outside `Dictionary::EXPERIMENTAL` given there changes nothing: the other languages are always served. In
Symfony, `phil/typography-bundle` sets the list with `experimental_hyphenation`.

An application that accepts the licence of the TeX patterns of one of these languages and wants their completeness
loads them itself, as [Patterns of your own](#patterns-of-your-own) shows.

## Thai: word breaks

Thai is written without spaces between words and breaks a line between words without a visible hyphen, which a soft
hyphen cannot express. Its preset runs the `word_break` fixer instead of `hyphen`: `WordBreak` puts a zero-width space
(U+200B) at each line-break opportunity that ICU finds in a run of Thai characters, through `IntlBreakIterator` and
its Thai dictionary, so that a browser without a dictionary of its own still breaks the line between two words. The
fixer runs only where hyphenation is asked for (`Typographer::for('th', hyphenation: false)` leaves it out). `ext-intl`
is already a requirement of the library, for `Normalizer`, so the fixer needs nothing more. A fixer never adds markup,
so the break is a character, not a `<wbr>` element; the two behave alike in a browser.

```php
Typographer::for('th_TH')->fixText('สวัสดีครับ'); // "สวัสดี\u{200B}ครับ"
```

The breaks are those of the ICU the PHP build links to, and may differ slightly from one ICU version to the next.

## Left out

| Language | Why |
|---|---|
| Serbian, Cyrillic patterns of their own (`sr-cyrl`) | GPL only; Serbian is served by `sh-cyrl` and `sh-latn` |
| Zulu (`zu`) | No patterns in hyph-utf8 |

For these tags, and for Thai, `Dictionary::supports()` is false, the presets leave `Hyphen` out, and
`Hyphenator::for()` throws `UnsupportedLanguage`.

## Patterns of your own

`new Dictionary($directory)` reads `<tag>.pat.txt` and `<tag>.hyp.txt` from another directory, for the same tags; a
missing exceptions file means none, and `supports()` is true only when the patterns file is there. Patterns can also
be given directly:

```php
$patterns = Patterns::parse(file_get_contents('hyph-cs.pat.txt'), file_get_contents('hyph-cs.hyp.txt'), 2, 3);
$hyphenator = new Hyphenator($patterns);
```

That is how an application uses patterns under a licence this library does not ship, under its own responsibility.

## Performance

A dictionary reads the patterns of a language once and keeps them: the German patterns, the largest, take about forty
milliseconds to read. `Typographers` shares one dictionary between its typographers; a long-running process should
keep its typographers rather than build them for each request.
