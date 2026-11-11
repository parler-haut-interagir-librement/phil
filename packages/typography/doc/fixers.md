# Fixers

Each fixer implements `Fixer` — `fix(Run $run): Run` — and can be used alone, in a `Typographer` of your own or
through a preset. They are listed here in the order the presets run them; the order matters: quotes are made before
French spaces are placed around them, and hyphenation comes last.

| Name in presets | Class | What it does |
|---|---|---|
| `unicode_normalization` | `UnicodeNormalization` | Normalization form C: `e` and a combining accent become `é` |
| `ellipsis` | `Ellipsis` | `...` and `. . .` become `…` |
| `dimension` | `Dimension` | `1920 x 1080`, `20cm x 30cm`, `6' x 8'` take `×`; `0x1F` stays |
| `dash` | `Dash` | `---` → `—`, `--` → `–`; `1914-1918` → `1914–1918`; ` - ` → the dash of the style |
| `smart_quotes` | `SmartQuotes` | `"…"` and `'…'` become the quotation marks of the language |
| `curly_quote` | `CurlyQuote` | Every remaining `'` becomes the apostrophe `’` |
| `no_space_before_comma` | `NoSpaceBeforeComma` | `word , word` and `word ,word` become `word, word` |
| `space_before_punctuation` | `SpaceBeforePunctuation` | The French spaces of `: ; ! ?` and `« »` |
| `trademark` | `Trademark` | `(tm)` → `™`, `(r)` → `®`, `(c) 2026` → `© 2026`, `(p) 1999` → `℗ 1999` |
| `unit` | `Unit` | The space of `50 km`, `20 °C`, `75 %`, `12,50 $` becomes a no-break space |
| `hyphen` | `Hyphen` | Soft hyphens in words of five letters or more, where the patterns allow |
| `word_break` | `WordBreak` | Zero-width spaces between Thai words |

## Ellipsis

Three full stops close together or spaced by single spaces. Four stops are an ellipsis and a full stop.

## Dimension

The letter `x` or `X`, with or without a space on either side, between a number and a number. The first number may
carry a unit — `mm`, `cm`, `dm`, `m`, `km`, `in`, `ft`, `px`, `pt`, `em`, `rem` — or a foot or inch mark. A chain such
as `10x20x30` is fixed throughout. A hexadecimal number (`0x1F`), a number after a letter (`A4x5`) or after a decimal
mark is left alone.

## Dash

| Written | Becomes |
|---|---|
| `---` | em dash `—` |
| `--` | en dash `–` |
| `1914-1918`, `12-15`, `(5-7)` | en dash between numbers of one to four digits |
| `2026-11-06`, `555-123-4567`, `1.5-2` | unchanged: a chain or a decimal number is not a range |
| `this - that` | `this – that` (`DashStyle::Spaced`) or `this—that` (`DashStyle::Closed`) |

A phone number written in two groups, `555-1234`, looks like a range and gets an en dash; write it with a no-break
hyphen (U+2011) or leave the fixer out where such numbers are common.

## Smart quotes

A double quote opens after the start of the block, a space or an opening mark — `( [ { « “ ‘ „ ‹ - – — / >` or another
quote — when a character follows it; otherwise it closes. `>` counts as an opening mark because a tag escaped as text,
`&lt;b&gt;"word"`, ends with it, as does a marker of quoted text. A single quote is an inner quotation mark only when it
opens in that position and a matching single quote closes before a space, a punctuation mark or `<`; every other single
quote is an apostrophe, left to `CurlyQuote`. The marks come from a `QuoteStyle`, English by default:

```php
new SmartQuotes(new QuoteStyle('«', '»', '‹', '›'));
```

Known limits: `rock 'n' roll` is read as an inner quotation of `n`; a double quote inside a double quotation is a
quotation of its own, never an inner one.

## Curly quote

A plain replacement of `'` by `’`: placed after `SmartQuotes`, it turns the apostrophes that fixer left. Used alone, it
turns every straight single quote.

## No space before a comma

Spaces before a comma are removed; when a letter follows the comma directly, a space is put after it. `1,5` and
`a ,5` keep their digits together.

## French spacing

`SpaceBeforePunctuation` writes the space before high punctuation that ends a word — whatever the author typed: none,
a space, a no-break space, a thin space — and inside guillemets. See [Locales](locales.md#french-spacing) for the
rules of France and of Canada. The colon of `12:30` or of `https://` is not followed by a space and is left alone;
`?!` takes a single space, before the first mark.

## Trademark

`(tm)` and `(r)`, in either case, are attached to the word before them. `(c)` and `(p)` become `©` and `℗` only when a
year follows, held to it by a no-break space, so the item `(c)` of a list stays as written; `© 2026` gets its no-break
space too.

## Unit

Only an existing space is changed — the fixer never adds one. The units are listed in `Unit::UNITS`: SI units and
their common multiples, data sizes, typographic units, speeds, temperatures, percentages and currency signs. A word
that merely begins like a unit (`5 mice`) is left alone.

## Hyphen

`Hyphen` takes a `Hyphenator` and a minimum length, five characters by default. A word is a run of letters and
combining marks; digits, emoji and symbols end it. A word that already holds a soft hyphen is left as its author wrote
it, so fixing twice gives the same text. See [Hyphenation](hyphenation.md).

## Word break

`WordBreak` puts a zero-width space (U+200B) between the words of each run of Thai characters, at the line-break
opportunities ICU finds with its Thai dictionary, never before the repetition mark `ๆ`. Other scripts are left alone,
and a zero-width space already written ends a run, so fixing twice gives the same text. See
[Hyphenation](hyphenation.md#thai-word-breaks).

## Writing a fixer

```php
final readonly class NoBreakAfterShortWords implements Fixer
{
    public function fix(Run $run): Run
    {
        return $run->replace(static fn (string $text): string => preg_replace('/\b(\p{L}{1,2}) /u', "\$1\u{A0}", $text) ?? $text);
    }
}
```

`Run::replace()` gives the fix the part between its neighbouring characters and keeps the result only if they are
unchanged; `Run::map()` gives the part and its neighbours separately; `Run::characters()` gives every character of
the run at once. A fixer must only rewrite text: what it returns is written into text nodes, so `<` stays a character.
