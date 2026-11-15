# Extension points

## Ports

| Port | Default | An application replaces it to |
|---|---|---|
| `Phil\AudioBundle\Application\Contract\AudioFiles` | `MediaLibraryAudioFiles` with phil/media-bundle | Give the jobs local copies from its own storage: `storage.files` |
| `Phil\AudioBundle\Application\Contract\RenditionStore` | `MediaStorageRenditions` with phil/media-bundle | Keep renditions and waveforms elsewhere: `storage.renditions` |
| `Phil\Audio\Contract\Transcoder`, `MetadataReader`, `PeaksGenerator` | `phil_audio.tools`, from `tools` | Use another tool: decorate or replace `phil_audio.tools` |

Each keeps no state between messages, so that a worker runs one job after another.

## Services

| Service | Role |
|---|---|
| `Phil\AudioBundle\Application\AudioJobs` | Schedules the jobs of an upload |
| `Phil\AudioBundle\Application\Renditions` | The renditions of the configuration, by name |
| `phil_audio.listening_policy`, aliased `Phil\Audio\ListeningPolicy` | The default policy of a creator who has not chosen |
| `Phil\AudioBundle\Infrastructure\Twig\PlayerComponent` | `PhilAudio:Player` |

## Templates and controller

`@PhilAudio/components/Player.html.twig` is overridden as any bundle template. The Stimulus controller dispatches no
event; another controller reads `aria-current` or listens to the audio element's own events.

## Stability

The configuration, the ports, the messages and the component's props are the public API, at 0.1: they may change in a
minor version until 1.0.
