# Transcoding and metadata

## Renditions

A `Rendition` is one version of a file to produce: an `AudioFormat`, and the bitrate, channels and sample rate the
encoder is asked for, each left to the encoder when null.

| Format | Media type | Extension | Encoder, muxer | Default bitrate |
|---|---|---|---|---|
| `Mp3` | `audio/mpeg` | `mp3` | `libmp3lame`, `mp3` | 128 kbit/s |
| `Aac` | `audio/mp4` | `m4a` | `aac`, `ipod` | 128 kbit/s |
| `Opus` | `audio/ogg; codecs=opus` | `opus` | `libopus`, `ogg` | 96 kbit/s |
| `Flac` | `audio/flac` | `flac` | `flac`, `flac` | none: lossless |
| `Wav` | `audio/wav` | `wav` | `pcm_s16le`, `wav` | none: lossless |

A bitrate is from 8 to 512 kbit/s and never given to a lossless format; channels are 1 or 2; a sample rate is one of
8000, 11025, 12000, 16000, 22050, 24000, 32000, 44100 or 48000 Hz, and for Opus one of 8000, 12000, 16000, 24000 or
48000 Hz. Anything else is refused with `InvalidAudio` when the rendition is built, before any tool runs.
`AudioFormat::fromMediaType()` finds a format by its media type and usual aliases; `audio/ogg` is Opus unless its
`codecs` parameter names another codec.

```php
$ffmpeg->transcode($source, $target, new Rendition(AudioFormat::Opus, 64, channels: 1, sampleRate: 48_000));
```

The target is written by ffmpeg, replaced when it exists. A rendition keeps the first audio stream only, without video
or cover art, and without the tags of the source: the title, the author and anything else a recorder wrote stay in the
upload.

## Excerpts

An `Excerpt` is a start and a duration in milliseconds. Given to `transcode()`, it cuts the rendition: ffmpeg seeks to
the start before reading, and stops after the duration.

```php
$ffmpeg->transcode($source, $excerptFile, new Rendition(AudioFormat::Mp3), new Excerpt(30_000, 30_000));
```

`Excerpt::within($duration)` cuts an excerpt to a file of that duration, or gives null when it starts after the end.

## Metadata

`read()` runs ffprobe on the file and returns `AudioMetadata`: the duration in milliseconds, the container, the codec,
the bitrate in bits per second, the sample rate, the channels, and the tags of the container and of the first audio
stream, each name in lowercase. A value the file does not give is null.

`Ffmpeg\FfprobeReader` reads the JSON report of ffprobe on its own, bounded: at most 1 MiB by default, 64 tags of 1 KiB
each; a tag longer or not text is left out, and a report without an audio stream is refused with `InvalidAudio`.

## The limits of each run

`FfmpegLimits` bounds every run:

| Limit | Default | When it is passed |
|---|---|---|
| `timeout` | 900 seconds | The run is stopped and `LimitExceeded` thrown |
| `maxInputBytes` | 2 GiB | The file is refused before any tool reads it |
| `maxOutputBytes` | 1 GiB | ffmpeg stops writing at the limit (`-fs`); a file that reaches it is deleted and refused |
| `maxDuration` | 6 hours | A file longer, by ffprobe, is refused before ffmpeg decodes it |
| `maxReportBytes` | 1 MiB | A program that prints more is stopped |

A file whose duration ffprobe cannot tell is still bounded by the output and the time.

## Errors

| Exception | When |
|---|---|
| `InvalidAudio` | A value refused, or a report that is no JSON or has no audio stream |
| `LimitExceeded` | One of the limits above |
| `AudioToolFailed` | The program ran and refused the file: its first line of error, at most 500 bytes, is the message |
| `AudioToolUnavailable` | The program cannot be found or executed, or `NullAudio` is configured |

All implement `Exception\AudioException`.
