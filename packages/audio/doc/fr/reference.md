<!-- phil-dev source-sha256=dc39bb99ce54ca9914a7d839e13daf7f3a944c05d81da59d4c49a7cb382cd1aa -->
# Référence

| Type | Rôle |
|---|---|
| `Audio` | Une piste telle qu'un lecteur la montre : titre, fichiers, politique d'écoute, durée, forme d'onde, chapitres, transcriptions |
| `AudioSource` | Un fichier d'une piste : adresse, format, indication qu'il ne contient que l'extrait |
| `AudioTranscript` | La transcription d'une piste dans une langue : une `Track` de phil/timed-text, un libellé, un fichier |
| `AudioFormat` | MP3, AAC, Opus, FLAC et WAV, avec leurs types de média et leurs extensions |
| `Rendition` | Une version à produire : format, débit, canaux, fréquence d'échantillonnage |
| `Excerpt` | Une partie d'un fichier : début et durée en millisecondes |
| `ListeningMode` | La piste entière, un extrait, ou rien avant un achat |
| `ListeningPolicy` | Le choix du créateur, et ce qu'un auditeur peut entendre |
| `AudioMetadata` | Ce qu'un fichier dit de lui-même : durée, conteneur, codec, débit, fréquence d'échantillonnage, canaux, étiquettes |
| `Peaks` | Une forme d'onde : l'échantillon le plus bas et le plus haut de chaque tronçon ; son JSON et ses barres |
| `PeaksReader` | Lit le JSON des crêtes, de façon bornée |
| `PeaksComputer` | Calcule les crêtes à partir d'échantillons bruts, par blocs |
| `CommandResult` | Ce qu'un programme a laissé : code de sortie, sortie, erreurs |
| `NullAudio` | Aucun outil : chaque appel lance `AudioToolUnavailable` |
| `Contract\Transcoder`, `Contract\MetadataReader`, `Contract\PeaksGenerator` | Les ports des outils |
| `Contract\CommandRunner` | Exécute un programme à partir de ses arguments, de façon bornée |
| `Ffmpeg\FfmpegAudio` | Les trois ports sur ffmpeg et ffprobe |
| `Ffmpeg\FfmpegLimits` | Les bornes de chaque exécution |
| `Ffmpeg\FfprobeReader` | Lit le rapport JSON de ffprobe |
| `Ffmpeg\ProcessCommandRunner` | Exécute un programme par Symfony Process |
| `Exception\AudioException` | L'interface de toute exception de la bibliothèque |
| `Exception\InvalidAudio` | Une valeur refusée, ou un document illisible |
| `Exception\LimitExceeded` | Un fichier, une durée, une sortie ou une exécution au-delà de sa limite |
| `Exception\AudioToolFailed` | Un programme qui a refusé le fichier |
| `Exception\AudioToolUnavailable` | Aucun programme à exécuter |
