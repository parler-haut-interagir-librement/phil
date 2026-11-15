# Installation

## Requirements

| Package | Version | Why |
|---|---|---|
| PHP | 8.5 or later, with `sodium` | Readonly classes; random names of temporary files |
| `phil/audio` | `^0.1` | The tools, the renditions, the waveform, the model of a track |
| `phil/timed-text` | `^0.1` | The instants of the chapters and cues the player shows |
| `symfony/config`, `symfony/dependency-injection`, `symfony/messenger` | `^8.2` | The bundle, its configuration and its jobs |
| `symfony/ux-twig-component` | `^3.7`, optional | The player component |
| `phil/media-bundle` | `^0.1.1`, optional | Uploads, local copies and storage of the jobs; its `MediaLibrary::readOriginal()` streams the original into the copy |

## Enabling the bundle

```sh
composer require phil/audio-bundle
```

Symfony Flex registers the bundle and copies its configuration from the recipe. Without Flex:

```php
// config/bundles.php
return [
    // …
    Phil\AudioBundle\PhilAudioBundle::class => ['all' => true],
];
```

With `tools: ffmpeg`, the programs ffmpeg and ffprobe run on the machine of the workers; see the installation of
phil/audio.

## The recipe

`recipe/0.1` registers the bundle and copies `config/packages/phil_audio.yaml`: no tool, one MP3 rendition, the whole
track for every listener. Switch `tools` to `ffmpeg` once ffmpeg is on the machine of the workers.

## The recipe of the kit

The player draws its links with the recipe Button of the PHIL UX kit, installed in the application by the Symfony UX
Toolkit; nothing is rendered from `vendor/`:

```sh
composer require symfony/ux-twig-component
php bin/console ux:install button --kit=github.com/parler-haut-interagir-librement/ux-kit
```

The recipe has the name, props and blocks of the Shadcn kit's Button, so a site on Tailwind installs the Shadcn kit's
instead. It is found under the components subdirectory of the `ux_toolkit` configuration; in an environment where the
Toolkit is not enabled, repeat it in `kit_subdirectory`.

## The Stimulus controller

The controller `phil--audio-bundle--player` ships in `assets/` with its stylesheet; Symfony UX's Flex recipe adds it
to `assets/controllers.json`, or the application registers it by hand. It needs `@hotwired/stimulus` 3.

## Messenger

The jobs are messages: route them to an asynchronous transport, so that transcoding runs in a worker.

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            audio: '%env(MESSENGER_TRANSPORT_DSN)%'
        routing:
            Phil\AudioBundle\Application\Message\TranscodeAudio: audio
            Phil\AudioBundle\Application\Message\ComputeWaveform: audio
```
