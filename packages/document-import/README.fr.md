<!-- phil-dev source-sha256=8fda8ad2d72d7886730b6d67939dd59c81a6beab97851d6b6fac94b0bca15299 -->
# phil/document-import

**Des documents de traitement de texte, du PDF, du Markdown et du HTML transformés en documents UJO, avec un rapport de
ce qui n'a pas trouvé sa place, et des documents UJO réécrits en DOCX et en ODT.**

phil/document-import importe les textes que les gens écrivent ailleurs en UJO (Universal JSON Output), le format de
document par blocs de `phil/ujo`. Il lit lui-même les documents Office Open XML (`.docx`, transitionnels ou stricts), les textes OpenDocument (`.odt`, et
`.fodt` plat) et le Rich Text Format (`.rtf`) : paragraphes et titres, gras, italique, souligné, barré, surlignage et
exposants, code en ligne, citations et blocs de code, listes imbriquées avec leur numérotation, tableaux, notes de bas
de page et notes de fin, liens, tables des matières, sauts de page, et images tirées comme médias. Il lit le texte et la
structure simple d'un PDF (titres, paragraphes, listes, liens et images, d'après la taille et la place de ses lignes,
les en-têtes courants étant laissés de côté). Il confie le Markdown et le HTML aux lecteurs de `phil/markup`, et tire
aussi comme médias les images qu'un collage HTML porte dans des adresses `data:`. Une table associe les styles de
paragraphe propres à l'application aux blocs qu'ils deviennent. Les documents Word 97-2003 sont reconnus et refusés.
Chaque image SVG est assainie par `phil/svg-sanitizer` avant d'être stockée : aucun script, aucun gestionnaire
d'événement, aucune référence à un autre fichier. Ce qui n'a pas de place dans un document UJO (commentaires,
suppressions suivies, en-têtes et pieds de page, zones de texte, formes et graphiques, équations, couleurs et polices,
cellules fusionnées, macros) est laissé de côté et compté dans un rapport que l'application peut afficher, ou rendre
avec le document comme dernier bloc. Le chemin du retour écrit un document UJO en fichier DOCX ou ODT, dans les styles
que connaît un traitement de texte, et qui s'importe de nouveau en les mêmes blocs.

Il s'adresse à toute application PHP qui reçoit des textes écrits dans un traitement de texte ou un éditeur : un CMS
qui prend des manuscrits, un outil qui met en page des livres à partir des chapitres de leurs auteurs, un wiki qui
importe d'anciennes pages, un forum qui accepte des documents collés. Un seul appel importe un fichier, dont le format
est donné ou détecté d'après son nom et ses octets, en un document UJO vérifié, ses métadonnées, ses images et son
rapport ; l'application stocke les images où elle le veut. Un seul appel exporte un document, ses images étant données
par l'application.

C'est une simple bibliothèque PHP, sans aucun programme externe : les archives sont lues et écrites avec l'extension
`zip` de PHP, en mémoire là où `ZipArchive::openString()` existe, et leur XML avec son `Dom\XMLDocument`, les flux PDF avec son extension `zlib`, avec des limites sur ce
qu'un document peut contenir une fois décompressé et sur la profondeur de son imbrication. ECMA-376, OpenDocument 1.3,
la spécification RTF 1.9 et ISO 32000 sont ses références ; aucun code d'un autre importateur n'est utilisé. `phil/ujo`
vérifie le document, `phil/markup` lit le Markdown et le HTML, et `phil/svg-sanitizer` assainit les images SVG.

La conception est petite : `DocumentImporter` importe, `Format` nomme et détecte les formats, `Imported` contient le
résultat, un `MediaStore` donne à chaque image son adresse (`MediaFolder` par défaut), un `StyleMap` nomme les styles
de l'application, et `Report` liste chaque `Omission`. `DocumentExporter` exporte, ses images venant d'une
`Contract\MediaSource` (`MediaMap` sur des images en mémoire), vers `Exported`.

## État

| Partie | État |
|---|---|
| Détection du format d'après le nom du fichier et les octets | Implémenté et testé |
| DOCX : paragraphes, titres et intitulés par style ou niveau de plan, citations, code, alignement | Implémenté et testé |
| DOCX : mise en forme des caractères, styles de caractères, hyperliens et champs `HYPERLINK`, tables des matières, sauts de page | Implémenté et testé |
| DOCX : listes à partir de la numérotation, imbriquées ; tableaux avec lignes d'en-tête et fusions ; notes de bas de page et notes de fin | Implémenté et testé |
| DOCX : images, DrawingML et VML, incorporées ou liées ; propriétés principales comme métadonnées | Implémenté et testé |
| ODT : paragraphes, titres, intitulés, citations, code, alignement, sauts de page, mise en forme des caractères par style | Implémenté et testé |
| ODT : liens, listes, tableaux avec lignes d'en-tête, cellules répétées et couvertes, notes, tables des matières, sections | Implémenté et testé |
| ODT : images dans l'archive, incorporées en Base64 ou liées ; métadonnées | Implémenté et testé |
| Markdown et HTML par `phil/markup`, images HTML des adresses `data:` comme médias | Implémenté et testé |
| Images SVG assainies par `phil/svg-sanitizer` avant d'être stockées, ce qui a été retiré étant signalé | Implémenté et testé |
| Rapport de ce qui a été laissé de côté, en lignes, en phrases ou en un bloc rendu avec le document | Implémenté et testé |
| Limites contre les bombes de décompression et d'imbrication ; XML sans réseau ni type de document (XXE) | Implémenté et testé |
| Office Open XML strict, lu par ses espaces de noms transitionnels | Implémenté et testé |
| ODT plat (`.fodt`) par le lecteur ODT | Implémenté et testé |
| RTF : paragraphes, styles, mise en forme des caractères, listes, tableaux, notes, champs, images PNG et JPEG, informations | Implémenté et testé |
| PDF : références croisées et flux d'objets, filtres, polices et CMap, titres, paragraphes, listes, liens, images, en-têtes courants laissés de côté | Implémenté et testé |
| Table des styles : les styles de paragraphe de l'application vers des types de blocs, avant les règles intégrées | Implémenté et testé |
| Images dans une cellule de tableau, un élément de liste ou une note, écrites après leur bloc ; deux images du même nom distinguées | Implémenté et testé |
| Export en DOCX et en ODT, relu en les mêmes blocs, images venant de l'application | Implémenté et testé |
| `.doc` Word 97-2003 et fichiers Office protégés par un mot de passe reconnus et refusés | Implémenté et testé |
| Cibles de fuzzing pour chaque lecteur | Implémenté |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis et installation. |
| [Importer](doc/fr/importing.md) | `DocumentImporter`, les formats et leur détection, `Imported` et les métadonnées, les limites. |
| [Documents de traitement de texte](doc/fr/word-processing.md) | Ce que deviennent DOCX, DOCX strict, ODT, ODT plat et RTF, construction par construction ; les documents Word 97-2003. |
| [PDF](doc/fr/pdf.md) | Ce que devient un PDF : objets, texte, polices, lignes en blocs, images, ce qui est laissé de côté. |
| [Table des styles](doc/fr/styles.md) | `StyleMap` et `StyleRole` : les styles de paragraphe de l'application et les blocs qu'ils deviennent. |
| [Exporter](doc/fr/export.md) | `DocumentExporter` : un document UJO en DOCX ou en ODT, ses images, ce qui est signalé. |
| [Markdown et HTML](doc/fr/markup.md) | Les lecteurs de `phil/markup`, leur choix, et les images d'un collage HTML. |
| [Médias](doc/fr/media.md) | `Media`, `MediaStore`, `MediaFolder`, les images SVG assainies, et le stockage des images. |
| [Le rapport](doc/fr/report.md) | `Report`, `Omission`, `Omitted`, et le rendu du rapport avec le document. |
| [Points d'extension](doc/fr/extension-points.md) | Le magasin et la source de médias, la table des styles, les lecteurs, l'assainisseur SVG et les limites. |
| [Exceptions](doc/fr/exceptions.md) | `DocumentImportException` et ses quatre exceptions. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, le seuil de mutation, et comment tester du code qui importe des documents. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/document-import
```

```php
use Phil\DocumentImport\DocumentExporter;
use Phil\DocumentImport\DocumentImporter;
use Phil\DocumentImport\Format;
use Phil\DocumentImport\MediaFolder;
use Phil\DocumentImport\MediaMap;
use Phil\Ujo\Ujo;

$imported = new DocumentImporter(new MediaFolder('/uploads/manuscript-42/'))->importFile('chapter-one.docx');

foreach ($imported->media as $address => $media) {
    file_put_contents('public'.$address, $media->contents);
}

$json = new Ujo()->encode($imported->document);

foreach ($imported->report->omissions() as $omission) {
    echo $omission->count, ' × ', $omission->what->description(), PHP_EOL;
}

$docx = new DocumentExporter(new MediaMap($imported->media))->export($imported->document, Format::Docx);
file_put_contents('chapter-one-edited.docx', $docx->bytes);
```

## Prérequis

PHP 8.5 ou plus récent, avec les extensions `dom`, `libxml`, `mbstring`, `sodium`, `zip` et `zlib` ; `phil/ujo`,
`phil/markup` et `phil/svg-sanitizer`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
