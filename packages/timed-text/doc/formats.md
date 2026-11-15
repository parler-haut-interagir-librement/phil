# Formats

| Format | Media type | Reader | Writer |
|---|---|---|---|
| WebVTT | `text/vtt` | `WebVtt\WebVttReader` | `WebVtt\WebVttWriter` |
| SubRip | `application/srt`, also `application/x-subrip` and `text/srt` when read | `Srt\SrtReader` | `Srt\SrtWriter` |
| Podcasting 2.0 JSON transcript | `application/json` | `Podcast\JsonTranscriptReader` | `Podcast\JsonTranscriptWriter` |
| HTML transcript | `text/html` | `Html\HtmlTranscriptReader` | `Html\HtmlTranscriptWriter` |
| Podcasting 2.0 JSON chapters | `application/json+chapters` | `Podcast\JsonChapterReader` | `Podcast\JsonChapterWriter` |
| WebVTT chapters | `text/vtt` | `WebVtt\WebVttChapterReader` | `WebVtt\WebVttChapterWriter` |

`TranscriptFormat::fromMediaType()` and `ChapterFormat::fromMediaType()` find the format of a media type, its
parameters and case ignored; `reader()` and `writer()` build its reader and writer.

## WebVTT

Read as the WebVTT specification of the W3C describes it: the optional byte order mark, `WEBVTT` and the header text,
the metadata lines under it skipped, then blocks separated by empty lines — `REGION` and `STYLE` before the first cue,
`NOTE` anywhere, and cues with an optional identifier, `start --> end`, settings and text. A line with `-->` starts the
next cue even without an empty line. Lines may end with CRLF, CR or LF.

A file read and written again keeps everything a player uses: header, regions, styles, notes in their place, cue
identifiers, timings, settings and markup. The writer always writes the hours of a timestamp, each region setting on
its own line, `NOTE text` on one line with the text, and LF line endings: a file written by hand comes back in that
form, with the same meaning.

## SubRip (SRT)

Numbered cues with `hh:mm:ss,mmm --> hh:mm:ss,mmm` timings and their text. The reader accepts what editors write: a full
stop or fewer digits in a timestamp, coordinates after the timings, a missing empty line before the next number and
timings. `<b>`, `<i>` and `<u>` become cue markup; `<font>`, other tags and the `{\an8}` overrides are dropped. The
numbers are not kept: the writer numbers the cues from 1.

The writer leaves out what SRT has no place for — identifiers, settings, regions, styles, notes, voices — and writes
`<b>`, `<i>` and `<u>` only. An SRT file read and written again is the same, numbered from 1, without its `<font>` tags.

## Podcasting 2.0 JSON transcripts

The `segments` of the Podcasting 2.0 transcript format, each with `startTime` and `endTime` in seconds, a `body` and an
optional `speaker`. A segment becomes a cue whose text is the body escaped and opened by the speaker's voice, so that a
WebVTT file written from it says who speaks. The writer writes version `1.0.0`, the speaker from the voice that opens
each cue, the body as plain text. A transcript read and written again is the same, its times rounded to the millisecond.

## HTML transcripts

The HTML transcript Podcasting 2.0 describes: the speaker in a `<cite>`, the start in a `<time>`, the text in a `<p>`.
The writer writes a fragment for the application's page — a `<cite>` when the speaker changes, a `<time>` whose
`datetime` is an ISO 8601 duration, the plain text in a `<p>` with `<br>` at its line breaks. The reader takes any HTML
of that shape, through PHP's HTML5 parser: a paragraph starts at the `<time>` above it — its `datetime`, else its text
read as a clock — and ends where the next later paragraph starts; the last one ends where it starts. Only the text and
its line breaks are kept.

## Not supported

- TTML and its profiles (IMSC, EBU-TT, SMPTE-TT), SCC, SBV, LRC and the subtitle formats of video editors.
- The WebVTT `Region:` header lines of the drafts before the `REGION` block; they are skipped with the other metadata.
- Interpreting CSS: a `STYLE` block is kept as text or dropped, never applied.
- Fields of Podcasting 2.0 JSON files the specification does not name: they are not kept.
- Writing a transcript as plain text: `CueText::plain()` gives each cue's text for an application that needs it.
