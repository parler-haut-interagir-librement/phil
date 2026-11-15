# phil/audio-bundle

**phil/audio in Symfony: ffmpeg or no tool from the configuration, renditions and waveforms produced by Messenger jobs
that carry identifiers only, uploads through phil/media-bundle, the creator's default listening policy, and a player
that stays a browser `<audio>` without JavaScript.**

The bundle configures the tools of phil/audio — `null`, which serves the files as they were uploaded, or `ffmpeg` —, the
renditions produced from each upload, and the waveform. `AudioJobs::schedule()` dispatches a message per rendition, per
rendition of the excerpt when the creator offers one, and one for the waveform; each message carries the identifier of
the file and nothing else, and its handler fetches a local copy, runs the tool, keeps the result and deletes its
temporary files. With phil/media-bundle installed, the uploads come from its library and the results go to its storage.

`<twig:PhilAudio:Player :audio="track" :entitled="bought" />` draws a track: the browser's `<audio>`, with its own
controls, the files this listener may hear, the waveform, the chapters and the transcripts, whose links open the instant
in the file without JavaScript. A light Stimulus controller draws the waveform in SVG, seeks the audio from a chapter or
a cue, and marks the current one. The links are the recipe Button of the PHIL UX kit — or of the Shadcn kit — installed
by the Symfony UX Toolkit.

## Status

| Part | State |
|---|---|
| The configuration `phil_audio`: tools, ffmpeg and its limits, renditions, waveform, default listening policy, storage | Implemented and tested |
| `AudioJobs`, `TranscodeAudio`, `ComputeWaveform` and their handlers: jobs of identifiers, temporary files deleted | Implemented and tested |
| `MediaLibraryAudioFiles`, `MediaStorageRenditions`: uploads and results through phil/media-bundle, the original streamed to the local copy when phil/media-bundle can | Implemented and tested |
| The Flex recipe `recipe/0.1`: the bundle and its configuration | Ready |
| `PlayerComponent` and its template: the audio element, the excerpt or nothing before a purchase, chapters and transcripts | Implemented and tested in a booted kernel |
| The Stimulus controller `player`: waveform in SVG, seeking, current chapter and cue | Implemented and tested under Node |
| Serving the renditions to entitled listeners only | The application's: a signed address or a controller of its own |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, enabling the bundle, its Flex recipe, the recipes of the kit, the controller. |
| [Configuration](doc/configuration.md) | Every key of `phil_audio`. |
| [The jobs](doc/jobs.md) | Uploads, renditions, excerpts and waveforms by Messenger. |
| [The player](doc/player.md) | The component, its props, what it draws and what the controller adds. |
| [Privacy and accessibility](doc/privacy-and-accessibility.md) | What the messages carry, what the player owes its listeners. |
| [Extension points](doc/extension-points.md) | The ports and services an application replaces. |
| [Testing](doc/testing.md) | The tests, the functional application, the controller under Node. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```yaml
# config/packages/phil_audio.yaml
phil_audio:
    tools: ffmpeg
    renditions:
        mp3: { format: mp3, bitrate: 128 }
        opus: { format: opus, bitrate: 64 }
```

```php
$media = $library->upload($uploadedFile->getPathname(), $uploadedFile->getClientOriginalName());
$audioJobs->schedule($media->id, new Excerpt(30_000, 30_000));
```

```twig
<twig:PhilAudio:Player :audio="track" :entitled="is_granted('LISTEN', track)" id="episode-12" />
```

## Requirements

PHP 8.5 or later, with `ext-sodium`; Symfony 8.2 or later with Messenger; Symfony UX Twig Component 3.7 or later and
the recipe Button of the kit for the player; phil/media-bundle for uploads and storage, or services of the application.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the [code of
conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md) explains, never in
a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
