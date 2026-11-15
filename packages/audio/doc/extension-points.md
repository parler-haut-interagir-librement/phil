# Extension points

The library is plain PHP and declares no service. What an application adds goes through four contracts and the
arguments of the adapters.

## Contracts

| Contract | Method | Contract |
|---|---|---|
| `Contract\Transcoder` | `transcode(string $source, string $target, Rendition, ?Excerpt): void` | Writes the rendition to the target, whole or the excerpt; throws an `AudioException` |
| `Contract\MetadataReader` | `read(string $path): AudioMetadata` | Reads what the file says of itself |
| `Contract\PeaksGenerator` | `peaks(string $path, int $samplesPerPixel = 800): Peaks` | Computes the waveform |
| `Contract\CommandRunner` | `run(array $command, float $timeout, int $maxOutputBytes): CommandResult` | Runs a program from its arguments, stops it at its bounds |

A transcoding service is a `Transcoder` of the application; a site without ffmpeg configures `NullAudio`; a container
that runs ffmpeg elsewhere is a `CommandRunner`. Each keeps no state between calls, so that a worker serves one job
after another.

## Arguments

| Point | How |
|---|---|
| The programs | The paths of ffmpeg and ffprobe, given to `FfmpegAudio` |
| The bounds of each run | `FfmpegLimits` |
| Where the samples of a waveform are written | The temporary directory of `FfmpegAudio` |
| The runs of a waveform | The limit of `PeaksComputer` and of `PeaksReader` |
| Catching the library's errors | Every exception implements `Exception\AudioException` |

## Stability

The contracts, the model and the adapters are the public API, at 0.1: they may change in a minor version until 1.0.
