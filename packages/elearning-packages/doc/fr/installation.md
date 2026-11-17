<!-- phil-dev source-sha256=813f4b7efa0d68d7d6383d6bd0a0f67e31880c71990bf0dc5c21f079b61999ca -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente.
- Les extensions `dom` et `libxml`, qui analysent les manifestes.
- L'extension `mbstring`, qui borne les textes et vérifie que les chemins sont en UTF-8.
- L'extension `zip`, qui lit les archives.

La bibliothèque ne dépend d'aucun autre paquet. Elle n'écrit jamais sur le disque : un paquet est lu là où il se trouve,
et ses fichiers sont remis à l'appelant sous forme de chaînes.

## Installer

```sh
composer require phil/elearning-packages
```

## Une première lecture

```php
use Phil\ElearningPackages\PackageReader;

$package = new PackageReader()->readFile('/path/to/course.zip');
```

`readFile()` ouvre l'archive, lit le manifeste à sa racine, vérifie les fichiers qu'il nomme, puis ferme l'archive. Elle
lève `PackageRefused` quand le fichier n'est pas un paquet qu'elle peut lire en toute sécurité ; voir
[Exceptions](exceptions.md).

Les limites par défaut conviennent à un paquet de cours d'au plus 512 Mio ; [Limites et sécurité](limits-and-security.md)
explique chacune et comment les resserrer, pour un formulaire de téléversement par exemple :

```php
use Phil\ElearningPackages\PackageLimits;
use Phil\ElearningPackages\PackageReader;

$reader = new PackageReader(new PackageLimits(archiveBytes: 50 * 1024 * 1024, entries: 2_000));
```

## Dans une application Symfony

La bibliothèque n'a besoin d'aucun bundle. Enregistrez `PackageReader` comme service, avec les limites de l'application :

```yaml
# config/services.yaml
services:
    Phil\ElearningPackages\PackageLimits:
        arguments:
            $archiveBytes: 104857600
    Phil\ElearningPackages\PackageReader:
        arguments:
            $limits: '@Phil\ElearningPackages\PackageLimits'
            $language: 'fr'
```

Les deux sont immuables et ne gardent aucun état entre deux lectures : ils sont sûrs dans un *worker* de longue durée.
