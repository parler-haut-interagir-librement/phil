# Comparing texts

`TextDiff` compares two texts and returns a `Diff`: the operations that make the new text out of the old one.

```php
$differ = new TextDiff();

$diff = $differ->words('one two three', 'one 2 three');
$diff = $differ->lines($before, $after);
$diff = $differ->paragraphs($before, $after);
$diff = $differ->html($beforeHtml, $afterHtml);
$diff = $differ->compare($before, $after, Tokenizer::Lines);   // the same, by its tokenizer
```

## Tokenizers

A text is compared token by token. The tokens of a text, put end to end, give it back byte for byte: white space
and line breaks are tokens too, so nothing is lost and both texts can always be rebuilt from the difference.

| `Tokenizer` | Tokens | For |
|---|---|---|
| `Words` | runs of letters, marks, digits and `_`; runs of white space; every other character alone (a whole grapheme, so an emoji with its modifier stays one); each character of a script written without spaces between words alone (see below) | prose, titles, short fields |
| `Lines` | each line without its break; each break (`\r\n`, `\r` or `\n`) | code, configuration, poetry |
| `Paragraphs` | each paragraph, its single line breaks inside it; each run of blank lines between two paragraphs | long texts in Markdown or plain text, where a word-level view is too fine |
| `Html` | as `Words`, but a tag with its attributes, a comment or a character reference (`&amp;`, `&#233;`) is one token | HTML: rendered pages, the inline HTML of UJO fields |

Chinese, Japanese, Thai, Lao, Khmer and Burmese put no space between words, so a run of letters there is a whole
sentence. In `Words` and `Html`, each character of the Han, Hiragana, Katakana, Thai, Lao, Khmer and Myanmar scripts —
and the prolonged sound mark `ー` of Japanese — is a token alone, with the marks that belong to it: a grapheme, as
Unicode cuts them. Such a text is compared character by character, a changed word reads as its changed characters,
and the Latin words, digits and Hangul around it stay whole.

```php
$differ->words('私は猫が好き', '私は犬が好き')->changes();   // one replacement, '猫' → '犬', at 2/2
```

A text that is not valid UTF-8 cannot be tokenized: `InvalidText` is thrown.

## Operations

`Diff::$operations` is a list of `Operation`s, each a `kind` — `OperationKind::Equal`, `Delete` or `Insert` — and the
`tokens` it keeps, deletes or inserts; `text()` puts them end to end. The list is normalized:

- **kept runs and changes alternate**: two kept runs are never next to each other, and neither are two changes;
- **in each change, deleted tokens come before inserted ones**;
- **white space alone between two changes is part of them**: `a b` → `c d` is one replacement of `a b` by `c d`,
  not two replacements around a kept space, which is how a reader sees a rewritten phrase. Kept white space at the
  start or the end, or next to a kept word, stays kept.

`Diff::old()` and `Diff::new()` give the two texts back; `isIdentical()` says whether they are the same.

## Changes

`Diff::changes()` lists what changed, in reading order, as `Change`s:

| `ChangeKind` | `old` | `new` |
|---|---|---|
| `Insertion` | `''` | the inserted text |
| `Deletion` | the deleted text | `''` |
| `Replacement` | the deleted text | the text inserted in its place |

`oldOffset` and `newOffset` say where the change happens in each text, in code points from the start, so they can be
used with `mb_substr()` or in a browser's `String` indices for characters of the Basic Multilingual Plane.

```php
foreach ($differ->words('Été: un deux trois quatre', 'Été: zéro un trois cinq')->changes() as $change) {
    // insertion '' → 'zéro ' at 5/5, deletion 'deux ' → '' at 8/13, replacement 'quatre' → 'cinq' at 19/19
}
```

## The algorithm and its bound

The tokens both texts start with, and those they end with, are set aside first. On what is left, Eugene W. Myers'
algorithm ("An O(ND) Difference Algorithm and Its Variations", 1986) follows, for 0, 1, 2… edits, the furthest path
on each diagonal of the edit graph, keeps each frontier, and walks back through them: the result is a shortest script —
no other turns the old text into the new one with fewer deleted and inserted tokens. When several scripts are equally
short, the one found keeps the earliest matches of the old text.

Its time grows with the length of the texts times the number of edits, and its memory with the square of the number of
edits. The constructor's `$maxEdits` bounds that number: when the middles of the two texts need more edits, the old
middle is given as deleted and the new one as inserted, whole. A text rewritten from end to end reads that way anyway.
The default, a thousand, keeps a comparison within a few megabytes; zero still sets the common start and end aside. A
negative bound throws `InvalidConfiguration`.

The script is shortest in tokens, not in characters: comparing by words, a word changed by one letter is a replaced
word.
