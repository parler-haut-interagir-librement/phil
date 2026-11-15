<!-- phil-dev source-sha256=532786b76ae52e6ff4c26e1b19277efbaf9369610f9ffc34352cb1e758eaa0b9 -->
# phil/timed-text

**Lisez et écrivez des sous-titres, des chapitres et des transcriptions dans chaque format qu'exige un lecteur ou une
application de balado, par un seul modèle.**

phil/timed-text lit WebVTT, SubRip (SRT), les transcriptions JSON de Podcasting 2.0 et les transcriptions HTML dans un
seul modèle neutre, un `Track` de `Cue`, et le réécrit dans n'importe lequel d'entre eux ; il lit et écrit les
chapitres en chapitres JSON de Podcasting 2.0 et en chapitres WebVTT. Un fichier WebVTT lu puis réécrit garde son
en-tête, ses régions, ses styles, ses notes, ses identifiants, ses réglages et le balisage de ses repères. Les instants
sont des millisecondes entières : décaler, fusionner et scinder une piste de plusieurs heures n'ajoute aucun arrondi.

Le paquet s'adresse à toute application PHP qui publie de l'audio ou de la vidéo : les sous-titres d'une vidéo, la
transcription et les chapitres d'un épisode, la sortie d'un service de reconnaissance vocale transformée en WebVTT,
SRT, JSON et page HTML.

C'est une bibliothèque PHP simple dont les prérequis sont `ext-dom`, `ext-libxml`, `ext-mbstring` et `phil/url`.
Chaque lecteur est borné — octets, repères, texte par repère, problèmes listés — et chaque écrivain de HTML échappe par
construction.

La conception : `Track`, `Cue`, `CueSettings`, `Region` et `Note` forment le modèle, `CueText` lit et écrit le texte
d'un repère, `Timecode` lit et écrit les instants ; les lecteurs et écrivains de `WebVtt\`, `Srt\`, `Podcast\` et
`Html\` suivent `Contract\TrackReader` et `Contract\TrackWriter`, ou `Contract\ChapterReader` et
`Contract\ChapterWriter` ; `TranscriptFormat` et `ChapterFormat` les choisissent par type de média.

## État

| Partie | État |
|---|---|
| WebVTT : en-tête, repères avec identifiants, réglages et balisage, régions, `STYLE` gardé ou écarté, commentaires `NOTE` | Implémenté et testé |
| SubRip (SRT) : repères numérotés, variantes des éditeurs, `<b>`, `<i>` et `<u>` | Implémenté et testé |
| Transcriptions JSON de Podcasting 2.0, avec intervenants | Implémenté et testé |
| Transcriptions HTML : écrites échappées par construction, lues avec l'analyseur HTML5 de PHP | Implémenté et testé |
| Chapitres : chapitres JSON de Podcasting 2.0, chapitres WebVTT | Implémenté et testé |
| Lecture stricte avec numéros de ligne, inspection indulgente qui liste chaque problème | Implémenté et testé |
| Décalage, fusion et scission de pistes et de repères, en millisecondes entières | Implémenté et testé |
| Texte de repère en texte brut, en texte SRT ou en HTML échappé ; voix et intervenants | Implémenté et testé |
| TTML, SCC, SBV et autres formats de sous-titres | Pas commencé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, les lecteurs et écrivains comme services. |
| [Le modèle](doc/fr/model.md) | Pistes, repères, texte de repère, notes, régions et styles, et modification d'une piste. |
| [Formats](doc/fr/formats.md) | Ce que porte chaque format, ce que garde un aller-retour et ce qui n'est pas pris en charge. |
| [Lecture](doc/fr/reading.md) | Lecture stricte, inspection indulgente, problèmes et leurs lignes, les limites. |
| [Chapitres](doc/fr/chapters.md) | Les chapitres JSON de Podcasting 2.0 et les chapitres WebVTT. |
| [Sécurité](doc/fr/security.md) | Les fichiers hostiles, les limites et le HTML échappé par construction. |
| [Points d'extension](doc/fr/extension-points.md) | Les contrats qu'une application implémente ou remplace. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, ses cibles de fuzzing et ses bancs d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/timed-text
```

```php
use Phil\TimedText\TranscriptFormat;
use Phil\TimedText\WebVtt\WebVttReader;

$track = new WebVttReader()->read(file_get_contents('episode.vtt'));

// The same cues one second later, as SRT, as a Podcasting 2.0 transcript and as HTML.
$later = $track->shifted(1_000);
$srt = TranscriptFormat::Srt->writer()->write($later);
$json = TranscriptFormat::Json->writer()->write($later);
$html = TranscriptFormat::Html->writer()->write($later);
```

## Prérequis

PHP 8.5 ou une version plus récente, avec `ext-dom`, `ext-libxml` et `ext-mbstring`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
