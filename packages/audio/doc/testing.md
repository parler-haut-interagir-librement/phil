# Testing

## The library's tests

```sh
vendor/bin/phpunit packages/audio/tests
```

ffmpeg and ffprobe never run in the unit suite: `FfmpegAudio` is given a scripted `CommandRunner`, which checks each
command argument by argument and writes the files ffmpeg would write. `ProcessCommandRunner` is tested on PHP itself,
the one program every machine of the tests has: arguments passed as they are, an output past its bound stopped, a run
past its timeout stopped, a program that cannot start. `PeaksComputer` is fed samples in strings and in a stream that
gives three bytes at a time, so that a sample split between two reads is joined.

The library is held to 100 % covered mutation score by Infection.

## Fuzzing

| Target | What it fuzzes |
|---|---|
| `peaks` | `PeaksReader::read()`, then `Peaks::amplitudes()` |
| `ffprobe` | `Ffmpeg\FfprobeReader::read()` |

```sh
composer fuzz -- peaks ffprobe
```

## Benchmarks

`tests/Benchmark/PeaksBench.php` computes the peaks of ten minutes of samples at 8 kHz — 4.8 million samples — in about
0.7 s, and reads the peaks of an hour and reduces them to 200 bars in about 20 ms.

## With a real ffmpeg

The suite needs no ffmpeg. To check a build of ffmpeg against the commands, run `FfmpegAudio` on a file of your own:

```php
$ffmpeg = new Phil\Audio\Ffmpeg\FfmpegAudio();
$ffmpeg->transcode('sample.wav', 'sample.opus', new Phil\Audio\Rendition(Phil\Audio\AudioFormat::Opus));
var_dump($ffmpeg->read('sample.opus'), $ffmpeg->peaks('sample.opus')->length());
```
