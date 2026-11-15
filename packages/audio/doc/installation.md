# Installation

```sh
composer require phil/audio
```

| Requirement | Why |
|---|---|
| PHP 8.5 or later, with `ext-sodium` | Readonly classes; random names of temporary files written in hexadecimal without timing leaks |
| `phil/timed-text` | The chapters and the transcripts of a track, and the instants of an excerpt |
| `phil/url` | The addresses of the files of a track |
| `symfony/process` | `FfmpegAudio` starts ffmpeg and ffprobe through it |

## ffmpeg

`FfmpegAudio` runs the programs `ffmpeg` and `ffprobe`, found on the `PATH` or named by their paths:

```php
use Phil\Audio\Ffmpeg\FfmpegAudio;
use Phil\Audio\Ffmpeg\FfmpegLimits;
use Phil\Audio\Ffmpeg\ProcessCommandRunner;

$ffmpeg = new FfmpegAudio(new ProcessCommandRunner(), '/usr/bin/ffmpeg', '/usr/bin/ffprobe', new FfmpegLimits(timeout: 600.0));
```

The encoders the renditions ask for are those of the usual builds: `libmp3lame` for MP3, `aac` for AAC, `libopus` for
Opus, `flac` and `pcm_s16le`. A build without one of them refuses the rendition, which `FfmpegAudio` reports as
`AudioToolFailed`. A site that serves only what its creators upload uses `NullAudio` and needs no ffmpeg.

ffmpeg is licensed under the LGPL or the GPL, depending on its build; it runs as a separate program, and nothing of it
is linked into PHP.

## In Symfony

`phil/audio-bundle` configures the tools, the renditions and the jobs that produce them.
