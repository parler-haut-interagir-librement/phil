<!-- phil-dev source-sha256=14fcd1e91dbf38433ec5e2afe89429eef5f8bd47af8e6ea251b7f74c9b9bce6c -->
# Chapitres

Une `ChapterList` contient des `Chapter` dans l'ordre, et ce qu'un fichier de chapitres de Podcasting 2.0 dit de
lui-même : sa `version`, son `title`, son `author`, son `podcastName`, sa `description`, son `fileName` et ses
`waypoints`.

Un `Chapter` a un `start` en millisecondes, un `title` en texte brut, et ce qu'ajoute Podcasting 2.0 : un `end`, une
`url`, une `image`, un indicateur `toc` — si le chapitre figure dans une table des matières — et un `ChapterLocation`
avec un `name`, une URI `geo:` et un objet OpenStreetMap. Les liens et les images sont des `Phil\Url\Url` : une adresse
`http` ou `https` d'un hôte.

| Méthode | Retourne |
|---|---|
| `ChapterList::at($ms)` | Le chapitre en cours à un instant |
| `ChapterList::tableOfContents()` | Les chapitres dont `toc` est vrai |
| `ChapterList::withEnds($duration)` | Chaque chapitre avec une fin : la sienne, le début du suivant ou la durée donnée |
| `Chapter::shifted($ms)` | Le chapitre déplacé |

## Chapitres JSON de Podcasting 2.0

`Podcast\JsonChapterReader` lit le fichier `application/json+chapters` vers lequel pointe un élément
`<podcast:chapters>`. La `version` est obligatoire ; un `startTime` est un nombre de secondes à partir de 0 ; un
`endTime` vient à son début ou après ; `toc` et `waypoints` sont des booléens ; un lien ou une image qui n'est pas une
adresse `http` ou `https` fait refuser le fichier, pour qu'un lien `javascript:` n'atteigne jamais un lecteur ; les
chapitres viennent dans l'ordre de leurs débuts. Les champs que la spécification ne nomme pas ne sont pas gardés.
`Podcast\JsonChapterWriter` écrit les champs définis, et `toc` seulement quand il est faux.

## Chapitres WebVTT

`WebVtt\WebVttChapterReader` lit un fichier WebVTT dont les repères sont les chapitres, comme l'emploie un
`<track kind="chapters">` : chaque repère donne un début, une fin et, à partir de son texte brut, un titre.
`WebVtt\WebVttChapterWriter` écrit un repère par chapitre ; un repère a besoin d'une fin, de sorte qu'un chapitre sans
fin se termine là où le suivant commence, et le dernier à la durée du média donnée à l'écrivain — sans elle, l'écrivain
lève `Exception\InvalidCue`. Les liens, les images, les lieux et la table des matières n'ont pas de place dans WebVTT
et sont omis.

```php
use Phil\TimedText\ChapterFormat;

$chapters = ChapterFormat::Json->reader()->read($json);
$vtt = ChapterFormat::WebVtt->writer(duration: 3_600_000)->write($chapters);
```
