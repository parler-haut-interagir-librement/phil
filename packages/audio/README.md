# phil/audio

**Audio for PHP: transcoding behind a port, metadata, a waveform computed once on the server, excerpts, the creator's
listening policy, and chapters and transcripts in the model of phil/timed-text.**

phil/audio describes a track as a player shows it — its files, its duration, its waveform, its chapters and its
transcripts — and does the work an upload needs: reading what the file says of itself, producing the renditions a site
serves, cutting an excerpt, and computing the peaks a player draws. The tools sit behind three ports: `FfmpegAudio`
runs ffmpeg and ffprobe as external programs, bounded and closed to the network; `NullAudio` runs nothing, for a site
that serves the files its creators upload as they are.

The creator chooses what a listener who has not bought a track may hear: the whole track, an excerpt, or nothing before
the purchase. `Audio::playable()` gives the files that listener's player may fetch.

It is a plain PHP library whose requirements are `ext-sodium`, `phil/timed-text`, `phil/url` and `symfony/process`.
No codec runs in PHP; the waveform is computed from samples ffmpeg decodes, chunk by chunk.

The design: `Audio`, `AudioSource`, `AudioTranscript`, `ListeningPolicy`, `Excerpt` and `Peaks` are the model;
`Contract\Transcoder`, `Contract\MetadataReader` and `Contract\PeaksGenerator` are the ports, `Ffmpeg\FfmpegAudio` and
`NullAudio` their adapters; `Contract\CommandRunner` runs a program, `Ffmpeg\ProcessCommandRunner` through Symfony
Process; `PeaksReader`, `PeaksComputer` and `Ffmpeg\FfprobeReader` read what comes from outside.

## Status

| Part | State |
|---|---|
| `Audio`, `AudioSource`, `AudioTranscript`: a track, its files, its chapters and transcripts from phil/timed-text | Implemented and tested |
| `ListeningPolicy`, `ListeningMode`, `Excerpt`: the whole track, an excerpt or nothing before a purchase | Implemented and tested |
| `Rendition`, `AudioFormat`: MP3, AAC, Opus, FLAC and WAV, with bitrate, channels and sample rate | Implemented and tested |
| `Ffmpeg\FfmpegAudio`: transcoding, excerpts, metadata and waveforms, bounded in bytes, duration and time | Implemented and tested with a scripted runner |
| `Ffmpeg\FfprobeReader`: the report of ffprobe as `AudioMetadata` | Implemented, tested and fuzzed |
| `Peaks`, `PeaksReader`, `PeaksComputer`: the waveform as documented JSON, bars for a player | Implemented, tested, fuzzed and benchmarked |
| `NullAudio`: no tool | Implemented and tested |
| Other tools behind the ports — SoX, a transcoding service | Not started: an adapter of the application |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, ffmpeg. |
| [Transcoding and metadata](doc/transcoding.md) | Renditions, excerpts, metadata, the limits of each run. |
| [The waveform](doc/waveform.md) | The JSON of the peaks, computing them, the bars of a player. |
| [Listening](doc/listening.md) | The track, its files, the listening policy, chapters and transcripts. |
| [Privacy and accessibility](doc/privacy-and-accessibility.md) | What leaves the server, what ffmpeg may reach, what a player owes its listeners. |
| [Extension points](doc/extension-points.md) | The ports an application implements or replaces. |
| [Testing](doc/testing.md) | The tests, the fuzz targets, the benchmarks, ffmpeg in integration. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/audio
```

```php
use Phil\Audio\AudioFormat;
use Phil\Audio\Excerpt;
use Phil\Audio\Ffmpeg\FfmpegAudio;
use Phil\Audio\Rendition;

$ffmpeg = new FfmpegAudio();

$metadata = $ffmpeg->read('/uploads/episode.wav');                  // duration, codec, tags
$ffmpeg->transcode('/uploads/episode.wav', '/renditions/episode.mp3', new Rendition(AudioFormat::Mp3, 128));
$ffmpeg->transcode('/uploads/episode.wav', '/renditions/excerpt.mp3', new Rendition(AudioFormat::Mp3, 128), new Excerpt(30_000, 30_000));
$peaks = $ffmpeg->peaks('/uploads/episode.wav');                     // ten runs a second
file_put_contents('/renditions/episode.peaks.json', $peaks->toJson());
```

## Requirements

PHP 8.5 or later, with `ext-sodium`. ffmpeg and ffprobe, for `FfmpegAudio`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the [code of
conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md) explains, never in
a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
