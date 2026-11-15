# Extension points

The library is plain PHP and declares no service. What an application adds goes through four contracts and the
arguments of the readers and writers.

## Contracts

| Contract | Method | Contract |
|---|---|---|
| `Contract\TrackReader` | `read(string): Track` | Reads a document strictly; throws `InvalidTimedText` or `LimitExceeded`; keeps no state between calls |
| | `inspect(string): Inspection` | Reads what it can and lists the problems |
| `Contract\TrackWriter` | `write(Track): string` | Writes a track, leaving out what the format cannot carry |
| `Contract\ChapterReader` | `read(string): ChapterList` | Reads chapters strictly |
| `Contract\ChapterWriter` | `write(ChapterList): string` | Writes chapters |

A format the library does not read — TTML, SCC, a service's own JSON — is a reader of the application's that builds a
`Track`, with cue text from `CueText::fromPlain()`; every writer of the library then writes it.

## Arguments

| Point | How |
|---|---|
| The bounds of the work | `Limits`, given to each reader |
| The `STYLE` blocks | `StylePolicy::Keep` or `StylePolicy::Drop`, given to `WebVttReader` and `WebVttWriter` |
| The duration of the media | The `$duration` of `WebVttChapterWriter`, which ends the last chapter |
| Choosing a format at run time | `TranscriptFormat::fromMediaType()` and `ChapterFormat::fromMediaType()` |
| Catching the library's errors | Every exception implements `Exception\TimedTextException` |

## Stability

The contracts, the model and the formats' readers and writers are the public API. The `Internal\` namespace —
`CueToken`, `Input`, `IssueLog`, `Json`, `SrtParser`, `WebVttParser` — is not: it changes without notice.
