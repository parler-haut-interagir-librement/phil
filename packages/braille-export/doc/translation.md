# Translation

## The port

```php
interface Translator
{
    public function supports(string $language, Contraction $contraction): bool;

    /** @param list<string> $texts @return list<string> */
    public function translate(array $texts, string $language, Contraction $contraction): array;
}
```

A text is one line of print; its braille comes back as Unicode braille patterns, U+2800 to U+28FF, one character per
cell, the space between two words the blank cell U+2800. `BrailleExporter` sends the whole document in one call, so
that a translator that starts a program starts it once, and checks the answer: as many texts as it sent, each made of
braille cells only.

`Contraction::Uncontracted` is letter for letter — grade 1, « braille intégral »; `Contraction::Contracted` uses the
contractions of the language — grade 2, « braille abrégé ».

## Uncontracted French and English

`UncontractedTranslator` writes uncontracted braille in PHP. The language is read from the primary subtag of its tag:
`fr`, `fr-CA` and `fr_BE` are French, `en` and `en-GB` English.

| | French: Code braille français uniformisé | English: Unified English Braille |
|---|---|---|
| Letters | a to z, and à â ç è é ê ë î ï ô ù û ü œ as their own cells | a to z; an accented letter as its modifier, then its letter |
| A capital | dots 4-6 before the letter | dot 6 before the letter |
| A word in capitals | dots 4-6 twice before the word | dot 6 twice before the word |
| A number | dot 6, then its digits in the Antoine notation | dots 3-4-5-6, then its digits as the letters a to j |
| Within a number | a comma or a point followed by a digit | a comma or a point followed by a digit |
| After a number | nothing | dots 5-6 before a letter a to j |
| Punctuation | the common marks, quotes and dashes | the common marks, brackets, quotes, dashes, and the signs of two cells such as `@`, `%` or `€` |

A letter with an accent the code has no sign for is written as its base letter: `ñ` is `n` in French. Any other
character without braille is left out; `new UncontractedTranslator(strict: true)` throws `UntranslatableCharacter`
instead, so that an application finds what its text holds.

The translator covers the text of most documents. A text that needs the full rules of a code — mathematics, computer
notation, foreign languages within the text, emphasis — needs a translator with full tables.

## Contracted braille and other languages

Contracted braille follows hundreds of rules per language, kept in the tables of a braille translation program.
`phil/braille-export-liblouis` is the translator that runs liblouis: contracted and uncontracted braille of every
language liblouis has tables for.

## A chain of translators

`ChainTranslator` asks each translator in turn whether it supports the language and the contraction, and gives the
batch to the first that does:

```php
use Phil\BrailleExport\Translation\ChainTranslator;
use Phil\BrailleExport\Translation\UncontractedTranslator;

$translator = new ChainTranslator($liblouis, new UncontractedTranslator());
```

When none does, it throws `UnsupportedTranslation`.

## Unicode braille

`Braille` holds the helpers of the form every text takes once translated:

| Member | What it gives |
|---|---|
| `Braille::cell('1245')` | The cell of some dots, here the letter g; `''` is the blank cell. |
| `Braille::cells('6', '6')` | The cells of a sign of several cells. |
| `Braille::isBraille($text)` | Whether a text holds braille cells and nothing else. |
| `Braille::length($braille)` | Its number of cells. |
| `Braille::BLANK`, `Braille::HYPHEN` | The blank cell, and dots 3-6. |
