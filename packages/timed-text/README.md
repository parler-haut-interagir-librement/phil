# phil/timed-text

**Read and write captions, chapters and transcripts in every format a player or a podcast app asks for, through one
model.**

phil/timed-text reads WebVTT, SubRip (SRT), Podcasting 2.0 JSON transcripts and HTML transcripts into one neutral model,
a `Track` of `Cue`s, and writes it back in any of them; it reads and writes chapters as Podcasting 2.0 JSON chapters and
as WebVTT chapters. A WebVTT file read and written again keeps its header, regions, styles, notes, identifiers, settings
and cue markup. Instants are integer milliseconds, so shifting, merging and splitting a track of several hours adds no
rounding.

It is for any PHP application that publishes audio or video: the captions of a video, the transcript and chapters of an
episode, the output of a speech-to-text service turned into WebVTT, SRT, JSON and an HTML page.

It is a plain PHP library whose requirements are `ext-dom`, `ext-libxml`, `ext-mbstring` and `phil/url`. Every reader is
bounded — bytes, cues, text per cue, problems listed — and every writer of HTML escapes by construction.

The design: `Track`, `Cue`, `CueSettings`, `Region` and `Note` are the model, `CueText` reads and writes the text of a
cue, `Timecode` reads and writes instants; the readers and writers of `WebVtt\`, `Srt\`, `Podcast\` and `Html\` follow
`Contract\TrackReader` and `Contract\TrackWriter`, or `Contract\ChapterReader` and `Contract\ChapterWriter`;
`TranscriptFormat` and `ChapterFormat` pick them by media type.

## Status

| Part | State |
|---|---|
| WebVTT: header, cues with identifiers, settings and markup, regions, `STYLE` kept or dropped, `NOTE` comments | Implemented and tested |
| SubRip (SRT): numbered cues, editors' variants, `<b>`, `<i>` and `<u>` | Implemented and tested |
| Podcasting 2.0 JSON transcripts, with speakers | Implemented and tested |
| HTML transcripts: written escaped by construction, read with PHP's HTML5 parser | Implemented and tested |
| Chapters: Podcasting 2.0 JSON chapters, WebVTT chapters | Implemented and tested |
| Strict reading with line numbers, lenient inspection that lists every problem | Implemented and tested |
| Shifting, merging and splitting tracks and cues, in integer milliseconds | Implemented and tested |
| Cue text as plain text, SRT text or escaped HTML; voices and speakers | Implemented and tested |
| TTML, SCC, SBV and other caption formats | Not started |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, the readers and writers as services. |
| [The model](doc/model.md) | Tracks, cues, cue text, notes, regions and styles, and editing a track. |
| [Formats](doc/formats.md) | What each format carries, what a round trip keeps, and what is not supported. |
| [Reading](doc/reading.md) | Strict reading, lenient inspection, problems and their lines, the limits. |
| [Chapters](doc/chapters.md) | Podcasting 2.0 JSON chapters and WebVTT chapters. |
| [Security](doc/security.md) | Hostile files, the limits, and the HTML escaped by construction. |
| [Extension points](doc/extension-points.md) | The contracts an application implements or replaces. |
| [Testing](doc/testing.md) | The library's tests, its fuzz targets and its benchmarks. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/timed-text
```

```php
use Phil\TimedText\TranscriptFormat;
use Phil\TimedText\WebVtt\WebVttReader;

$track = new WebVttReader()->read(file_get_contents('episode.vtt'));

// The same cues one second later, as SRT, as a Podcasting 2.0 transcript and as HTML.
$later = $track->shifted(1_000);
$srt = TranscriptFormat::Srt->writer()->write($later);
$json = TranscriptFormat::Json->writer()->write($later);
$html = TranscriptFormat::Html->writer()->write($later);
```

## Requirements

PHP 8.5 or later, with `ext-dom`, `ext-libxml` and `ext-mbstring`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the [code of
conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md) explains, never in
a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
