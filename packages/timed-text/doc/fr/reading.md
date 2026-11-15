<!-- phil-dev source-sha256=4fa62aabfe7b4cdbdbebde9cb0c44046cd492810ce616eb23e610561715aade2 -->
# Lecture

## Stricte et indulgente

Chaque lecteur de piste a deux méthodes :

- `read()` lit un document qui suit son format et lève `Exception\InvalidTimedText` à son premier problème, avec la
  ligne dans `documentLine` quand le format a des lignes ;
- `inspect()` lit ce qui peut l'être, enjambe les blocs qu'il ne peut pas lire et retourne une `Inspection` : la
  `track` des blocs lisibles et les `issues`, chacune une `Issue` avec sa `line` et son `message`. `isValid()` dit s'il
  n'y en a aucune.

```php
use Phil\TimedText\WebVtt\WebVttReader;

$inspection = new WebVttReader()->inspect($upload);

foreach ($inspection->issues as $issue) {
    echo $issue->toString(), "\n"; // Line 27: "align:middle" is not a cue setting; it is ignored.
}

$track = $inspection->track;
```

Employez `read()` sur les fichiers que l'application a produits, et `inspect()` sur les fichiers que des gens
téléversent, pour leur montrer ce qui a été mis de côté. `inspect()` lève tout de même une exception quand le document
n'est pas du tout du format — un fichier WebVTT qui ne s'ouvre pas par `WEBVTT`, un document JSON qui n'est pas un objet
avec sa liste — et quand il dépasse ce que permettent les limites.

## Ce qui compte comme un problème

| Format | Problèmes |
|---|---|
| WebVTT | Un bloc qui n'est ni un repère, ni une note, ni un style, ni une région ; des minutages qui ne sont pas `start --> end` ; une estampille qui n'en est pas une ; un repère qui se termine avant de commencer ou commence avant le repère qui le précède ; un réglage inconnu ; une région sans identifiant ou définie deux fois ; un bloc `STYLE` ou `REGION` après le premier repère ou contenant `-->` ; une note coupée par une ligne avec `-->` ; un en-tête contenant `-->` |
| SRT | Un bloc sans minutages ; un repère sans son numéro ; des minutages ou des estampilles illisibles ; un repère qui se termine avant de commencer ou commence avant le repère qui le précède |
| Transcription JSON | Un segment qui n'est pas un objet ; un `startTime` ou un `endTime` qui n'est pas un nombre de secondes à partir de 0 ; un segment qui se termine avant de commencer ou commence avant celui qui le précède ; un `body` ou un `speaker` qui n'est pas une chaîne |
| Transcription HTML | Un `<time>` qui n'est ni une durée ni une horloge, ou qui vient avant le temps qui le précède |

Le JSON n'a pas de lignes : ses problèmes nomment le segment, compté à partir de 1. Les problèmes du HTML nomment le
`<time>`. Les lecteurs de chapitres sont stricts seulement, et leurs problèmes nomment le chapitre.

## Les limites

`Limits` borne le travail sur un document, quel que soit son contenu : `maxBytes` (16 Mio par défaut), `maxCues`
(100 000 repères, segments, paragraphes ou chapitres), `maxTextBytes` (64 Kio pour le texte d'un repère, d'un titre,
d'une note ou d'un style) et `maxIssues` (1 000 problèmes listés). Au-delà de l'une d'elles, le lecteur lève
`Exception\LimitExceeded`, dans `read()` comme dans `inspect()`. Voir [Sécurité](security.md).
