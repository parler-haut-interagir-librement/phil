<!-- phil-dev source-sha256=7f88cc79d5d8d63bdea4c0a36ff68311644ef00077f3337e68c19bb7daef35a4 -->
# Importer

## L'importateur

```php
use Phil\DocumentImport\DocumentImporter;
use Phil\DocumentImport\Format;
use Phil\DocumentImport\Limits;
use Phil\DocumentImport\MediaFolder;
use Phil\DocumentImport\StyleMap;
use Phil\SvgSanitizer\SvgSanitizer;

$importer = new DocumentImporter(
    media: new MediaFolder('media/'),   // where image blocks point
    markdown: null,                     // a phil/markup reader, MarkdownReader by default
    html: null,                         // a phil/markup reader, HtmlReader by default
    normalizer: null,                   // the phil/ujo normalizer of DOCX and ODT documents
    limits: new Limits(),
    svg: new SvgSanitizer(),            // the phil/svg-sanitizer sanitizer of SVG images
    styles: new StyleMap(),             // the application's paragraph styles and their blocks
);

$imported = $importer->importFile('/tmp/upload.docx');
$imported = $importer->import($bytes, filename: 'notes.md');
$imported = $importer->import($bytes, Format::Odt);
```

`import()` prend le contenu d'un fichier ; `importFile()` lit un chemin et passe son nom de base. Le format est celui
qui est donné ; sinon, celui qu'annonce le nom ; sinon, celui que montrent les octets. La [table des
styles](styles.md) dit à l'importateur ce que deviennent les styles de paragraphe propres à l'application.

## Formats

| `Format` | Extensions | Détecté d'après les octets | Lu par |
|---|---|---|---|
| `Docx` | `.docx`, `.docm`, `.dotx`, `.dotm` | Une archive ZIP qui n'est pas un texte OpenDocument | Cette bibliothèque |
| `Odt` | `.odt`, `.ott` | Une archive ZIP dont la première entrée est le `mimetype` d'un texte OpenDocument, stocké sans compression | Cette bibliothèque |
| `Fodt` | `.fodt` | Un `office:document` XML dont l'`office:mimetype` est celui d'un texte, dans ses 4 premiers Kio | Cette bibliothèque |
| `Rtf` | `.rtf` | `{\rtf`, après une marque d'ordre des octets et des blancs | Cette bibliothèque |
| `Pdf` | `.pdf` | `%PDF-` | Cette bibliothèque, voir [PDF](pdf.md) |
| `Markdown` | `.md`, `.markdown`, `.mdown`, `.mkd` | Jamais : le Markdown est n'importe quel texte | `phil/markup` |
| `Html` | `.html`, `.htm`, `.xhtml` | Un texte qui commence par `<`, après une marque d'ordre des octets et des blancs | `phil/markup` |

`Format::fromFilename()` donne le format d'un nom, ou `null` ; `Format::detect()` donne le format d'un fichier ou lève
`UnsupportedFormat`. Les macros d'un `.docm` ne sont jamais exécutées : elles sont signalées. Un document Word 97-2003
(nommé `.doc` ou `.dot`, ou un fichier composé quel que soit son nom) et un document Office protégé par un mot de passe
sont refusés par `UnsupportedFormat`, même quand un format est donné : voir
[Documents de traitement de texte](word-processing.md).

## Le résultat

| Propriété d'`Imported` | Contenu |
|---|---|
| `format` | Le `Format` lu |
| `document` | Le `Phil\Ujo\Document`, vérifié et sous forme canonique ; blocs numérotés `b1`, `b2`… dans l'ordre de lecture |
| `metadata` | Les métadonnées du fichier (ci-dessous) |
| `media` | Les images tirées du fichier, des `Media` indexés par l'adresse qu'affichent leurs blocs d'image |
| `report` | Le `Report` de ce qui a été laissé de côté |

`Imported::documentWithReport()` donne le document avec le rapport comme dernier bloc `warning`, pour qu'un lecteur voie
ce qui a été laissé de côté avec le texte : voir [Le rapport](report.md).

Les métadonnées d'un DOCX sont ses propriétés principales, celles d'un ODT son `meta.xml` (ou l'`office:meta` d'un ODT
plat), celles d'un RTF son `\info`, celles d'un PDF ses informations de document et la langue de son catalogue, sous
les mêmes clés quand elles sont présentes : `title`, `subject`, `creator` (l'auteur initial d'un ODT, pas son dernier
éditeur), `keywords` (joints par des virgules), `description`, `language`, `created`, `modified`, les dates telles que
le fichier les écrit. Un fichier Markdown donne son en-tête de métadonnées (*front matter*), une page HTML son titre et
ses `<meta>` nommés.

## Limites

`Limits` borne ce qu'un document peut contenir, et refuse par `LimitExceeded` ce qui le dépasse :

| Limite | Valeur par défaut | Borne |
|---|---|---|
| `entryBytes` | 64 Mio | Une entrée d'une archive une fois décompressée ; un ODT plat ; un flux PDF une fois décodé |
| `totalBytes` | 256 Mio | Une archive une fois décompressée ; un fichier RTF, PDF, Markdown ou HTML ; tous les flux d'un PDF une fois décodés |
| `depth` | 100 | L'imbrication des groupes RTF, des tableaux et dictionnaires PDF et de l'arbre des pages |
| `objects` | 500 000 | Les objets qu'un PDF déclare |
| `operations` | 1 000 000 | Le travail de lecture des pages d'un PDF : chaque opérateur de contenu, ceux d'un formulaire chaque fois qu'il est dessiné, et chaque suite de texte contre laquelle un lien est vérifié |

Les tailles que déclare une archive sont vérifiées avant la lecture d'une entrée, et une entrée dont les octets ne
correspondent pas à sa taille ou à sa somme de contrôle déclarées ne peut pas être lue : une bombe de décompression est
refusée avant d'être décompressée. Un flux PDF n'est pas décompressé au-delà de sa limite. Un PDF de quelques kilo-octets dont les formulaires se dessinent
les uns les autres mille fois demande des milliards d'opérations : il est refusé dès qu'il dépasse `operations`, en
quelques secondes.

```php
new DocumentImporter(limits: new Limits(entryBytes: 8 * 1024 * 1024, totalBytes: 32 * 1024 * 1024, depth: 50));
```

Le XML (les entrées d'une archive, un ODT plat) est analysé sans accès réseau, et une entrée vide ou qui déclare un
type de document, ce que ne fait aucun document bureautique, est refusée avant d'être analysée : cela ferme la porte à
l'expansion d'entités (XXE et ses cousines).
