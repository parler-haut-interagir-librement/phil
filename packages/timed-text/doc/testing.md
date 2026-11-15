# Testing

## The library's tests

The suite `tests/Unit` reads files written for it in `tests/Fixtures` — a WebVTT file with a header, a region, a style,
notes, identifiers, settings and voices, an SRT file with `<font>` and uppercase tags, a Podcasting 2.0 transcript and
chapters file, an HTML transcript — and tests each reading and writing, each round trip, every problem an inspection
reports with its line, every limit, the timestamps of each format, the editing of tracks and cues, and the escaping of
the HTML.

From the monorepo root:

```sh
vendor/bin/phpunit packages/timed-text/tests
```

The library is held to 100 % covered mutation score by Infection.

## Fuzzing

Each parser has a fuzz target, fed by the seeds of `tests/Fuzz/corpus/<target>`: whatever it reads, it returns what it
read, which is written again, or throws an exception of the library.

| Target | What it fuzzes |
|---|---|
| `webvtt` | `WebVttReader::inspect()`, then `WebVttWriter` |
| `srt` | `SrtReader::inspect()`, then `SrtWriter` |
| `json-transcript` | `JsonTranscriptReader::inspect()`, then `JsonTranscriptWriter` |
| `json-chapters` | `JsonChapterReader::read()`, then `JsonChapterWriter` |
| `html-transcript` | `HtmlTranscriptReader::inspect()`, then `HtmlTranscriptWriter` |
| `cue-text` | Every method of `CueText`; the HTML must carry no tag it did not build |

```sh
composer fuzz -- webvtt srt json-transcript json-chapters html-transcript cue-text
```

## Benchmarks

`tests/Benchmark/TimedTextBench.php` reads and writes a three-hour transcript — 3 600 cues with two speakers — in each
format, and shifts and splits it. Each reading and writing takes a few tens of milliseconds:

```sh
composer bench -- packages/timed-text/tests/Benchmark
```

## Testing code that uses the library

The readers and writers are deterministic and need no server: keep sample files beside the tests, read them, and
compare the cues, or write a track and compare the text.

```php
$track = new WebVttReader()->read((string) file_get_contents(__DIR__.'/captions/episode.vtt'));

self::assertSame('Alice', $track->cues[0]->speaker());
self::assertSame("1\n00:00:01,000 --> 00:00:04,500\nWelcome.\n", new SrtWriter()->write($track->withCues([$track->cues[0]])));
```
