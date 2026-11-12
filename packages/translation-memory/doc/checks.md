# Quality checks

The checks of the `Check` namespace read a translated segment and report what looks wrong with its target against its
source: a lost full stop, a missing tag, a plural form the target language needs, a target copied from the source, a
label too wide for its button. They never change a segment; a translator reads the issues and decides.

```php
use Phil\TranslationMemory\Check\Checked;
use Phil\TranslationMemory\Check\Checker;
use Phil\TranslationMemory\Check\Flags;
use Phil\TranslationMemory\LanguageTag;

$checker = Checker::defaults();

$issues = $checker->check(new Checked(
    source: '<b>Ready?</b>',
    target: 'Prêt',
    sourceLanguage: LanguageTag::of('en'),
    targetLanguage: LanguageTag::of('fr-CA'),
    flags: Flags::parse('ignore-same'),
));

foreach ($issues as $issue) {
    echo $issue->check, ': ', $issue->message, "\n";
}
// punctuation: The source ends with “?”, the target with no punctuation.
// tags: The target lacks the tag <b>.
// tags: The target lacks the tag </b>.
```

`Checked::segment($segment, $sourceLanguage, $targetLanguage, $flags)` builds the same input from a `Segment`, and gives
`null` when the segment has no target.

## The checks

| Name | Class | What it reports |
|---|---|---|
| `punctuation` | `Punctuation` | The source and the target end with different marks — full stop, `!`, `?`, `:`, `;`, ellipsis, comma — or one ends with a mark and the other with none. Full-width marks count as their Latin counterparts, `...` as `…`; the spaces before French marks and the closing quotes and brackets after the mark are looked past |
| `spaces` | `Spaces` | The target starts or ends with white space where the source does not, or the reverse; the target holds two spaces in a row and the source none |
| `tags` | `Tags` | A tag of the source missing from the target, or written fewer times; a tag in the target the source does not hold. Tags are compared as written, attributes included |
| `plurals` | `Plurals` | The source holds an ICU plural, `{count, plural, one {…} other {…}}`, and the target has no plural of the same name, lacks a category its language needs for whole numbers, or has braces that do not balance |
| `same` | `SameAsSource` | The plain target is the plain source, word for word and case included, and the source holds a letter |
| `max-size` | `MaxSize` | The widest line of the target is drawn wider than its limit, in pixels |
| `glyphs` | `Glyphs` | A character of the target that the template's font cannot draw |

`Checker::defaults()` runs the first five. Given a `TextWidth` — and a limit, or segments that carry one —, it adds
`max-size`; given a `Phil\Fonts\Font`, it adds `glyphs`. `new Checker([...])` runs any list of checks, the
application's own included, in the order given.

### Plural categories

`Plurals::categories($language)` gives the categories a language needs for whole numbers, from the CLDR cardinal
rules, by primary language:

| Categories | Languages |
|---|---|
| `one`, `other` | af, bg, ca, da, de, el, en, es, et, eu, fi, fr, gl, hu, it, nb, nl, nn, no, pt, sv, tr |
| `one`, `few`, `other` | bs, cs, hr, lt, sk, sr |
| `one`, `few`, `many`, `other` | be, pl, ru, uk |
| `one`, `two`, `other` | he |
| `one`, `two`, `few`, `many`, `other` | ga |
| `zero`, `one`, `two`, `few`, `many`, `other` | ar, cy |
| `other` | any other language |

Explicit cases such as `=0` are welcome and never required. Text between apostrophes is literal, as ICU reads it, and
arguments nest sixteen deep at most.

## Flags

A segment's `Flags` tune its checks. `ignore-<name>` dismisses a check on that segment — `ignore-same` for a name kept
as it is, `ignore-tags` for a target that drops a tag on purpose —, and `max-size:<pixels>` sets the widest the target
may be drawn.

```php
$flags = Flags::parse('ignore-same, max-size:240');

$flags->dismisses('same');        // true
$flags->value('max-size');        // '240'
$flags->with('ignore-tags');      // a copy with one more flag
$flags->without('max-size');      // a copy without it
$flags->toString();               // 'ignore-same, max-size:240'
```

A name is a lower case letter and up to 31 more lower case letters, digits and hyphens; a value is 1 to 32 letters,
digits, dots, underscores and hyphens; a segment holds at most 32 flags, and the same name given twice keeps the last
value. Anything else throws `InvalidConfiguration`. The library does not store flags: the application keeps them with
its segments, as `phil/translation-bundle` does.

## Rendered size in pixels

`MaxSize` measures each line of the target — cut at `<br>` and at line breaks, tags left out, character references
decoded — with a `TextWidth`, and reports the widest when it passes the limit: the segment's `max-size` flag, or else
the limit `MaxSize` is given. Without either, it passes.

`AdvanceWidths` is the `TextWidth` of a font: the advance of each code point in font units, the font's units per em,
the size the template draws it at in pixels, and a fallback advance for the characters the table lacks.

```php
use Phil\TranslationMemory\Check\AdvanceWidths;
use Phil\TranslationMemory\Check\MaxSize;

$width = new AdvanceWidths(unitsPerEm: 1000, size: 16.0, advances: $advances, fallback: 600);
$checker = Checker::defaults($width, maximum: 240.0, font: $font);
```

The width is the sum of the advances: kerning and ligatures are left out, which a proportional font draws within a
few percent. The units per em are 16 to 16384, the size above 0 and at most 1000 pixels, and no advance is below 0.

`Glyphs` reads the character map of a face of `phil/fonts` — `FontFile::fromFile($path)->face()` — and reports the
characters of the target it does not cover, white space aside, ten at most per issue. A character the font lacks is
drawn by a fallback font, or as an empty box, and its measured width is wrong; the two checks go together.

## Writing a check

A check implements `Phil\TranslationMemory\Check\Contract\Check`: `name()`, lower case letters and hyphens, which the
flag `ignore-<name>` dismisses, and `check(Checked $checked): list<CheckIssue>`. A check holds no state between two
calls. See [Extension points](extension-points.md).
