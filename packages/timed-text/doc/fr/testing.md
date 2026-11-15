<!-- phil-dev source-sha256=e3df7ec0494f4411950cf09dd6b15166f813e76b0aa815e979d73b8e457386b5 -->
# Tests

## Les tests de la bibliothèque

La suite `tests/Unit` lit des fichiers écrits pour elle dans `tests/Fixtures` — un fichier WebVTT avec un en-tête, une
région, un style, des notes, des identifiants, des réglages et des voix, un fichier SRT avec des balises `<font>` et en
majuscules, un fichier de transcription et de chapitres de Podcasting 2.0, une transcription HTML — et teste chaque
lecture et écriture, chaque aller-retour, chaque problème qu'une inspection signale avec sa ligne, chaque limite, les
estampilles temporelles de chaque format, la modification des pistes et des repères, et l'échappement du HTML.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/timed-text/tests
```

Infection maintient la bibliothèque à 100 % de score de mutation couvert.

## Le fuzzing

Chaque analyseur a une cible de fuzzing, alimentée par les germes de `tests/Fuzz/corpus/<target>` : quoi qu'il lise, il
retourne ce qu'il a lu, qui est réécrit, ou lève une exception de la bibliothèque.

| Cible | Ce qu'elle soumet au fuzzing |
|---|---|
| `webvtt` | `WebVttReader::inspect()`, puis `WebVttWriter` |
| `srt` | `SrtReader::inspect()`, puis `SrtWriter` |
| `json-transcript` | `JsonTranscriptReader::inspect()`, puis `JsonTranscriptWriter` |
| `json-chapters` | `JsonChapterReader::read()`, puis `JsonChapterWriter` |
| `html-transcript` | `HtmlTranscriptReader::inspect()`, puis `HtmlTranscriptWriter` |
| `cue-text` | Chaque méthode de `CueText` ; le HTML ne doit porter aucune balise qu'il n'a pas construite |

```sh
composer fuzz -- webvtt srt json-transcript json-chapters html-transcript cue-text
```

## Les bancs d'essai

`tests/Benchmark/TimedTextBench.php` lit et écrit une transcription de trois heures — 3 600 repères avec deux
intervenants — dans chaque format, puis la décale et la scinde. Chaque lecture et chaque écriture prend quelques
dizaines de millisecondes :

```sh
composer bench -- packages/timed-text/tests/Benchmark
```

## Tester le code qui emploie la bibliothèque

Les lecteurs et les écrivains sont déterministes et n'ont besoin d'aucun serveur : gardez des fichiers d'exemple à côté
des tests, lisez-les et comparez les repères, ou écrivez une piste et comparez le texte.

```php
$track = new WebVttReader()->read((string) file_get_contents(__DIR__.'/captions/episode.vtt'));

self::assertSame('Alice', $track->cues[0]->speaker());
self::assertSame("1\n00:00:01,000 --> 00:00:04,500\nWelcome.\n", new SrtWriter()->write($track->withCues([$track->cues[0]])));
```
