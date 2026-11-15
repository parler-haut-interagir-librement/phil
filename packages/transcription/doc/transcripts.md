# Transcripts

## Segments

A `Segment` is what was said between two instants in milliseconds: its text on one line, its speaker when the service
tells speakers apart, and the service's confidence from 0 to 1. Whitespace is collapsed; a blank text, an instant out
of range or an end before its start is refused with `InvalidTranscription`.

A `Transcript` holds its `Language`, its segments in order, its `ReviewState` and the engine that produced it, for the
record: `whisper.cpp`, `openai:whisper-1`, `deepgram:nova-3`.

## For an editor

`toTrack()` gives the segments as the cues of a phil/timed-text `Track`, a speaker as the voice of each cue: an editor
corrects it in any format of the library — WebVTT, SRT — and `Transcript::fromTrack()` reads the corrected track back,
each cue a segment, its voice the speaker, a cue left blank dropped.

```php
$vtt = TranscriptFormat::WebVtt->writer()->write($transcript->toTrack());
// … corrected by a person …
$corrected = Transcript::fromTrack(TranscriptFormat::WebVtt->reader()->read($edited), $transcript->language, ReviewState::Reviewed);
```

## The review

A machine's transcript is `ToReview`. `reviewed()` marks it reviewed; `withSegments()` changes its segments and makes it
to review again. `publish($format)` writes it in a format of phil/timed-text — WebVTT, SRT, Podcasting 2.0 JSON, HTML —
only once reviewed, and throws `NotReviewed` otherwise; `isPublishable()` says which.

```php
$transcript->reviewed()->publish(TranscriptFormat::Json);   // a <podcast:transcript> file
```

`text()` gives the whole text, a segment per line, for a search index or a summary.
