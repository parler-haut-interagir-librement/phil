# Chapters

A `ChapterList` holds `Chapter`s in order, and what a Podcasting 2.0 chapters file says of itself: its `version`,
`title`, `author`, `podcastName`, `description`, `fileName` and `waypoints`.

A `Chapter` has a `start` in milliseconds, a `title` as plain text, and what Podcasting 2.0 adds: an `end`, a `url`, an
`image`, a `toc` flag — whether the chapter shows in a table of contents — and a `ChapterLocation` with a `name`, a
`geo:` URI and an OpenStreetMap object. Links and images are `Phil\Url\Url`: an `http` or `https` address of a host.

| Method | Returns |
|---|---|
| `ChapterList::at($ms)` | The chapter under way at an instant |
| `ChapterList::tableOfContents()` | The chapters whose `toc` is true |
| `ChapterList::withEnds($duration)` | Each chapter with an end: its own, the next start, or the duration given |
| `Chapter::shifted($ms)` | The chapter moved |

## Podcasting 2.0 JSON chapters

`Podcast\JsonChapterReader` reads the `application/json+chapters` file a `<podcast:chapters>` element points to. The
`version` is required; a `startTime` is a number of seconds from 0; an `endTime` comes at or after its start; `toc` and
`waypoints` are booleans; a link or an image that is not an `http` or `https` address refuses the file, so that a
`javascript:` link never reaches a player; the chapters come in the order of their starts. Fields the specification
does not name are not kept. `Podcast\JsonChapterWriter` writes the fields that are set, and `toc` only when it is false.

## WebVTT chapters

`WebVtt\WebVttChapterReader` reads a WebVTT file whose cues are the chapters, as a `<track kind="chapters">` uses: each
cue gives a start, an end and, from its plain text, a title. `WebVtt\WebVttChapterWriter` writes one cue per chapter;
a cue needs an end, so a chapter without one ends where the next starts, and the last at the duration of the media
given to the writer — without it, the writer throws `Exception\InvalidCue`. Links, images, places and the table of
contents have no place in WebVTT and are left out.

```php
use Phil\TimedText\ChapterFormat;

$chapters = ChapterFormat::Json->reader()->read($json);
$vtt = ChapterFormat::WebVtt->writer(duration: 3_600_000)->write($chapters);
```
