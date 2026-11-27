# Changelog

[Français](CHANGELOG.fr.md)

All notable changes to `phil/audio` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-15

### Added

- The model of a track: `Audio` with its title, its `AudioSource`s, its duration, its waveform, its chapters as a
  `ChapterList` and its `AudioTranscript`s as `Track`s of phil/timed-text.
- The creator's listening policy: `ListeningPolicy` and `ListeningMode` — the whole track, an `Excerpt`, or nothing
  before a purchase —, and `Audio::playable()`, the files a listener's player may fetch.
- `AudioFormat` (MP3, AAC, Opus, FLAC, WAV) and `Rendition`, refusing a bitrate, channels or a sample rate no encoder
  takes.
- The ports `Contract\Transcoder`, `Contract\MetadataReader` and `Contract\PeaksGenerator`, and their adapters:
  `Ffmpeg\FfmpegAudio`, on ffmpeg and ffprobe run as external programs, and `NullAudio`.
- The bounds of each run of ffmpeg, `Ffmpeg\FfmpegLimits`: input bytes, duration, output bytes, printed output and
  time; the `file` protocol only, no standard input, renditions without the tags of the source.
- `Contract\CommandRunner` and `Ffmpeg\ProcessCommandRunner`: a program run from the list of its arguments through
  Symfony Process, stopped at its timeout or past its output.
- `Ffmpeg\FfprobeReader` and `AudioMetadata`: the report of ffprobe, bounded, with its tags.
- The waveform: `Peaks` and its JSON, the shape audiowaveform writes; `PeaksReader`, bounded; `PeaksComputer`, from raw
  samples read in chunks; `Peaks::amplitudes()`, the bars of a player.
- The exceptions, all `Exception\AudioException`: `InvalidAudio`, `LimitExceeded`, `AudioToolFailed`,
  `AudioToolUnavailable`.
- The fuzz targets `peaks` and `ffprobe`, and the benchmark of the waveform.
- The package skeleton, its documentation in `doc/`, `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
