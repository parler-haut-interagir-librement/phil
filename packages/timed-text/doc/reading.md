# Reading

## Strict and lenient

Every track reader has two methods:

- `read()` reads a document that follows its format and throws `Exception\InvalidTimedText` at its first problem, with
  the line in `documentLine` when the format has lines;
- `inspect()` reads what can be read, steps over the blocks it cannot, and returns an `Inspection`: the `track` of the
  readable blocks and the `issues`, each an `Issue` with its `line` and `message`. `isValid()` says whether there is
  none.

```php
use Phil\TimedText\WebVtt\WebVttReader;

$inspection = new WebVttReader()->inspect($upload);

foreach ($inspection->issues as $issue) {
    echo $issue->toString(), "\n"; // Line 27: "align:middle" is not a cue setting; it is ignored.
}

$track = $inspection->track;
```

Use `read()` on files the application produced, and `inspect()` on files people upload, to show them what was set
aside. `inspect()` still throws when the document is not of the format at all — a WebVTT file that does not open with
`WEBVTT`, a JSON document that is not an object with its list — and when it is larger than the limits allow.

## What counts as a problem

| Format | Problems |
|---|---|
| WebVTT | A block that is neither a cue, a note, a style nor a region; timings that are not `start --> end`; a timestamp that is not one; a cue that ends before it starts or starts before the cue above it; an unknown setting; a region without an identifier or defined twice; a `STYLE` or `REGION` block after the first cue or holding `-->`; a note cut by a line with `-->`; a header holding `-->` |
| SRT | A block without timings; a cue without its number; timings or timestamps that cannot be read; a cue that ends before it starts or starts before the cue above it |
| JSON transcript | A segment that is not an object; a `startTime` or `endTime` that is not a number of seconds from 0; a segment that ends before it starts or starts before the one above it; a `body` or `speaker` that is not a string |
| HTML transcript | A `<time>` that is neither a duration nor a clock, or comes before the time above it |

JSON has no lines: its problems name the segment, counted from 1. HTML problems name the `<time>`. The chapter readers
are strict only, and their problems name the chapter.

## Limits

`Limits` bounds the work on one document, whatever it holds: `maxBytes` (16 MiB by default), `maxCues` (100 000 cues,
segments, paragraphs or chapters), `maxTextBytes` (64 KiB for the text of one cue, title, note or style) and
`maxIssues` (1 000 problems listed). Past one of them, the reader throws `Exception\LimitExceeded`, in `read()` and in
`inspect()` alike. See [Security](security.md).
