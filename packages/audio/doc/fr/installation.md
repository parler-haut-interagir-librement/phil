<!-- phil-dev source-sha256=8fe58739f0a8c3859d0ae48f8a445cb372f789ca7eb85bf7543029bbc6134904 -->
# Installation

```sh
composer require phil/audio
```

| Prérequis | Pourquoi |
|---|---|
| PHP 8.5 ou une version plus récente, avec `ext-sodium` | Classes en lecture seule ; noms aléatoires de fichiers temporaires écrits en hexadécimal sans fuite par le temps d'exécution |
| `phil/timed-text` | Les chapitres et les transcriptions d'une piste, et les instants d'un extrait |
| `phil/url` | Les adresses des fichiers d'une piste |
| `symfony/process` | `FfmpegAudio` y démarre ffmpeg et ffprobe |

## ffmpeg

`FfmpegAudio` exécute les programmes `ffmpeg` et `ffprobe`, trouvés dans le `PATH` ou nommés par leur chemin :

```php
use Phil\Audio\Ffmpeg\FfmpegAudio;
use Phil\Audio\Ffmpeg\FfmpegLimits;
use Phil\Audio\Ffmpeg\ProcessCommandRunner;

$ffmpeg = new FfmpegAudio(new ProcessCommandRunner(), '/usr/bin/ffmpeg', '/usr/bin/ffprobe', new FfmpegLimits(timeout: 600.0));
```

Les encodeurs que demandent les versions sont ceux des constructions usuelles : `libmp3lame` pour MP3, `aac` pour AAC,
`libopus` pour Opus, `flac` et `pcm_s16le`. Une construction qui en manque un refuse la version, ce que `FfmpegAudio`
signale par `AudioToolFailed`. Un site qui ne sert que ce que ses créateurs téléversent utilise `NullAudio` et n'a pas
besoin de ffmpeg.

ffmpeg est sous licence LGPL ou GPL, selon sa construction ; il s'exécute comme programme distinct, et rien de lui n'est
lié à PHP.

## Dans Symfony

`phil/audio-bundle` configure les outils, les versions et les tâches qui les produisent.
