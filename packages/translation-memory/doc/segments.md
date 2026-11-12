# Segments

A `Segment` is one piece of a document to translate into one language. It is a value: each change returns a copy.

| Property | Meaning |
|---|---|
| `key` | Where the piece is in its document — 1 to 255 bytes of UTF-8, unique in the document |
| `source` | The text to translate, inline HTML |
| `fingerprint` | The `Fingerprint` of the source, computed |
| `state` | A `SegmentState` |
| `target` | The translation, inline HTML, or `null` |
| `translatedSource` | The source the target was written for, or `null` |

## Fingerprints

`Fingerprint::of($text)` is the SHA-256, in hexadecimal, of the text with every run of white space made one space and
none at either end. Two sources that differ only by white space have the same fingerprint; a word, a punctuation mark
or a tag changes it. `Fingerprint::fromString()` reads one back from storage and refuses anything but 64 lower-case
hexadecimal digits.

## States

| State | Target | Usable | Meaning |
|---|---|---|---|
| `untranslated` | none | no | Nothing written yet |
| `suggested` | yes | no | Proposed by the memory, a machine or an import; nobody accepted it |
| `translated` | yes | yes | Written or accepted by a translator |
| `reviewed` | yes | yes | Translated, then checked by a reviewer |
| `stale` | yes | no | Written for a source that has changed since |

`SegmentState::isUsable()` says whether a target can be published; `hasTarget()` whether the state holds one. The
constructor refuses a target without state and a state without target.

## Transitions

```php
$segment = new Segment('b1/text', 'Hello world');   // untranslated
$segment = $segment->suggest('Bonjour monde');      // suggested
$segment = $segment->translate('Bonjour le monde'); // translated
$segment = $segment->review();                      // reviewed
$segment = $segment->withSource('Hello, world');    // stale: the target and the source it translated are kept
$segment = $segment->confirm();                     // translated again: the target still holds
$segment = $segment->clear();                       // untranslated
```

- `translate()` and `suggest()` set the target for the current source, from any state.
- `review()` accepts a suggested or translated target, and refuses an untranslated or stale segment.
- `confirm()` turns a stale segment back into a translated one, its target unchanged; any other state is refused.
- `withSource()` keeps the segment as it is when the new source has the same fingerprint, leaves an untranslated
  segment untranslated, makes a segment with a target stale — keeping the source its target was written for — and
  makes a stale segment translated again when the source comes back to that text.
- `withKey()` moves the segment to another key.

## What changed in a stale source

`SourceChange` compares the words of the source a target was written for with the current source, through
`phil/text-diff`, and gives escaped HTML with `<del>` and `<ins>` — the place to look at before revising the target:

```php
echo new SourceChange()->of($stale);
// The <del>red</del><ins>blue</ins> door
```

It gives `null` when the segment has no earlier source, or when the words are the same — only tags changed.
