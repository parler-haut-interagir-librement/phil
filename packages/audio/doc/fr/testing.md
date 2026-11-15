<!-- phil-dev source-sha256=5b8d6c25805b78bd587cb7a60e6fe9fb2c2609eeb9a2f95abdd1e604b1fade71 -->
# Tests

## Les tests de la bibliothèque

```sh
vendor/bin/phpunit packages/audio/tests
```

ffmpeg et ffprobe ne s'exécutent jamais dans la suite unitaire : `FfmpegAudio` reçoit un `CommandRunner` scénarisé, qui
vérifie chaque commande argument par argument et écrit les fichiers que ffmpeg écrirait. `ProcessCommandRunner` est testé
sur PHP lui-même, le seul programme que possède chaque machine de test : arguments passés tels quels, sortie au-delà de
sa borne arrêtée, exécution au-delà de son délai arrêtée, programme qui ne peut pas démarrer. `PeaksComputer` reçoit des
échantillons dans des chaînes et dans un flux qui donne trois octets à la fois, de sorte qu'un échantillon coupé entre
deux lectures est rejoint.

La bibliothèque est tenue à 100 % de score de mutation couvert par Infection.

## Fuzzing

| Cible | Ce qu'elle soumet au fuzzing |
|---|---|
| `peaks` | `PeaksReader::read()`, puis `Peaks::amplitudes()` |
| `ffprobe` | `Ffmpeg\FfprobeReader::read()` |

```sh
composer fuzz -- peaks ffprobe
```

## Bancs d'essai

`tests/Benchmark/PeaksBench.php` calcule les crêtes de dix minutes d'échantillons à 8 kHz — 4,8 millions d'échantillons —
en environ 0,7 s, et lit les crêtes d'une heure et les ramène à 200 barres en environ 20 ms.

## Avec un vrai ffmpeg

La suite n'a pas besoin de ffmpeg. Pour vérifier une construction de ffmpeg contre les commandes, exécutez `FfmpegAudio`
sur un fichier à vous :

```php
$ffmpeg = new Phil\Audio\Ffmpeg\FfmpegAudio();
$ffmpeg->transcode('sample.wav', 'sample.opus', new Phil\Audio\Rendition(Phil\Audio\AudioFormat::Opus));
var_dump($ffmpeg->read('sample.opus'), $ffmpeg->peaks('sample.opus')->length());
```
