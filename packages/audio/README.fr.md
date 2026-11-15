<!-- phil-dev source-sha256=e12c627fd78464586f9861f6857536dae221bd50419bc46ddbb8d9b850c1b067 -->
# phil/audio

**L'audio en PHP : transcodage derrière un port, métadonnées, une forme d'onde calculée une fois sur le serveur, extraits,
politique d'écoute du créateur, et chapitres et transcriptions dans le modèle de phil/timed-text.**

phil/audio décrit une piste telle qu'un lecteur la montre — ses fichiers, sa durée, sa forme d'onde, ses chapitres et ses
transcriptions — et fait le travail qu'exige un téléversement : lire ce que le fichier dit de lui-même, produire les
versions qu'un site sert, découper un extrait et calculer les crêtes qu'un lecteur dessine. Les outils sont derrière trois
ports : `FfmpegAudio` exécute ffmpeg et ffprobe comme programmes externes, bornés et fermés au réseau ; `NullAudio`
n'exécute rien, pour un site qui sert tels quels les fichiers que ses créateurs téléversent.

Le créateur choisit ce qu'un auditeur qui n'a pas acheté une piste peut entendre : la piste entière, un extrait, ou rien
avant l'achat. `Audio::playable()` donne les fichiers que le lecteur de cet auditeur peut récupérer.

C'est une bibliothèque PHP simple dont les prérequis sont `ext-sodium`, `phil/timed-text`, `phil/url` et
`symfony/process`. Aucun codec ne s'exécute en PHP ; la forme d'onde est calculée à partir des échantillons que ffmpeg
décode, bloc par bloc.

La conception : `Audio`, `AudioSource`, `AudioTranscript`, `ListeningPolicy`, `Excerpt` et `Peaks` sont le modèle ;
`Contract\Transcoder`, `Contract\MetadataReader` et `Contract\PeaksGenerator` sont les ports, `Ffmpeg\FfmpegAudio` et
`NullAudio` leurs adaptateurs ; `Contract\CommandRunner` exécute un programme, `Ffmpeg\ProcessCommandRunner` par Symfony
Process ; `PeaksReader`, `PeaksComputer` et `Ffmpeg\FfprobeReader` lisent ce qui vient de l'extérieur.

## État

| Partie | État |
|---|---|
| `Audio`, `AudioSource`, `AudioTranscript` : une piste, ses fichiers, ses chapitres et ses transcriptions de phil/timed-text | Implémentée et testée |
| `ListeningPolicy`, `ListeningMode`, `Excerpt` : la piste entière, un extrait ou rien avant un achat | Implémentée et testée |
| `Rendition`, `AudioFormat` : MP3, AAC, Opus, FLAC et WAV, avec débit, canaux et fréquence d'échantillonnage | Implémentée et testée |
| `Ffmpeg\FfmpegAudio` : transcodage, extraits, métadonnées et formes d'onde, bornés en octets, en durée et en temps | Implémentée et testée avec un exécuteur scénarisé |
| `Ffmpeg\FfprobeReader` : le rapport de ffprobe en `AudioMetadata` | Implémentée, testée et soumise au fuzzing |
| `Peaks`, `PeaksReader`, `PeaksComputer` : la forme d'onde en JSON documenté, des barres pour un lecteur | Implémentée, testée, soumise au fuzzing et mesurée par des bancs d'essai |
| `NullAudio` : aucun outil | Implémentée et testée |
| Autres outils derrière les ports — SoX, un service de transcodage | Non commencée : un adaptateur de l'application |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, ffmpeg. |
| [Transcodage et métadonnées](doc/fr/transcoding.md) | Les versions, les extraits, les métadonnées, les limites de chaque exécution. |
| [La forme d'onde](doc/fr/waveform.md) | Le JSON des crêtes, leur calcul, les barres d'un lecteur. |
| [Écoute](doc/fr/listening.md) | La piste, ses fichiers, la politique d'écoute, les chapitres et les transcriptions. |
| [Vie privée et accessibilité](doc/fr/privacy-and-accessibility.md) | Ce qui quitte le serveur, ce que ffmpeg peut atteindre, ce qu'un lecteur doit à ses auditeurs. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports qu'une application implémente ou remplace. |
| [Tests](doc/fr/testing.md) | Les tests, les cibles de fuzzing, les bancs d'essai, ffmpeg en intégration. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/audio
```

```php
use Phil\Audio\AudioFormat;
use Phil\Audio\Excerpt;
use Phil\Audio\Ffmpeg\FfmpegAudio;
use Phil\Audio\Rendition;

$ffmpeg = new FfmpegAudio();

$metadata = $ffmpeg->read('/uploads/episode.wav');                  // duration, codec, tags
$ffmpeg->transcode('/uploads/episode.wav', '/renditions/episode.mp3', new Rendition(AudioFormat::Mp3, 128));
$ffmpeg->transcode('/uploads/episode.wav', '/renditions/excerpt.mp3', new Rendition(AudioFormat::Mp3, 128), new Excerpt(30_000, 30_000));
$peaks = $ffmpeg->peaks('/uploads/episode.wav');                     // ten runs a second
file_put_contents('/renditions/episode.peaks.json', $peaks->toJson());
```

## Prérequis

PHP 8.5 ou une version plus récente, avec `ext-sodium`. ffmpeg et ffprobe, pour `FfmpegAudio`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
