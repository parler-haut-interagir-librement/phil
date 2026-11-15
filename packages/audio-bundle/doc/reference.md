# Reference

| Type | Role |
|---|---|
| `PhilAudioBundle` | The bundle and its configuration `phil_audio` |
| `Application\AudioJobs` | Schedules the renditions, the renditions of the excerpt and the waveform of an upload |
| `Application\Renditions` | The renditions of the configuration, by name |
| `Application\Message\TranscodeAudio` | The job of one rendition: identifier, rendition, excerpt |
| `Application\Message\ComputeWaveform` | The job of the waveform: identifier |
| `Application\Handler\TranscodeAudioHandler` | Produces a rendition and deletes its temporary files |
| `Application\Handler\ComputeWaveformHandler` | Computes and keeps the waveform |
| `Application\Contract\AudioFiles` | Local copies of the uploads |
| `Application\Contract\RenditionStore` | Where renditions and waveforms are kept |
| `Application\Exception\AudioNotFound` | No upload has the identifier |
| `Application\Exception\UnknownRendition` | No rendition has the name |
| `Infrastructure\MediaBundle\MediaLibraryAudioFiles` | Local copies from phil/media-bundle's library, streamed |
| `Infrastructure\MediaBundle\MediaStorageRenditions` | Renditions and waveforms in phil/media-bundle's storage |
| `Infrastructure\Twig\PlayerComponent` | `PhilAudio:Player` |
| `Infrastructure\DependencyInjection\AudioServices` | The services, from the configuration |
| `Infrastructure\DependencyInjection\KitSubdirectoryPass` | The subdirectory of the recipes of the kit |
| `Infrastructure\DependencyInjection\JobsPass` | Removes the jobs without a message bus |
