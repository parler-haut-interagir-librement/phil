<!-- phil-dev source-sha256=9ecd536a05c6b2c4ea6a9b20542d8cc465e47f93fa38a87351ce170d713c52cd -->
# Formats

| Format | Type de média | Lecteur | Écrivain |
|---|---|---|---|
| WebVTT | `text/vtt` | `WebVtt\WebVttReader` | `WebVtt\WebVttWriter` |
| SubRip | `application/srt`, aussi `application/x-subrip` et `text/srt` en lecture | `Srt\SrtReader` | `Srt\SrtWriter` |
| Transcription JSON de Podcasting 2.0 | `application/json` | `Podcast\JsonTranscriptReader` | `Podcast\JsonTranscriptWriter` |
| Transcription HTML | `text/html` | `Html\HtmlTranscriptReader` | `Html\HtmlTranscriptWriter` |
| Chapitres JSON de Podcasting 2.0 | `application/json+chapters` | `Podcast\JsonChapterReader` | `Podcast\JsonChapterWriter` |
| Chapitres WebVTT | `text/vtt` | `WebVtt\WebVttChapterReader` | `WebVtt\WebVttChapterWriter` |

`TranscriptFormat::fromMediaType()` et `ChapterFormat::fromMediaType()` trouvent le format d'un type de média, sans
tenir compte de ses paramètres ni de la casse ; `reader()` et `writer()` construisent son lecteur et son écrivain.

## WebVTT

Lu comme le décrit la spécification WebVTT du W3C : la marque d'ordre des octets facultative, `WEBVTT` et le texte de
l'en-tête, les lignes de métadonnées qui suivent ignorées, puis des blocs séparés par des lignes vides — `REGION` et
`STYLE` avant le premier repère, `NOTE` n'importe où, et des repères avec un identifiant facultatif, `start --> end`,
des réglages et du texte. Une ligne avec `-->` commence le repère suivant même sans ligne vide. Les lignes peuvent se
terminer par CRLF, CR ou LF.

Un fichier lu puis réécrit garde tout ce qu'emploie un lecteur : en-tête, régions, styles, notes à leur place,
identifiants de repères, minutages, réglages et balisage. L'écrivain écrit toujours les heures d'une estampille
temporelle, chaque réglage de région sur sa propre ligne, `NOTE texte` sur une seule ligne avec le texte, et des fins
de ligne LF : un fichier écrit à la main revient sous cette forme, avec le même sens.

## SubRip (SRT)

Des repères numérotés avec des minutages `hh:mm:ss,mmm --> hh:mm:ss,mmm` et leur texte. Le lecteur accepte ce
qu'écrivent les éditeurs : un point ou moins de chiffres dans une estampille, des coordonnées après les minutages, une
ligne vide manquante avant le numéro et les minutages suivants. `<b>`, `<i>` et `<u>` deviennent du balisage de repère ;
`<font>`, les autres balises et les remplacements `{\an8}` sont écartés. Les numéros ne sont pas gardés : l'écrivain
numérote les repères à partir de 1.

L'écrivain omet ce pour quoi SRT n'a pas de place — identifiants, réglages, régions, styles, notes, voix — et n'écrit
que `<b>`, `<i>` et `<u>`. Un fichier SRT lu puis réécrit est identique, numéroté à partir de 1, sans ses balises
`<font>`.

## Transcriptions JSON de Podcasting 2.0

Les `segments` du format de transcription de Podcasting 2.0, chacun avec `startTime` et `endTime` en secondes, un `body`
et un `speaker` facultatif. Un segment devient un repère dont le texte est le corps échappé, ouvert par la voix de
l'intervenant, de sorte qu'un fichier WebVTT qui en est écrit dit qui parle. L'écrivain écrit la version `1.0.0`,
l'intervenant à partir de la voix qui ouvre chaque repère, le corps en texte brut. Une transcription lue puis réécrite
est identique, ses temps arrondis à la milliseconde.

## Transcriptions HTML

La transcription HTML que décrit Podcasting 2.0 : l'intervenant dans un `<cite>`, le début dans un `<time>`, le texte
dans un `<p>`. L'écrivain écrit un fragment pour la page de l'application — un `<cite>` quand l'intervenant change, un
`<time>` dont le `datetime` est une durée ISO 8601, le texte brut dans un `<p>` avec des `<br>` à ses sauts de ligne.
Le lecteur accepte tout HTML de cette forme, par l'analyseur HTML5 de PHP : un paragraphe commence au `<time>` qui le
surmonte — son `datetime`, sinon son texte lu comme une horloge — et se termine là où commence le paragraphe suivant ;
le dernier se termine là où il commence. Seuls le texte et ses sauts de ligne sont gardés.

## Non pris en charge

- TTML et ses profils (IMSC, EBU-TT, SMPTE-TT), SCC, SBV, LRC et les formats de sous-titres des logiciels de montage
  vidéo.
- Les lignes d'en-tête `Region:` de WebVTT des ébauches antérieures au bloc `REGION` ; elles sont ignorées avec les
  autres métadonnées.
- L'interprétation du CSS : un bloc `STYLE` est gardé comme texte ou écarté, jamais appliqué.
- Les champs des fichiers JSON de Podcasting 2.0 que la spécification ne nomme pas : ils ne sont pas gardés.
- L'écriture d'une transcription en texte brut : `CueText::plain()` donne le texte de chaque repère à l'application qui
  en a besoin.
