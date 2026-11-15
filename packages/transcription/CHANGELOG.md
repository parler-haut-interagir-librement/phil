# Changelog

All notable changes to `phil/transcription` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-15

### Added

- The model: `Transcript` of `Segment`s in a declared `Language` — `fr-CA` included —, with its engine and its
  `ReviewState`; `toTrack()` and `fromTrack()` for an editor, `publish()` in each format of phil/timed-text once
  reviewed, `NotReviewed` before.
- The port `Contract\Transcriber`, its `Processing` — local or cloud —, and `TranscriptionRequest` with the
  `RecordingConsent` of the people heard; `Transcription`, which asks the consent before any transcriber sees the
  request and leaves every transcript to review.
- `WhisperCpp\WhisperCppTranscriber`: a whisper.cpp server run by the site, `verbose_json`.
- `OpenAi\OpenAiTranscriber` and `Deepgram\DeepgramTranscriber`: cloud services refused without consent before the file
  is opened, keys as sensitive parameters, endpoints set by the operator.
- `Reader\VerboseJsonReader` and `Reader\DeepgramReader`: the responses read within `TranscriptionLimits`, a segment
  that cannot be timed or is blank skipped, speakers named after a pattern.
- The recording streamed in chunks, the response read within its bytes, a failure that never quotes the body.
- The exceptions, all `Exception\TranscriptionException`: `ConsentRequired`, `InvalidTranscription`,
  `TranscriptionFailed`, `LimitExceeded`, `NotReviewed`.
- The fuzz targets `verbose-json` and `deepgram`.
- The package skeleton, its documentation in `doc/`, `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
