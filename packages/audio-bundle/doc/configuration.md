# Configuration

```yaml
phil_audio:
    tools: 'null'                   # or "ffmpeg"
    ffmpeg:
        binary: ffmpeg
        ffprobe_binary: ffprobe
        timeout: 900                # seconds of one run
        max_input_bytes: 2147483648
        max_output_bytes: 1073741824
        max_duration: 21600         # seconds of a file read
    temporary_directory: null       # the system's
    renditions:
        mp3: { format: mp3, bitrate: 128, channels: null, sample_rate: null }
    waveform:
        samples_per_pixel: 800
    listening:
        mode: full                  # full, excerpt or purchase_only
        excerpt_start: 0            # seconds
        excerpt_duration: 30        # seconds
    storage:
        files: null                 # an AudioFiles service; phil/media-bundle's when null
        renditions: null            # a RenditionStore service; phil/media-bundle's when null
        prefix: audio
    kit_subdirectory: null
```

| Key | Default | Meaning |
|---|---|---|
| `tools` | `null` | `null` registers `NullAudio`: no job runs, the files are served as uploaded. `ffmpeg` registers `FfmpegAudio`. Either is the service `phil_audio.tools`, aliased to `Transcoder`, `MetadataReader` and `PeaksGenerator` |
| `ffmpeg.*` | above | The programs and the `FfmpegLimits` of each run, read with `tools: ffmpeg` |
| `temporary_directory` | `null` | Where the jobs write their temporary files — the local copies, the renditions before they are stored and the samples of a waveform —, deleted whatever happens; the system's when null. A directory on the workers' own disk, writable by them only |
| `renditions` | one MP3 at 128 kbit/s | The renditions produced from each upload, by name — letters, digits, `_` and `-`. A rendition no encoder takes, a bitrate for FLAC or a sample rate Opus refuses, is refused when the container is built |
| `waveform.samples_per_pixel` | `800` | The samples of each run, at 8000 Hz: ten runs a second |
| `listening` | `full` | The default policy of a creator who has not chosen: the service `phil_audio.listening_policy`, aliased to `ListeningPolicy` |
| `storage.files`, `storage.renditions` | `null` | The services of the jobs; phil/media-bundle's when installed. Without both, no job is registered |
| `storage.prefix` | `audio` | The folder of the results in the storage of phil/media-bundle |
| `kit_subdirectory` | `null` | Where the recipes of the kit are under the components directory; the subdirectory of `ux_toolkit` when null |
