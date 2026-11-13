<!-- phil-dev source-sha256=4261d936e5a7d0358a58d76d2630041e0631eefe3a601f71989bb4aceb9275d3 -->
# Installation

## Prérequis

| Prérequis | Pourquoi |
|---|---|
| PHP 8.5 ou plus récent | Classes en lecture seule, constantes typées, `array_any()`, `new` sans parenthèses, `Dom\HTMLDocument` |
| `ext-dom` | L'analyseur HTML5 qui lit chaque chapitre avant qu'il soit écrit en XHTML |
| `ext-zip` | Le conteneur ZIP |
| `phil/ujo` (facultatif) | Les documents UJO transformés en chapitres, par `Ujo\UjoChapters` |

La bibliothèque n'a aucune autre dépendance : aucun binaire, aucun service. EPUBCheck, qui valide la sortie, n'est
nécessaire que pour lancer les tests de validation.

## Installer

```sh
composer require phil/epub
composer require phil/ujo   # only to write UJO documents
```

C'est une bibliothèque, pas un bundle : il n'y a rien à enregistrer. Construisez un `Book` là où un livre est exporté,
et écrivez-le :

```php
use Phil\Epub\EpubWriter;

new EpubWriter()->write($book, '/path/to/book.epub');
$bytes = new EpubWriter()->toString($book); // for a download response
```

Un `EpubWriter` ne garde aucun état : une seule instance peut écrire tous les livres. `toString()` écrit par un fichier
temporaire, dans le répertoire donné au constructeur ou dans le répertoire temporaire du système, puis le supprime.
