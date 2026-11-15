# Reference

| Type | Role |
|---|---|
| `Audio` | A track as a player shows it: title, files, listening policy, duration, waveform, chapters, transcripts |
| `AudioSource` | One file of a track: address, format, whether it holds only the excerpt |
| `AudioTranscript` | The transcript of a track in one language: a phil/timed-text `Track`, a label, a file |
| `AudioFormat` | MP3, AAC, Opus, FLAC and WAV, with their media types and extensions |
| `Rendition` | A version to produce: format, bitrate, channels, sample rate |
| `Excerpt` | A part of a file: start and duration in milliseconds |
| `ListeningMode` | The whole track, an excerpt, or nothing before a purchase |
| `ListeningPolicy` | The creator's choice, and what a listener may hear |
| `AudioMetadata` | What a file says of itself: duration, container, codec, bitrate, sample rate, channels, tags |
| `Peaks` | A waveform: the lowest and highest sample of each run; its JSON and its bars |
| `PeaksReader` | Reads the JSON of peaks, bounded |
| `PeaksComputer` | Computes peaks from raw samples, in chunks |
| `CommandResult` | What a program left: exit code, output, errors |
| `NullAudio` | No tool: every call throws `AudioToolUnavailable` |
| `Contract\Transcoder`, `Contract\MetadataReader`, `Contract\PeaksGenerator` | The ports of the tools |
| `Contract\CommandRunner` | Runs a program from its arguments, bounded |
| `Ffmpeg\FfmpegAudio` | The three ports on ffmpeg and ffprobe |
| `Ffmpeg\FfmpegLimits` | The bounds of each run |
| `Ffmpeg\FfprobeReader` | Reads the JSON report of ffprobe |
| `Ffmpeg\ProcessCommandRunner` | Runs a program through Symfony Process |
| `Exception\AudioException` | The interface of every exception of the library |
| `Exception\InvalidAudio` | A value refused, or a document that cannot be read |
| `Exception\LimitExceeded` | A file, a duration, an output or a run past its limit |
| `Exception\AudioToolFailed` | A program that refused the file |
| `Exception\AudioToolUnavailable` | No program to run |
