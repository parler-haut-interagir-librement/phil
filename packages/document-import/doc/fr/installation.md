<!-- phil-dev source-sha256=a487e2ce660f7dbb54813a34171676beea1b699359a6a769167597cd67a94c7e -->
# Installation

## Prérequis

| Prérequis | Pourquoi |
|---|---|
| PHP 8.5 ou plus récent | Classes en lecture seule, constantes typées, `new` sans parenthèses, `Dom\XMLDocument`, `array_last()` |
| `ext-dom` | Le XML des documents DOCX, ODT et ODT plat, et l'analyseur HTML5 pour les images d'un collage HTML et le HTML en ligne d'un export |
| `ext-libxml` | Les options de l'analyseur : aucun accès réseau, aucune sortie d'erreur |
| `ext-mbstring` | Les pages de codes de RTF et de PDF, les chaînes UTF-16, les textes de remplacement coupés à leur longueur |
| `ext-sodium` | Le Base64 à temps constant des images incorporées dans un ODT et dans les adresses `data:`, et l'hexadécimal des images RTF et des chaînes PDF |
| `ext-zip` | Les archives DOCX et ODT, lues et écrites |
| `ext-zlib` | Les flux Flate d'un PDF, et les fichiers PNG écrits à partir de ses images |
| `phil/ujo` | Le document UJO, vérifié et mis sous forme canonique |
| `phil/markup` | Les lecteurs de Markdown et de HTML |
| `phil/svg-sanitizer` | Les images SVG assainies avant d'être stockées |

La bibliothèque n'appelle aucune suite bureautique, aucun binaire et aucun service.

## Installer

```sh
composer require phil/document-import
```

C'est une bibliothèque, pas un bundle : il n'y a rien à enregistrer. Créez un `DocumentImporter` là où les fichiers
sont reçus :

```php
use Phil\DocumentImport\DocumentImporter;

$imported = new DocumentImporter()->importFile($upload->getPathname());
```

Un `DocumentImporter` ne garde aucun état : une seule instance importe tous les fichiers, et un `DocumentExporter`
exporte tous les documents. Une archive (DOCX, ODT) est ouverte et écrite en mémoire sur un PHP avec
`ZipArchive::openString()` et `ZipArchive::closeString()` ; sur un PHP sans elles, elle passe par un fichier temporaire
du répertoire temporaire du système pendant sa lecture ou son écriture, et ce fichier est supprimé avant qu'`import()`
ou `export()` retourne ou lève une exception.
