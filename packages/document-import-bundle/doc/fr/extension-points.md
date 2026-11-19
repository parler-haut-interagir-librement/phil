<!-- phil-dev source-sha256=67f88cbc2b7d6d08d6da44d53accfbeb68a8b5a60bd2769303ad1cf2705f0a02 -->
# Points d'extension

Ce qu'une application implémente, remplace ou utilise.

## Services

| Service | Comment une application l'étend |
|---|---|
| `Phil\DocumentImport\MediaStore` | Un alias vers un service propre à l'application pour conserver les images ailleurs : voir [Médias](media.md) |
| `phil_document_import.media_address` | Remplacé par une fermeture propre à l'application, d'un média vers l'adresse que montre un bloc d'image |
| `Phil\DocumentImport\Contract\MediaSource` | Implémenté par l'application et nommé dans `export.media_source`, pour que l'exportateur lise les octets derrière chaque adresse d'image |
| `Phil\SvgSanitizer\SvgSanitizer` | Demandé partout où l'application assainit du SVG avec la même politique |
| `Phil\DocumentImportBundle\Application\DocumentImports` | Demandé par les contrôleurs et les gestionnaires qui importent et exportent les formats acceptés |
| `Phil\DocumentImport\DocumentImporter`, `Phil\DocumentImport\DocumentExporter` | Demandés par le code qui a besoin de tous les formats de la bibliothèque |
| `Phil\DocumentImportBundle\Application\Contract\DocumentScreening` | Implémenté par l'application, et l'interface dont l'alias pointe vers son service avec `scan: off`, pour une vérification propre avant la lecture de chaque fichier : voir [Importer](importing.md) |
| `Phil\DocumentImportBundle\Application\Contract\PendingImports` | Implémenté par l'application et nommé dans `async.store`, pour garder ailleurs les fichiers qui attendent le *worker* : voir [Importations sur un worker](worker.md) |
| `Phil\DocumentImportBundle\Application\DocumentImportQueue` | Demandé par les contrôleurs qui importent sur un *worker* |

## Événements

| Événement | Quand |
|---|---|
| `Phil\DocumentImportBundle\Domain\Event\DocumentImported` | Après chaque importation de `DocumentImports`, lorsque le conteneur a un service `event_dispatcher` ; `$claim` est défini pour une importation sur un *worker* |
| `Phil\DocumentImportBundle\Domain\Event\DocumentImportFailed` | Après qu'un fichier importé sur un *worker* est refusé par la bibliothèque ou l'analyse préalable |

## Messages

| Message | Porte |
|---|---|
| `Phil\DocumentImportBundle\Application\Message\ImportDocument` | `$claim`, la référence du fichier dans `PendingImports`, et rien d'autre |

## Gabarits

| Gabarit | Variables |
|---|---|
| `@PhilDocumentImport/report.html.twig` | `report`, obligatoire ; `title`, `level` et `id`, facultatives ; redéfini dans `templates/bundles/PhilDocumentImportBundle/report.html.twig` |

## Configuration

| Option | Ce qu'elle étend |
|---|---|
| `media.*` | L'endroit où vont les images |
| `formats.*` | Les formats lus et écrits |
| `limits.*` | Ce qu'un document peut contenir, sa profondeur d'imbrication, le nombre d'objets qu'un PDF peut déclarer |
| `styles` | Le bloc que devient chaque style de paragraphe |
| `report.title` | Le titre du bloc et du fragment du rapport |
| `scan` | Si chaque fichier est analysé avant d'être lu |
| `async.*` | Les importations sur un *worker* : le transport, l'endroit où attendent les fichiers |
| `export.*` | L'endroit où l'exportateur lit les images et écrit les archives |
| `svg.*` | Ce qu'une image SVG conserve |
