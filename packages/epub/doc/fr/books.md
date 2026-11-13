<!-- phil-dev source-sha256=a38fb34534b4808d05de63d2fe5e12e466b1d4e147f032476bfb0b4ceea184c8 -->
# Livres

Un `Book` est une valeur : ses métadonnées, ses chapitres dans l'ordre de lecture, ses autres fichiers, sa couverture,
et la façon dont sa navigation est faite. Chaque méthode `with` retourne un nouveau livre.

```php
$book = new Book($metadata)
    ->withChapter($titlePage, $chapterOne, $chapterTwo)
    ->withAsset($stylesheet, $map, $font)
    ->withCover(new Cover($coverImage, 'A lighthouse in a storm, under the title'));
```

## Métadonnées

`Metadata` contient ce qu'exige EPUB (un titre, une langue, un identifiant unique et le moment de la dernière
modification du livre) et ce qu'il permet :

| Propriété | Écrite comme | Remarques |
|---|---|---|
| `title` | `dc:title` | Non vide |
| `language` | `dc:language`, `xml:lang` du paquet et de chaque document | Une étiquette de langue comme `en` ou `fr-CA` |
| `identifier` | `dc:identifier`, l'identifiant unique du paquet | Aussi la clé de l'obscurcissement des polices |
| `modified` | `dcterms:modified`, en UTC à la seconde ; la date de chaque entrée ZIP | Exigée par EPUB 3 |
| `creators`, `contributors` | `dc:creator`, `dc:contributor`, avec les précisions `role` (code de relation MARC) et `file-as` | |
| `publisher`, `description`, `rights`, `source` | `dc:publisher`, `dc:description`, `dc:rights`, `dc:source` | `source` nomme l'édition imprimée que suit une liste de pages |
| `published` | `dc:date` | `YYYY`, `YYYY-MM` ou `YYYY-MM-DD` |
| `subjects` | un `dc:subject` chacun | |
| `otherIdentifiers` | d'autres éléments `dc:identifier` | |
| `direction` | `dir` du paquet et des documents, `page-progression-direction` de l'épine | `Direction::Default` n'en écrit aucun |
| `accessibility` | métadonnées schema.org et EPUB Accessibility | Voir [Accessibilité](accessibility.md) |

## Identifiants

| Fabrique | Valeur écrite | Précision |
|---|---|---|
| `Identifier::uuid('7c9e…')` | `urn:uuid:7c9e…`, en minuscules | aucune |
| `Identifier::isbn('978-2-…')` | les 13 chiffres, chiffre de contrôle vérifié | liste de codes ONIX 5, `15` |
| `Identifier::doi('10.1000/182')` | le DOI | liste de codes ONIX 5, `06` |
| `new Identifier('urn:…', IdentifierType::Urn)` | la valeur | liste de codes ONIX 5, `22` |
| `new Identifier('X-1')` | la valeur | liste de codes ONIX 5, `01` (propriétaire) |

Un livre garde son identifiant unique d'une édition à l'autre : les systèmes de lecture s'en servent pour reconnaître
un livre qu'ils ont déjà. Donnez un nouvel UUID à une nouvelle œuvre, pas à un fichier corrigé.

## Créateurs

`new Creator('Ann Writer', 'aut', 'Writer, Ann')` : un nom, un code de relation MARC de trois lettres minuscules
(`aut` auteur, `ill` illustrateur, `edt` éditeur intellectuel, `trl` traducteur, `nrt` narrateur…) et la forme qui
sert au tri.

## Chapitres

`new Chapter($html, …)` est un document de contenu. Son HTML est un fragment, comme on en trouve dans le corps d'une
page ; voir [Documents de contenu](content.md) pour ce qu'il devient.

| Argument | Valeur par défaut | Effet |
|---|---|---|
| `title` | le texte de son premier titre, sinon le titre du livre | Le `<title>` du document, son entrée dans la table des matières quand il n'a pas de titre, le libellé de son repère |
| `fileName` | `chapter-N.xhtml`, N étant sa position | Son chemin dans le paquet ; se termine par `.xhtml` ; peut contenir des dossiers, comme `text/one.xhtml` |
| `landmark` | aucun | Un `LandmarkType` : l'`epub:type` de son corps et une entrée dans les repères |
| `inToc` | `true` | Si ses titres, ou son titre, figurent dans la table des matières |
| `linear` | `true` | `false` écrit `linear="no"` dans l'épine, pour des notes ou des réponses qu'on atteint par des liens |
| `language` | celle du livre | Ses propres `xml:lang` et `lang` |
| `overlay` | aucune | Un `MediaOverlay`, la narration synchronisée avec son texte : voir [Médias, superpositions et mise en page](media.md) |
| `layout` | celle du livre | `Layout::PrePaginated` ou `Layout::Reflowable`, quand ce chapitre est mis en page autrement que le livre |
| `viewport` | celle du livre | Le `Viewport` d'un chapitre prépaginé |
| `pageSpread` | aucun | Un `PageSpread` : la page de gauche, la page de droite ou le centre d'une double page |

## Ressources

Chaque autre fichier est un `Asset`, à un chemin relatif au répertoire du paquet, fait de lettres, de chiffres, de `.`,
`_`, `-` et `/` :

| Fabrique | Type de média | Remarques |
|---|---|---|
| `Asset::stylesheet('css/book.css', $css)` | `text/css` | Liée depuis chaque document de contenu, la navigation et la couverture, dans l'ordre d'ajout |
| `Asset::image('images/map.png', $bytes)` | lu d'après les octets : PNG, JPEG, GIF, WebP ou SVG | Ou donné en troisième argument |
| `Asset::font('fonts/body.otf', $bytes, obfuscate: true)` | lu d'après les octets : TrueType, OpenType, WOFF ou WOFF2 | Ou donné en quatrième argument |
| `Asset::audio('audio/one.mp3', $bytes)` | lu d'après les octets : MP3, AAC dans MP4, Opus, Vorbis, FLAC ou WAV | Ou donné en troisième argument ; une solution de repli en quatrième |
| `Asset::video('video/clip.mp4', $bytes)` | lu d'après les octets : MP4, WebM ou Ogg | Ou donné en troisième argument ; une solution de repli en quatrième |
| `Asset::textTrack('video/clip.vtt', $vtt)` | `text/vtt` | Un fichier WebVTT de sous-titres ou de légendes |

Les feuilles de style, les images, les polices et les pistes de texte ne sont acceptées que dans les types de médias de
base d'EPUB, pour qu'elles n'aient besoin d'aucune solution de repli. Les fichiers audio et vidéo, et les solutions de
repli qu'ils peuvent porter, sont décrits dans [Médias, superpositions et mise en page](media.md). Un chemin ne sert
qu'une fois dans un livre ; `nav.xhtml`, `toc.ncx`, `cover.xhtml` et `package.opf` appartiennent à la bibliothèque.

Une feuille de style désigne ses polices et ses images par des chemins relatifs à elle-même
(`url(../fonts/body.otf)`). La bibliothèque ne lit pas le CSS ; une application qui accepte du CSS de ses utilisateurs
le fait d'abord passer par un assainisseur.

## Obscurcissement des polices

Une ressource de police créée avec `obfuscate: true` est écrite avec l'algorithme de l'IDPF d'EPUB 3 : ses 1040
premiers octets sont combinés avec le SHA-1 de l'identifiant unique du livre, et `META-INF/encryption.xml` la liste.
Les systèmes de lecture le défont ; une police copiée hors de l'archive n'est pas directement utilisable. Ce n'est pas
du chiffrement : cela respecte une licence de police qui l'exige, comme bien des licences commerciales pour
l'incorporation dans des livres numériques, et rien de plus. Une police dont la licence interdit l'incorporation ne
peut pas devenir incorporable par l'obscurcissement.

## Couverture

`new Cover($image, $alt)` prend une ressource d'image et son texte de remplacement. L'image est marquée `cover-image`
dans le manifeste (les systèmes de lecture l'affichent dans leur bibliothèque) et affichée seule dans `cover.xhtml`, en
premier dans l'ordre de lecture, avec le repère `cover`. L'image peut figurer parmi les ressources du livre ou
seulement dans la couverture.
