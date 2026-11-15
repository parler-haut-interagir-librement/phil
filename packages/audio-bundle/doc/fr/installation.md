<!-- phil-dev source-sha256=fc8b3ffae5a6b3eac92ad0c27f55325b44ae8938d03de4f436f5692e73a73b5f -->
# Installation

## Prérequis

| Paquet | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récente, avec `sodium` | Classes en lecture seule ; noms aléatoires de fichiers temporaires |
| `phil/audio` | `^0.1` | Les outils, les versions, la forme d'onde, le modèle d'une piste |
| `phil/timed-text` | `^0.1` | Les instants des chapitres et des repères que montre le lecteur |
| `symfony/config`, `symfony/dependency-injection`, `symfony/messenger` | `^8.2` | Le bundle, sa configuration et ses tâches |
| `symfony/ux-twig-component` | `^3.7`, facultatif | Le composant du lecteur |
| `phil/media-bundle` | `^0.1.1`, facultatif | Les téléversements, les copies locales et le stockage des tâches ; son `MediaLibrary::readOriginal()` lit l'original en flux vers la copie |

## Activer le bundle

```sh
composer require phil/audio-bundle
```

Symfony Flex enregistre le bundle et copie sa configuration depuis la recette. Sans Flex :

```php
// config/bundles.php
return [
    // …
    Phil\AudioBundle\PhilAudioBundle::class => ['all' => true],
];
```

Avec `tools: ffmpeg`, les programmes ffmpeg et ffprobe s'exécutent sur la machine des workers ; voyez l'installation de
phil/audio.

## La recette

`recipe/0.1` enregistre le bundle et copie `config/packages/phil_audio.yaml` : aucun outil, une version MP3, la piste
entière pour toute personne qui écoute. Passez `tools` à `ffmpeg` une fois ffmpeg installé sur la machine des workers.

## La recette du kit

Le lecteur dessine ses liens avec la recette Button du kit PHIL UX, installée dans l'application par le Symfony UX
Toolkit ; rien n'est rendu depuis `vendor/` :

```sh
composer require symfony/ux-twig-component
php bin/console ux:install button --kit=github.com/parler-haut-interagir-librement/ux-kit
```

La recette a le nom, les propriétés et les blocs du Button du kit Shadcn ; un site sur Tailwind installe donc celui du
kit Shadcn. Elle se trouve sous le sous-répertoire des composants de la configuration `ux_toolkit` ; dans un
environnement où le Toolkit n'est pas activé, répétez-le dans `kit_subdirectory`.

## Le contrôleur Stimulus

Le contrôleur `phil--audio-bundle--player` est livré dans `assets/` avec sa feuille de style ; la recette Flex de
Symfony UX l'ajoute à `assets/controllers.json`, ou l'application l'enregistre à la main. Il a besoin de
`@hotwired/stimulus` 3.

## Messenger

Les tâches sont des messages : acheminez-les vers un transport asynchrone, afin que le transcodage s'exécute dans un
worker.

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
