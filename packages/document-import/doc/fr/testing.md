<!-- phil-dev source-sha256=857aaea2389e3f90465dc8243cb1a6ca5d81b8adf2e680e8dda6c45ae67107ae -->
# Tests

## Les tests de la bibliothèque

La suite `tests/Unit` écrit en mémoire de petites archives DOCX et ODT, des fichiers ODT plats, RTF et PDF
(`tests/Unit/Fixture`) et vérifie les blocs UJO, les médias et le rapport de chaque construction : mise en forme et
styles, titres et leurs niveaux, listes et leur imbrication, tableaux et leurs fusions, notes, liens et champs, images
sous toutes leurs formes, images SVG assainies, ce qui est signalé, les métadonnées, les limites, les archives ouvertes et écrites en mémoire ou par un fichier temporaire (les deux voies
sur un PHP qui a `ZipArchive::openString()`), et chaque refus avec son message. Les parties du PDF (analyseur, filtres et leurs
prédicteurs, CMap, noms de glyphes,
pages de codes) ont leurs propres tests, et chaque bloc qu'écrit un export est relu par l'importateur. Le Markdown et
le HTML passent par les lecteurs de `phil/markup`.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/document-import/tests
```

La bibliothèque est tenue par Infection à un score de mutation couverte de 100 %.

Chaque lecteur est aussi une cible de fuzzing (`docx`, `odt`, `fodt`, `rtf`, `pdf`) qui s'exécute sur des fichiers que
personne n'a écrits, à partir des germes de `tests/Fuzz/corpus/<target>`, et vérifie que le lecteur donne un document
ou refuse le fichier par une exception de la bibliothèque, rien d'autre :

```sh
composer fuzz -- docx odt fodt rtf pdf --budget 60
```

Les lecteurs ont aussi été exécutés sur des documents écrits par de vraies applications : des DOCX et des ODT écrits
par Pandoc à partir de Markdown, et par `textutil` de macOS à partir de HTML, relus avec la structure qui avait servi à
les écrire.

## Tester du code qui importe des documents

Construisez le document dans le test, ou gardez parmi les jeux de données d'essai du test de petits fichiers écrits par
le traitement de texte qu'attend l'application, et vérifiez les blocs plutôt que le HTML :

```php
use Phil\DocumentImport\DocumentImporter;
use Phil\Ujo\Block;

$imported = new DocumentImporter()->importFile(__DIR__.'/fixtures/manuscript.docx');

self::assertSame(['header', 'paragraph', 'list'], array_map(static fn (Block $block): string => $block->type, $imported->document->blocks));
self::assertTrue($imported->report->isEmpty());
```

Un `MediaStore` écrit pour le test enregistre les images au lieu de les écrire.
