# The model

Every format is read into one model and written from it. Each type is immutable: a change returns a new value.

## Instants

An instant is an `int` of milliseconds from the start of the media, from 0 to `Timecode::MAX` (9999 hours, 59 minutes,
59.999 seconds). `Timecode` reads and writes each written form:

| Method | Form |
|---|---|
| `parseWebVtt()`, `formatWebVtt()` | `01:02:03.004`, or `02:03.004` without hours when read |
| `parseSrt()`, `formatSrt()` | `01:02:03,004`; a full stop and fewer digits are read too |
| `fromSeconds()`, `toSeconds()` | `3723.004` as JSON writes it, an integer when the instant falls on a second |
| `parseClock()`, `formatClock()` | `1:02:03` or `2:03`, the clock shown beside a paragraph |
| `parseDuration()`, `formatDuration()` | `PT3723.004S`, the `datetime` of an HTML `<time>` |

Seconds read from JSON are rounded to the millisecond; nothing finer is kept.

## Track and cues

A `Track` holds its `Cue`s in order, and what only WebVTT carries: the `header` after `WEBVTT`, the `Region`s, the
`styles` — the CSS of each `STYLE` block, as text — and the `Note`s.

A `Cue` has a `start`, an `end` at or after it, its `text`, an optional `id` and its `CueSettings`. The text of a cue is
WebVTT cue text in every format: markup such as `<v Alice>`, `<i>` or `<c.loud>`, and `&amp;`, `&lt;` and `&gt;` for
the characters they stand for. A cue refuses a text with a carriage return, an empty line or `-->`, which would break
the block it is written in; build the text from plain text with `CueText::fromPlain()`.

```php
use Phil\TimedText\Cue;
use Phil\TimedText\CueText;

$cue = new Cue(1_000, 4_500, CueText::fromPlain('Fish & chips', speaker: 'Alice'));
$cue->text;        // '<v Alice>Fish &amp; chips'
$cue->plainText(); // 'Fish & chips'
$cue->speaker();   // 'Alice'
```

## Cue text

| Method | Returns |
|---|---|
| `CueText::plain()` | The text without markup, its character references decoded |
| `CueText::speaker()` | The voice that opens the text, or null |
| `CueText::voices()` | Every voice the text names, once each |
| `CueText::fromPlain()` | Cue text from plain text, escaped, empty lines removed, opened by the speaker's voice |
| `CueText::fromSrt()` | Cue text from SRT text: `<b>`, `<i>` and `<u>` kept, other tags and `{\an8}` overrides dropped |
| `CueText::toSrt()` | SRT text: `<b>`, `<i>` and `<u>` kept, other tags dropped, references decoded |
| `CueText::toHtml()` | HTML, escaped by construction — see [Security](security.md) |

## Notes, regions and styles

A `Note` is a `NOTE` comment anchored to an instant: it is written before the first cue that starts at or after its
`at`, or after every cue when `at` is null. Shifting a track moves the anchors with the cues.

A `Region` has an `id`, which the cues name in their `region` setting, and its `width`, `lines`, `regionanchor`,
`viewportanchor` and `scroll` settings. `CueSettings` holds the `vertical`, `line`, `position`, `size`, `align` and
`region` of a cue, in the order written; a setting or a value WebVTT does not allow is refused when built by hand and
dropped, with a problem, when read.

Styles are never interpreted: they are text kept for the next WebVTT file, or dropped by `StylePolicy::Drop`.

## Editing a track

| Method | What it does |
|---|---|
| `Track::shifted($ms)` | Moves every cue and note; moved earlier, a cue that would end before 0 is dropped and one that would start before 0 starts at 0 |
| `Track::mergedWith($other)` | One track from two, cues ordered by start; the regions, styles and notes of the other added |
| `Track::splitAt($ms, $rebase)` | Two tracks, a cue across the instant cut between them; with `$rebase` the second starts at 0 |
| `Track::sorted()` | The cues ordered by start, then end, ties kept in their order |
| `Track::at($ms)`, `between($from, $to)` | The cues shown at an instant, or during `[$from, $to)` |
| `Track::withCues()`, `withoutStyles()` | The same track with other cues, or without its styles |
| `Cue::shifted()`, `splitAt()`, `mergedWith()` | The same on one cue |

```php
// Remove the first ninety seconds of a recording and the captions that went with them.
[, $rest] = $track->splitAt(90_000, rebase: true);
```
