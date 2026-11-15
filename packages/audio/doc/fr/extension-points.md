<!-- phil-dev source-sha256=6679742254fc24cd597392ed8b0728f7b098d0d856e6c45702171db9b32c1ae2 -->
# Points d'extension

La bibliothèque est du PHP simple et ne déclare aucun service. Ce qu'une application ajoute passe par quatre contrats et
par les arguments des adaptateurs.

## Contrats

| Contrat | Méthode | Rôle |
|---|---|---|
| `Contract\Transcoder` | `transcode(string $source, string $target, Rendition, ?Excerpt): void` | Écrit la version dans la cible, entière ou l'extrait ; lance une `AudioException` |
| `Contract\MetadataReader` | `read(string $path): AudioMetadata` | Lit ce que le fichier dit de lui-même |
| `Contract\PeaksGenerator` | `peaks(string $path, int $samplesPerPixel = 800): Peaks` | Calcule la forme d'onde |
| `Contract\CommandRunner` | `run(array $command, float $timeout, int $maxOutputBytes): CommandResult` | Exécute un programme à partir de ses arguments, l'arrête à ses bornes |

Un service de transcodage est un `Transcoder` de l'application ; un site sans ffmpeg configure `NullAudio` ; un conteneur
qui exécute ffmpeg ailleurs est un `CommandRunner`. Chacun ne garde aucun état entre deux appels, de sorte qu'un worker
sert une tâche après l'autre.

## Arguments

| Point | Comment |
|---|---|
| Les programmes | Les chemins de ffmpeg et de ffprobe, donnés à `FfmpegAudio` |
| Les bornes de chaque exécution | `FfmpegLimits` |
| Où s'écrivent les échantillons d'une forme d'onde | Le répertoire temporaire de `FfmpegAudio` |
| Les tronçons d'une forme d'onde | La limite de `PeaksComputer` et de `PeaksReader` |
| Attraper les erreurs de la bibliothèque | Toute exception implémente `Exception\AudioException` |

## Stabilité

Les contrats, le modèle et les adaptateurs sont l'API publique, en 0.1 : ils peuvent changer dans une version mineure
jusqu'à la 1.0.
