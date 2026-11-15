<!-- phil-dev source-sha256=a8405ee8a93d341eb8e84dbbaa1676ff1ca395ce4de3cc2097d1e6600d23d541 -->
# Configuration

```yaml
phil_audio:
    tools: 'null'                   # or "ffmpeg"
    ffmpeg:
        binary: ffmpeg
        ffprobe_binary: ffprobe
        timeout: 900                # seconds of one run
        max_input_bytes: 2147483648
        max_output_bytes: 1073741824
        max_duration: 21600         # seconds of a file read
    temporary_directory: null       # the system's
    renditions:
        mp3: { format: mp3, bitrate: 128, channels: null, sample_rate: null }
    waveform:
        samples_per_pixel: 800
    listening:
        mode: full                  # full, excerpt or purchase_only
        excerpt_start: 0            # seconds
        excerpt_duration: 30        # seconds
    storage:
        files: null                 # an AudioFiles service; phil/media-bundle's when null
        renditions: null            # a RenditionStore service; phil/media-bundle's when null
        prefix: audio
    kit_subdirectory: null
```

| Clé | Valeur par défaut | Sens |
|---|---|---|
| `tools` | `null` | `null` enregistre `NullAudio` : aucune tâche ne s'exécute, les fichiers sont servis tels que téléversés. `ffmpeg` enregistre `FfmpegAudio`. L'un ou l'autre est le service `phil_audio.tools`, aliasé à `Transcoder`, `MetadataReader` et `PeaksGenerator` |
| `ffmpeg.*` | ci-dessus | Les programmes et les `FfmpegLimits` de chaque exécution, lus avec `tools: ffmpeg` |
| `temporary_directory` | `null` | Où les tâches écrivent leurs fichiers temporaires — les copies locales, les versions avant leur stockage et les échantillons d'une forme d'onde —, supprimés quoi qu'il arrive ; celui du système quand elle est nulle. Un répertoire sur le disque même des workers, accessible en écriture par eux seuls |
| `renditions` | un MP3 à 128 kbit/s | Les versions produites à partir de chaque téléversement, par nom — lettres, chiffres, `_` et `-`. Une version qu'aucun encodeur n'accepte, un débit pour FLAC ou une fréquence d'échantillonnage qu'Opus refuse, est refusée à la construction du conteneur |
| `waveform.samples_per_pixel` | `800` | Les échantillons de chaque tronçon, à 8000 Hz : dix tronçons par seconde |
| `listening` | `full` | La politique par défaut d'un créateur qui n'a pas choisi : le service `phil_audio.listening_policy`, aliasé à `ListeningPolicy` |
| `storage.files`, `storage.renditions` | `null` | Les services des tâches ; ceux de phil/media-bundle quand il est installé. Sans les deux, aucune tâche n'est enregistrée |
| `storage.prefix` | `audio` | Le dossier des résultats dans le stockage de phil/media-bundle |
| `kit_subdirectory` | `null` | L'endroit des recettes du kit sous le répertoire des composants ; le sous-répertoire de `ux_toolkit` quand elle est nulle |
