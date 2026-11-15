# The waveform

A waveform is computed once, when a file is uploaded, and kept as JSON; a player reads it and draws a few hundred bars.
Nothing heavy reaches the listener.

## The JSON of the peaks

```json
{"version":2,"channels":1,"sample_rate":8000,"samples_per_pixel":800,"bits":8,"length":3,"data":[-10,12,-128,127,0,0]}
```

| Key | Value |
|---|---|
| `version` | 2; a document of version 1, without this key, is read too |
| `channels` | 1: one channel, the sound mixed down |
| `sample_rate` | The rate the samples were decoded at, in hertz |
| `samples_per_pixel` | The samples of each run |
| `bits` | 8 or 16: the range of the values, -128 to 127 or -32768 to 32767 |
| `length` | The number of runs |
| `data` | For each run, its lowest then its highest sample |

The shape is the one the BBC's audiowaveform tool writes, so that a waveform it computed is read as well; its code is
not used.

`Peaks::toJson()` writes it; `PeaksReader::read()` reads it, refusing a document past 8 MiB or a million runs
(`LimitExceeded`), and anything that is not this shape (`InvalidAudio`): another version, several channels, data that
is not pairs of integers within the bits, a length that does not match.

## Computing the peaks

`FfmpegAudio::peaks($path, $samplesPerPixel)` asks ffmpeg for the samples of the file, mixed to one channel at
8000 Hz, signed 16-bit little-endian, into a temporary file, then computes the peaks from it and deletes it. With the
default 800 samples a run, a waveform holds ten runs a second: an hour is 36 000 runs, about 200 KB of JSON.

`PeaksComputer` does the computing alone, from any stream of such samples, read in chunks of 64 KiB: an hour of sound
never sits in memory. A run that the end of the stream leaves short is kept; a byte that ends the stream alone is not a
sample and is dropped. It refuses to build more than a million runs.

```php
$peaks = new PeaksComputer()->fromStream($pipe, 8_000, 800);
```

## The bars of a player

`Peaks::amplitudes($width)` reduces the waveform to `$width` bars, each from 0 to 1 rounded to three decimals: the
loudest sample of the runs it covers, over the largest value of the bits. A waveform with fewer runs gives fewer bars.

```php
$bars = PeaksReader::read($json)->amplitudes(200);   // [0.094, 1.0, 0.0, …]
```

`phil/audio-bundle` gives these bars to its Stimulus controller, which draws them in SVG.
