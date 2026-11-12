<!-- phil-dev source-sha256=a6b64993802d6dc540476ca0a17a5c0a6a5619b395b812286692659ec3910433 -->
# Installation

## Prérequis

| Prérequis | Pourquoi |
|---|---|
| PHP 8.5 ou plus récent | Classes en lecture seule, constantes typées, `new` sans parenthèses |
| `ext-ctype` | Distinguer les sous-étiquettes d'une étiquette de langue, lettres ou chiffres |
| `ext-dom` et `ext-libxml` | Le XLIFF, le TMX et le TBX écrits et lus sur le DOM de PHP |
| `ext-mbstring` | Les positions, les longueurs et les distances comptées en points de code |
| `phil/ujo` | Les documents découpés en segments et réassemblés |
| `phil/text-diff` | Ce qui a changé dans la source d'un segment périmé |
| `phil/fonts` | La table des caractères de la police d'un gabarit, pour le contrôle des caractères qu'elle dessine |

## Installer

```sh
composer require phil/translation-memory
```

C'est une bibliothèque, pas un bundle : il n'y a rien à enregistrer. Ses services ne gardent aucun état entre deux
appels. Construisez-les une fois et gardez-les :

```php
use Phil\TranslationMemory\Memory\InMemoryStore;
use Phil\TranslationMemory\Memory\TranslationMemory;
use Phil\TranslationMemory\Segmentation\Granularity;
use Phil\TranslationMemory\Segmentation\UjoSegmenter;

$segmenter = new UjoSegmenter(Granularity::Block);
$memory = new TranslationMemory(new InMemoryStore(), minimum: 0.7, limit: 5);
```

La mémoire a besoin d'un stockage : `InMemoryStore` pour les tests et les petites mémoires chargées d'un fichier, ou
une implémentation de `MemoryStore` sur la base de données de l'application. Dans une application Symfony,
`phil/translation-bundle` fournit un stockage Doctrine, conserve les segments et branche le tout en services.
