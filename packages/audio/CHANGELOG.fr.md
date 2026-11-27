<!-- phil-dev source-sha256=1d50a4960065d44fcb0c703e6411dc7651c1b69080dccd09bad8fba2a72ea481 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/audio` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-15

### Ajouté

- Le modèle d'une piste : `Audio` avec son titre, ses `AudioSource`, sa durée, sa forme d'onde, ses chapitres comme
  `ChapterList` et ses `AudioTranscript` comme `Track` de phil/timed-text.
- La politique d'écoute du créateur : `ListeningPolicy` et `ListeningMode` — la piste entière, un `Excerpt`, ou rien
  avant un achat —, et `Audio::playable()`, les fichiers que le lecteur d'une personne peut récupérer.
- `AudioFormat` (MP3, AAC, Opus, FLAC, WAV) et `Rendition`, qui refuse un débit, des canaux ou une fréquence
  d'échantillonnage qu'aucun encodeur n'accepte.
- Les ports `Contract\Transcoder`, `Contract\MetadataReader` et `Contract\PeaksGenerator`, et leurs adaptateurs :
  `Ffmpeg\FfmpegAudio`, sur ffmpeg et ffprobe exécutés comme programmes externes, et `NullAudio`.
- Les bornes de chaque exécution de ffmpeg, `Ffmpeg\FfmpegLimits` : les octets d'entrée, la durée, les octets de sortie,
  la sortie affichée et le temps ; le protocole `file` seulement, aucune entrée standard, des versions sans les
  étiquettes de la source.
- `Contract\CommandRunner` et `Ffmpeg\ProcessCommandRunner` : un programme exécuté à partir de la liste de ses arguments
  par Symfony Process, arrêté à son délai d'attente ou au-delà de sa sortie.
- `Ffmpeg\FfprobeReader` et `AudioMetadata` : le rapport de ffprobe, borné, avec ses étiquettes.
- La forme d'onde : `Peaks` et son JSON, la forme qu'écrit audiowaveform ; `PeaksReader`, borné ; `PeaksComputer`, à
  partir d'échantillons bruts lus par morceaux ; `Peaks::amplitudes()`, les barres d'un lecteur.
- Les exceptions, toutes des `Exception\AudioException` : `InvalidAudio`, `LimitExceeded`, `AudioToolFailed`,
  `AudioToolUnavailable`.
- Les cibles de fuzzing `peaks` et `ffprobe`, et le banc d'essai de la forme d'onde.
- Le squelette du paquet, sa documentation dans `doc/`, `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
