# Locales

A locale is read by `Locale::parse()` from a language of two or three letters, then an optional script of four
letters and an optional region of two letters or three digits, joined by `_` or `-` in any case: `fr`, `fr_CA`,
`fr-ca`, `sr_Latn_RS`, `es_419`. Everything that depends on the locale looks it up from the most precise form to the
language alone: `sr_Latn_RS`, `sr_RS`, `sr_Latn`, `sr`.

## Presets

`Preset::names($locale)` gives the names of the fixers recommended for a locale, in the order they run, and
`Preset::fixers($locale)` the fixers themselves, set for it. Every language gets the same rules — Unicode
normalization, ellipsis, dimension, dash, smart quotes, apostrophe, no space before a comma, legal signs, units —;
French adds its spacing; hyphenation comes last when the library has patterns for the language and `$hyphenation` is
true, and Thai takes the zero-width spaces of `word_break` in its place, under the same flag.

```php
Preset::names(Locale::parse('fr_CA'));
// unicode_normalization, ellipsis, dimension, dash, smart_quotes, curly_quote, no_space_before_comma,
// space_before_punctuation, trademark, unit, hyphen

$typographer = Typographer::for('de_CH');              // the preset of the locale
$typographer = Typographer::for('de_CH', hyphenation: false);
```

`Typographers` keeps one typographer per locale, for 64 locales at most so that locales taken from requests cannot
grow the memory of a long-running process, and lets an application replace the preset of some locales by fixer
names; the fixers configured for `fr` serve `fr_BE` and `fr_CH` too:

```php
$typographers = new Typographers('fr_CA', ['en' => ['ellipsis', 'smart_quotes', 'curly_quote']]);
$typographers->for()->fix($html);       // fr_CA, its preset
$typographers->for('en_GB')->fix($html); // the three fixers configured for en
```

## Quotation marks

`QuoteStyles::for($locale)` gives the marks of the locale, after the delimiters of the Unicode CLDR; a language that
is not listed gets the English marks.

| Locales | Primary | Inner |
|---|---|---|
| `en`, `af`, `da`, `eo`, `ga`, `gl`, `ko`, `mn`, `nl`, `pt`, `th`, `tr`, `vi`, `zh`, `es_419`, `es_MX`, `es_US` | “ ” | ‘ ’ |
| `fr`, `ca`, `el`, `es`, `eu`, `it`, `kk`, `pt_PT`, `sq` | « » | “ ” |
| `fr_CH`, `de_CH`, `de_LI`, `it_CH`, `rm`, `am`, `fa` | « » | ‹ › |
| `de`, `cs`, `et`, `hr`, `is`, `lt`, `mk`, `sk`, `sl` | „ “ | ‚ ‘ |
| `ru`, `uk`, `be` | « » | „ “ |
| `pl`, `ro` | „ ” | « » |
| `hu` | „ ” | » « |
| `bg` | „ “ | „ “ |
| `bs`, `sr` | „ “ | ‘ ’ |
| `ka` | „ “ | « » |
| `lv` | “ ” | „ “ |
| `nb`, `nn`, `no` | « » | ‘ ’ |
| `fi`, `sv` | ” ” | ’ ’ |
| `he` | ” ” | ’ ’ |
| `ar` | ” “ | ’ ‘ |
| `cy` | ‘ ’ | “ ” |
| `hy` | « » | « » |
| `ja`, `zh_Hant`, `zh_HK`, `zh_TW` | 「 」 | 『 』 |

## Dash styles

`DashStyle::for($locale)` gives the closed em dash — `this—that` — to English without a region and to American,
Canadian and Philippine English, and the spaced en dash — `this – that` — to every other locale, British English
included.

## French spacing

`FrenchSpacing::for($locale)` gives the rules of Canada to `fr_CA` and those of France to every other French locale.

| Before or inside | France, Belgium, Switzerland… | Canada (Office québécois de la langue française) |
|---|---|---|
| `;` `!` `?` | narrow no-break space (U+202F) | no space |
| `:` | no-break space (U+00A0) | no-break space (U+00A0) |
| `« »` | no-break space (U+00A0) | no-break space (U+00A0) |

The preset gives `SpaceBeforePunctuation` to every locale whose language is `fr`; another language that wants it adds
the fixer itself.
