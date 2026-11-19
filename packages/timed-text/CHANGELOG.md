# Changelog

All notable changes to `phil/timed-text` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.1.1-alpha: a WebVTT file holding more than 100,000 notes, 1,000 regions or 1,000 style blocks is
refused as soon as its budget is spent. A patch version and not a minor one: no caption file written for a player
holds that many.

### Added

- `maxNotes`, `maxRegions` and `maxStyles` on `Limits`, the `NOTE` blocks, regions and `STYLE` blocks of a WebVTT
  document: 100,000, 1,000 and 1,000 by default. `WebVtt\WebVttReader` throws `Exception\LimitExceeded` at the first
  block past its budget, before it reads it, in `read()` and in `inspect()` alike.
- The seeds `many-notes.vtt` and `many-regions.vtt` of the fuzz target `webvtt`, which reads under tight budgets of
  notes, regions and style blocks.

### Changed

- A region is compared to the regions above it by its identifier in constant time, instead of one by one.

## 0.1.0-alpha - 2026-11-14

### Added

- The model: `Track` of `Cue`s with its header, `Region`s, styles and `Note`s anchored to an instant; `Cue` with its
  start and end in integer milliseconds, WebVTT cue text, identifier and `CueSettings`; every value immutable and
  refusing what would break a WebVTT block.
- Editing in integer milliseconds: `Track::shifted()`, `mergedWith()`, `splitAt()` with or without rebasing,
  `sorted()`, `at()` and `between()`; `Cue::shifted()`, `splitAt()` and `mergedWith()`.
- `Timecode`: WebVTT, SRT, clock, ISO 8601 duration and JSON seconds, read and written, up to 9999 hours.
- `CueText`: plain text, speaker and voices of a cue; cue text from plain text or SRT text; SRT text; HTML escaped by
  construction, with only `b`, `i`, `u`, `ruby`, `rt`, `span` and `br`.
- `WebVtt\WebVttReader` and `WebVtt\WebVttWriter`: header, regions, `STYLE` blocks kept or dropped by `StylePolicy`,
  `NOTE` comments in their place, identifiers, timings, settings and markup, with round trips that keep them.
- `Srt\SrtReader` and `Srt\SrtWriter`: numbered cues, the variants editors write, `<b>`, `<i>` and `<u>`.
- `Podcast\JsonTranscriptReader` and `Podcast\JsonTranscriptWriter`: Podcasting 2.0 JSON transcripts, speakers as the
  voice of each cue.
- `Html\HtmlTranscriptReader` and `Html\HtmlTranscriptWriter`: HTML transcripts of `<cite>`, `<time>` and `<p>`, read
  with PHP's HTML5 parser and written escaped by construction.
- `Chapter`, `ChapterLocation` and `ChapterList`; `Podcast\JsonChapterReader` and `Podcast\JsonChapterWriter` for
  Podcasting 2.0 JSON chapters, links and images read through `phil/url`; `WebVtt\WebVttChapterReader` and
  `WebVtt\WebVttChapterWriter` for WebVTT chapters.
- `TranscriptFormat` and `ChapterFormat`: the formats by media type, with their reader and writer.
- Strict reading that refuses a document at its first problem, with its line, and `inspect()`, which reads what it can
  and returns an `Inspection` with every `Issue`.
- `Limits`: the bytes of a document, its cues, the text of a cue and the problems listed, refused past them with
  `Exception\LimitExceeded`.
- The contracts `Contract\TrackReader`, `Contract\TrackWriter`, `Contract\ChapterReader` and
  `Contract\ChapterWriter`, and `Exception\TimedTextException` over every exception.
- Fuzz targets for each parser and for `CueText`, and benchmarks on a three-hour transcript.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
