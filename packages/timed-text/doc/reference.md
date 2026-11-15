# Reference

| Type | Role |
|---|---|
| `Track` | The cues of one media and what only WebVTT carries; shifting, merging, splitting, finding cues |
| `Cue` | One timed text: start, end, cue text, identifier, settings |
| `CueSettings` | The WebVTT settings of a cue |
| `Region` | A WebVTT region and its settings |
| `Note` | A WebVTT comment anchored to an instant |
| `StylePolicy` | Whether WebVTT `STYLE` blocks are kept or dropped |
| `CueText` | Reading and writing the text of a cue: plain text, voices, SRT text, HTML |
| `Timecode` | Instants in milliseconds and their written forms |
| `Limits` | The bounds of the work on one document |
| `Inspection` | What a lenient reading made of a document: the track and the problems |
| `Issue` | One problem, with its line |
| `Chapter` | One chapter: start, title, end, link, image, table of contents, place |
| `ChapterLocation` | The place of a chapter |
| `ChapterList` | The chapters of a media and what the file says of itself |
| `TranscriptFormat` | The formats of a track, by media type, with their reader and writer |
| `ChapterFormat` | The formats of chapters, by media type, with their reader and writer |
| `Contract\TrackReader`, `Contract\TrackWriter` | Reading and writing a track |
| `Contract\ChapterReader`, `Contract\ChapterWriter` | Reading and writing chapters |
| `WebVtt\WebVttReader`, `WebVtt\WebVttWriter` | WebVTT |
| `WebVtt\WebVttChapterReader`, `WebVtt\WebVttChapterWriter` | WebVTT chapters |
| `Srt\SrtReader`, `Srt\SrtWriter` | SubRip |
| `Podcast\JsonTranscriptReader`, `Podcast\JsonTranscriptWriter` | Podcasting 2.0 JSON transcripts |
| `Podcast\JsonChapterReader`, `Podcast\JsonChapterWriter` | Podcasting 2.0 JSON chapters |
| `Html\HtmlTranscriptReader`, `Html\HtmlTranscriptWriter` | HTML transcripts |
| `Exception\TimedTextException` | The interface of every exception of the library |
| `Exception\InvalidTimedText` | A document refused, with its `documentLine` |
| `Exception\LimitExceeded` | A document past the limits |
| `Exception\InvalidCue` | A value built that no format can carry |
| `Exception\InvalidLimits` | Limits below 1 |
| `Internal\*` | The parsers and helpers: not part of the public API |
