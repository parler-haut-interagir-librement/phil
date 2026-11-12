# Glossary

A `Glossary\Glossary` holds the `Term`s of one pair of languages.

```php
$glossary = new Glossary(LanguageTag::of('en'), LanguageTag::of('fr'), [
    new Term('hit points', ['points de vie', 'PV'], forbidden: ['vie']),
    new Term('Dungeon Master'),                                  // kept as it is
    new Term('Gnome', ['Gnome'], caseSensitive: true, note: 'The people, not the garden ornament.'),
]);
```

| Term | Meaning |
|---|---|
| `source` | The term in the source language, white space normalized |
| `translations` | The allowed translations; none when the term is kept as it is — a name, a brand |
| `forbidden` | Translations refused |
| `caseSensitive` | Whether case matters, both in the source and in the target |
| `note` | A note for translators |
| `forms` | The inflected forms of the term — `hit point` for `hit points` |
| `translationForms` | The inflected forms of its translations — `point de vie` for `points de vie` — or of the term when it is kept |

## Finding terms

`find($source)` gives the `TermHit`s of a source, in reading order: the term, the words as written, and their offset in
characters of the plain text. Terms match as whole words — never inside a longer word — and literally, without regard
to case unless the term says otherwise. Where two terms overlap, the longer one wins: `hit points` before `hit`.

## Checking a target

`check($source, $target)` gives the `TermIssue`s of a target:

| Kind | When |
|---|---|
| `missing` | The target holds the allowed translations — or the kept term — fewer times than the source holds the term; the issue says how many of each |
| `forbidden` | The target holds a refused translation outside an allowed one: `vie` is refused while `points de vie` is allowed |

Only the terms of the source are checked.

## Inflected forms

A term and its translations change in a sentence: one hit point, two hit points. The forms of a term are found as the
term itself, and the forms of its translations count as its translations:

```php
$term = new Term('hit points', ['points de vie'], forms: ['hit point'], translationForms: ['point de vie']);

$term->variants();   // ['hit points', 'hit point'] — what find() looks for
$term->accepted();   // ['points de vie', 'point de vie'] — what check() counts
```

Forms equal to the term or to a translation are left out, and duplicates kept once.

An `Inflector` makes the forms instead: given one, the glossary adds the forms it gives for each term, in the source
language, and for each translation, in the target language; `$term->inflected($inflector, $source, $target)` does the
same for one term. `RegularPlurals` gives the regular plural in English and in French, regional variants included:

| Language | Rule | Examples |
|---|---|---|
| English | The last word: `-es` after `s`, `x`, `z`, `ch`, `sh`; `-ies` after a consonant and `y`; `-s` otherwise | `hit point`, `hit points`; `box`, `boxes`; `city`, `cities` |
| French | Every word before the first preposition: unchanged after `s`, `x`, `z`; `-aux` for `-al`; `-x` after `au`, `eu` and seven nouns in `-ou`; `-s` otherwise, and for `bal`, `bleu`, `festival`, `pneu` and the like | `point de vie`, `points de vie`; `épée longue`, `épées longues`; `cheval`, `chevaux` |

Words with a digit or an apostrophe are left alone, and other languages get no form. Irregular plurals — `mouse`,
`œil` — and other inflections — genders, cases, conjugations — go into the term's forms, or into an inflector of the
application.

```php
$glossary = new Glossary(LanguageTag::of('en'), LanguageTag::of('fr-CA'), $terms, new RegularPlurals());
```

## Exchange

A glossary leaves and arrives as TBX: see [TMX and TBX](tmx-and-tbx.md).
