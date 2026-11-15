# Reference

| Type | Role |
|---|---|
| `Transcript` | The timed segments of a recording, its language, its review; its track and its publication |
| `Segment` | What was said between two instants: text, speaker, confidence |
| `Language` | A BCP 47 tag of a language, a script and a region, written in its usual case |
| `ReviewState` | To review, or reviewed by a person |
| `TranscriptionRequest` | A recording to transcribe: its file, its language, the consent, a hint |
| `RecordingConsent` | Whether the people heard agreed that the recording leaves the site |
| `Processing` | Whether a transcriber sends a recording outside the site |
| `TranscriptionLimits` | The bounds of one transcription |
| `Transcription` | The transcriber an application calls: consent first, every transcript to review |
| `Contract\Transcriber` | The port of the transcribers |
| `WhisperCpp\WhisperCppTranscriber` | A whisper.cpp server run by the site |
| `OpenAi\OpenAiTranscriber` | OpenAI's transcription API, or a server that speaks it |
| `Deepgram\DeepgramTranscriber` | Deepgram's pre-recorded API |
| `Reader\VerboseJsonReader` | Reads the `verbose_json` of a Whisper server |
| `Reader\DeepgramReader` | Reads the utterances of Deepgram |
| `Exception\TranscriptionException` | The interface of every exception of the library |
| `Exception\ConsentRequired` | A recording sent outside without consent |
| `Exception\InvalidTranscription` | A value refused |
| `Exception\TranscriptionFailed` | A service that fails or answers no transcript |
| `Exception\LimitExceeded` | A recording, a response or a transcript past its limit |
| `Exception\NotReviewed` | A transcript published before its review |
| `Internal\*` | The HTTP, multipart and segment helpers: not part of the public API |
