<!-- phil-dev source-sha256=d991c673373a13f584bc8af40cf10004aa9b87f22eb72707d81982047458d1db -->
# Exporter

`DocumentExporter` prend le chemin du retour : un document UJO écrit en fichier DOCX ou ODT, dans les styles que connaît
un traitement de texte, pour qu'une personne puisse le modifier dans sa suite bureautique et que l'importateur le relise
en les mêmes blocs.

```php
use Phil\DocumentImport\DocumentExporter;
use Phil\DocumentImport\Format;
use Phil\DocumentImport\MediaMap;

$exported = new DocumentExporter(new MediaMap($imported->media))
    ->export($document, Format::Docx, ['title' => 'Chapter One', 'creator' => 'Ann Writer']);

file_put_contents('chapter-one.'.$exported->extension(), $exported->bytes);
header('Content-Type: '.$exported->mediaType());
```

`export()` prend le document, `Format::Docx` ou `Format::Odt` (tout autre format lève `ExportFailed`), et les
métadonnées à écrire, sous les clés qu'une importation donne : `title`, `subject`, `creator`, `keywords`,
`description`, `language`, et `created` et `modified` en ISO 8601. `Exported` contient le format, les octets du
fichier et le rapport de ce pour quoi il n'a pas de place ; `mediaType()` et `extension()` nomment le fichier.

## Blocs

| UJO | DOCX | ODT |
|---|---|---|
| `paragraph` | `Normal`, aligné par son réglage `alignment` | `Text body`, aligné par un style automatique |
| `header` | `Heading 1` à `Heading 6`, avec leurs niveaux de plan | `text:h` en `Heading 1` à `Heading 6` |
| `list` | Une instance de numérotation par liste, imbriquée sur neuf niveaux, son compteur et son premier numéro ; les éléments d'une liste de tâches marqués `☑` ou `☐` | `text:list` avec son propre style de liste |
| `quote` | `Quote`, sa légende sur une ligne à part après un tiret | `Quotations`, de la même façon |
| `code` | `Code`, ses lignes séparées par des sauts de ligne | `Preformatted Text` |
| `table` | `Table Grid`, chaque ligne aussi large que la plus large, la ligne d'en-tête répétée sur chaque page | `table:table`, sa ligne d'en-tête dans `table:table-header-rows` |
| `image` | Une image en ligne avec son texte de remplacement, à 96 pixels par pouce et pas plus large que 6 pouces ; sa légende en `Caption` | Un `draw:frame` avec son `svg:title` et son `svg:desc` ; sa légende en `Caption` |
| `footnote` et sa référence | Une note de bas de page là où elle est référencée la première fois | Un `text:note` là où elle est référencée la première fois |
| `toc` | Le contrôle de table des matières de Word et son champ `TOC`, que Word remplit à l'ouverture du fichier | `text:table-of-content`, que la suite remplit à la mise à jour |
| `pageBreak` | Un saut de page | Un paragraphe qui saute avant |
| `delimiter` | `* * *`, centré | Pareil |
| `warning` | Son titre en gras et son message, en `Quote` | En `Quotations` |
| `linkTool`, `attaches` | Un paragraphe avec le lien, son titre comme texte | Pareil |
| `embed` | Un paragraphe avec le lien vers sa source, pour les sources `http` et `https` | Pareil |

Le texte garde le gras, l'italique, le souligné, le barré, le surlignage, le code en ligne (le style `Code Char` du
DOCX, le style `Source Text` de l'ODT), les exposants, les sauts de ligne et les liens vers des adresses `http`,
`https`, `mailto` et `tel` ; un lien vers toute autre adresse garde son texte. Un lien dans une note de bas de page
DOCX est écrit comme son texte suivi de son adresse. Une note référencée deux fois est écrite à sa première référence,
et une référence à une note que le document ne contient pas reste son numéro en exposant ; une note jamais référencée
est écrite à la fin, en `Caption`.

## Images

L'exportateur ne va jamais chercher une adresse. Les images viennent de la `Contract\MediaSource` de l'application, qui
répond les octets derrière l'adresse d'un bloc d'image, ou `null` ; `MediaMap` est une source sur des images en
mémoire, comme les médias d'une importation. Les images PNG, JPEG et GIF vont dans les deux formats. Une image SVG va
dans un ODT une fois assainie par `phil/svg-sanitizer` ; un DOCX ne peut pas la contenir sans image matricielle de
repli, elle est donc signalée. Une image que la source ne détient pas, ou d'un autre type, est signalée ; quand son
adresse est `http` ou `https`, un paragraphe y renvoie par un lien.

## Ce qui est signalé

Le `Report` d'`Exported` emploie les mêmes sortes qu'une importation : `block` avec le type d'un bloc qui n'a pas de
place dans un document de traitement de texte (`raw`, un type que l'exportateur ne connaît pas, un lien vers une adresse
qui n'est ni `http`, ni `https`, ni `mailto`) et `image` avec `not fetched` ou le type de média d'une image qu'il ne
peut pas écrire.

## Le fichier

Chaque entrée de l'archive porte une même date fixe, si bien que le même document donne les mêmes octets. Sur un PHP
avec `ZipArchive::openString()` et `ZipArchive::closeString()`, l'archive est écrite en mémoire et aucun fichier n'est
créé ; sinon elle est écrite par un fichier temporaire, dans `$temporaryDirectory` ou le répertoire temporaire du
système, supprimé avant que `export()` retourne. Les deux voies donnent les mêmes octets. Un ODT commence par son entrée
`mimetype`, stockée sans compression, comme le format l'exige.
