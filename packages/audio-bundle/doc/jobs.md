# The jobs

## From an upload

An audio file is uploaded through phil/media-bundle, or kept by the application. Then:

```php
use Phil\Audio\Excerpt;
use Phil\AudioBundle\Application\AudioJobs;

$audioJobs->schedule($mediaId);                                // each rendition, the waveform
$audioJobs->schedule($mediaId, new Excerpt(30_000, 30_000));   // and each rendition of the excerpt
```

`schedule()` dispatches, on the default bus:

| Message | Carries | Its handler |
|---|---|---|
| `TranscodeAudio` | the identifier, the name of the rendition, and the start and duration of the excerpt, in milliseconds, or none | asks `AudioFiles` for a local copy, transcodes it to a temporary file, gives it to `RenditionStore::save()`, deletes both |
| `ComputeWaveform` | the identifier | asks for a local copy, computes the peaks, gives them to `RenditionStore::savePeaks()`, releases the copy |

The messages carry identifiers and instants: no audio, no name, nothing of the person who uploaded it. A worker fetches
the file itself, so that a message stays a few bytes on any transport.

With `tools: null`, `schedule()` dispatches nothing. Without a message bus, `AudioJobs` and the handlers are removed.

## Failures

| What happens | The job |
|---|---|
| The file is gone, the rendition no longer configured, the excerpt invalid | Fails for good: `UnrecoverableMessageHandlingException` |
| The tool refuses the file, or a limit is passed | Fails for good |
| ffmpeg cannot be found or executed | Fails as any error, under the retry strategy of the transport, until it is installed |

Whatever happens, the local copy is released and the temporary rendition deleted.

## With phil/media-bundle

`MediaLibraryAudioFiles` copies the original of a medium to a temporary file named `phil-audio-source-<random>`, never
after the name the uploader chose, in `temporary_directory`. The file is created for this copy alone — a name that
already exists, a file or a link, is refused — and only its owner may read it. A copy that fails is deleted before the
error goes on, and `release()` deletes only the bundle's own copies.

The original is streamed into the file in chunks by `MediaLibrary::readOriginal()`, which phil/media-bundle has from
0.1.1 (older versions conflict with the bundle): with a storage that streams, such as the filesystem and Flysystem
storages of phil/media-bundle, the worker's memory does not grow with the length of the recording. An original missing
from the storage is a file gone: the job fails for good. A storage that throws, or a disk that refuses the copy, is an
ordinary error, retried under the retry strategy of the transport. The service keeps nothing between two jobs, so a
long-running worker reuses it as is.

`MediaStorageRenditions` writes, under `<prefix>/<medium>/`: `<rendition>.<ext>`, `<rendition>-excerpt.<ext>` and
`peaks.json`. An identifier or a rendition name holds letters, digits, `_` and `-` only, so that no key leaves its
folder. `peaks($mediaId)` reads the waveform back for the player.

## Serving the renditions

The renditions are files of the storage. The application serves them — a controller, a signed address of its storage —
and serves the whole files only to listeners entitled to them: the listening policy decides what a page offers, not
what a guessed address returns.
