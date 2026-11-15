<!-- phil-dev source-sha256=694a34d500f91cb7d7acb7d21c2ec8a6d8c0730ce43bfd5300592c030eb7dd52 -->
# Installation

## Prérequis

PHP 8.5 ou une version plus récente, avec `ext-dom` et `ext-libxml` — l'analyseur HTML5 qui lit les transcriptions
HTML — et `ext-mbstring`, qui remplace l'UTF-8 invalide d'un fichier. La bibliothèque exige `phil/url`, qui vérifie les
liens et les images des chapitres.

## Installation

```sh
composer require phil/timed-text
```

## Comme services Symfony

La bibliothèque ne déclare aucun service. Ses lecteurs et écrivains sont immuables et ne gardent aucun état entre les
appels : une seule instance sert toutes les requêtes d'un *worker* de longue durée. Une application déclare ceux
qu'elle utilise, avec ses propres limites :

```php
// config/services.php
use Phil\TimedText\Limits;
use Phil\TimedText\Podcast\JsonTranscriptReader;
use Phil\TimedText\StylePolicy;
use Phil\TimedText\WebVtt\WebVttReader;

$services->set(Limits::class)->args(['$maxBytes' => 4_194_304, '$maxCues' => 20_000]);
$services->set(WebVttReader::class)->args([service(Limits::class), StylePolicy::Drop]);
$services->set(JsonTranscriptReader::class)->args([service(Limits::class)]);
```

`TranscriptFormat::reader()` et `ChapterFormat::reader()` construisent un lecteur pour un type de média quand le format
n'est connu qu'à l'exécution, comme le `type` d'un `<podcast:transcript>`.
