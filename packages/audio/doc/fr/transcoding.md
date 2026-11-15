<!-- phil-dev source-sha256=b9aa65a0a6128861bd2d3284d80b042ba2ff0af31a872a0b36fb498da6fd7aef -->
# Transcodage et métadonnées

## Versions

Une `Rendition` est une version d'un fichier à produire : un `AudioFormat`, ainsi que le débit, les canaux et la
fréquence d'échantillonnage demandés à l'encodeur, chacun laissé à l'encodeur quand il est nul.

| Format | Type de média | Extension | Encodeur, multiplexeur | Débit par défaut |
|---|---|---|---|---|
| `Mp3` | `audio/mpeg` | `mp3` | `libmp3lame`, `mp3` | 128 kbit/s |
| `Aac` | `audio/mp4` | `m4a` | `aac`, `ipod` | 128 kbit/s |
| `Opus` | `audio/ogg; codecs=opus` | `opus` | `libopus`, `ogg` | 96 kbit/s |
| `Flac` | `audio/flac` | `flac` | `flac`, `flac` | aucun : sans perte |
| `Wav` | `audio/wav` | `wav` | `pcm_s16le`, `wav` | aucun : sans perte |

Un débit va de 8 à 512 kbit/s et n'est jamais donné à un format sans perte ; les canaux sont 1 ou 2 ; une fréquence
d'échantillonnage est l'une de 8000, 11025, 12000, 16000, 22050, 24000, 32000, 44100 ou 48000 Hz, et pour Opus l'une de
8000, 12000, 16000, 24000 ou 48000 Hz. Toute autre valeur est refusée avec `InvalidAudio` à la construction de la
version, avant qu'aucun outil ne s'exécute. `AudioFormat::fromMediaType()` trouve un format par son type de média et ses
alias usuels ; `audio/ogg` est Opus, sauf si son paramètre `codecs` nomme un autre codec.

```php
$ffmpeg->transcode($source, $target, new Rendition(AudioFormat::Opus, 64, channels: 1, sampleRate: 48_000));
```

La cible est écrite par ffmpeg, et remplacée si elle existe. Une version ne garde que le premier flux audio, sans vidéo
ni pochette, et sans les étiquettes de la source : le titre, l'auteur et tout ce qu'un enregistreur a écrit restent dans
le fichier téléversé.

## Extraits

Un `Excerpt` est un début et une durée en millisecondes. Donné à `transcode()`, il découpe la version : ffmpeg se place
au début avant de lire, et s'arrête après la durée.

```php
$ffmpeg->transcode($source, $excerptFile, new Rendition(AudioFormat::Mp3), new Excerpt(30_000, 30_000));
```

`Excerpt::within($duration)` ramène un extrait à un fichier de cette durée, ou donne null quand il commence après la
fin.

## Métadonnées

`read()` exécute ffprobe sur le fichier et retourne un `AudioMetadata` : la durée en millisecondes, le conteneur, le
codec, le débit en bits par seconde, la fréquence d'échantillonnage, les canaux, et les étiquettes du conteneur et du
premier flux audio, chaque nom en minuscules. Une valeur que le fichier ne donne pas est nulle.

`Ffmpeg\FfprobeReader` lit seul le rapport JSON de ffprobe, de façon bornée : au plus 1 Mio par défaut, 64 étiquettes de
1 Kio chacune ; une étiquette plus longue ou qui n'est pas du texte est écartée, et un rapport sans flux audio est
refusé avec `InvalidAudio`.

## Les limites de chaque exécution

`FfmpegLimits` borne chaque exécution :

| Limite | Valeur par défaut | Quand elle est dépassée |
|---|---|---|
| `timeout` | 900 secondes | L'exécution est arrêtée et `LimitExceeded` est lancée |
| `maxInputBytes` | 2 Gio | Le fichier est refusé avant qu'aucun outil ne le lise |
| `maxOutputBytes` | 1 Gio | ffmpeg cesse d'écrire à la limite (`-fs`) ; un fichier qui l'atteint est supprimé et refusé |
| `maxDuration` | 6 heures | Un fichier plus long, selon ffprobe, est refusé avant que ffmpeg ne le décode |
| `maxReportBytes` | 1 Mio | Un programme qui affiche davantage est arrêté |

Un fichier dont ffprobe ne peut pas dire la durée reste borné par la sortie et le temps.

## Erreurs

| Exception | Quand |
|---|---|
| `InvalidAudio` | Une valeur refusée, ou un rapport qui n'est pas du JSON ou n'a pas de flux audio |
| `LimitExceeded` | L'une des limites ci-dessus |
| `AudioToolFailed` | Le programme s'est exécuté et a refusé le fichier : sa première ligne d'erreur, d'au plus 500 octets, est le message |
| `AudioToolUnavailable` | Le programme est introuvable ou inexécutable, ou `NullAudio` est configuré |

Toutes implémentent `Exception\AudioException`.
