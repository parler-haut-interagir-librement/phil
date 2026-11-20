<!-- phil-dev source-sha256=15cfc4a61b2f60a639ff9a4d4ae2db56a741e1b29354f3a148823cbd5b94d9bb -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente.
- Les extensions `dom`, `libxml` et `xmlreader`, qui comptent et analysent les manifestes.
- L'extension `mbstring`, qui borne les textes et vérifie que les chemins sont en UTF-8.
- L'extension `zip`, qui lit les archives.
- `phil/url`, qui lit l'adresse de lancement absolue d'une activité xAPI, et les extensions `intl` et `uri` dont il a
  besoin.

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

Les deux sont immuables et ne gardent aucun état entre deux lectures : ils sont sûrs dans un *worker* de longue durée. Il
en va de même de `SequencingReader` et de `Sequencer`, qu'une plateforme qui exécute le séquencement SCORM enregistre de
la même façon.
