<!-- phil-dev source-sha256=70b741fa9f9025b1621dea7fb19d8394ea8bec3cbe71f00b57b0cfb349813517 -->
# Lire des fichiers de police

## Fichiers et formats

`FontFile::fromBytes(string $bytes, ?Closure $brotli = null)` et
`FontFile::fromFile(string $path, ?Closure $brotli = null)` lisent un fichier et en tirent :

| Propriété | Ce qu'elle contient |
|---|---|
| `format` | Le conteneur, un `Format` |
| `faces` | Les faces du fichier, jamais vide : une seule, ou plusieurs pour une collection |
| `sha256` | Le SHA-256 des octets du fichier, en hexadécimal |
| `extendedMetadata` | Le XML des métadonnées étendues d'un fichier WOFF ou WOFF2, décompressé, ou null |

`face(int $index = 0)` donne une face, et lance `InvalidFont` pour un indice que le fichier n'a pas.

| `Format` | Signature | Remarques |
|---|---|---|
| `TrueType` | `00 01 00 00` ou `true` | Des contours TrueType, ou aucun |
| `OpenType` | `OTTO` | Des contours PostScript (CFF) |
| `Collection` | `ttcf` | Chaque face lue, chacune avec ses propres tables |
| `Woff` | `wOFF` | Chaque table décompressée avec zlib jusqu'à la longueur qu'elle déclare, ou gardée telle quelle lorsqu'elle est stockée sans compression |
| `Woff2` | `wOF2` | Toutes les tables décompressées ensemble au moyen du décompresseur Brotli fourni |

Chaque valeur de `Format` est le mot-clé de la fonction CSS `format()`. La bibliothèque lit dans les tables ce dont elle
a besoin et vérifie chaque lecture par rapport à la fin de sa table : un fichier tronqué, une table hors du fichier, un
bloc WOFF qui ne se décompresse pas à la longueur déclarée ou un bloc Brotli de mauvaise longueur lance `InvalidFont`.

Les fichiers de polices sont souvent téléversés : le travail qu'un fichier peut causer est donc borné, et un fichier
qui dépasse une limite lance `InvalidFont` :

| Limite | Valeur |
|---|---|
| Le fichier | 32 Mio |
| Les tables que liste une face | 256, chaque étiquette une fois |
| Les faces d'une collection | 64 |
| Les tables et les métadonnées, copiées ou décompressées | 64 Mio en tout, réservés avant chaque copie ou décompression |

Seules les six tables dont une face est lue sont copiées ou décompressées ; les autres sont vérifiées à l'intérieur du
fichier. Deux blocs WOFF qui partagent des octets sont refusés.

Les encodeurs WOFF2 transforment les tables `glyf`, `loca` et `hmtx`. Elles ne portent aucune métadonnée : la
bibliothèque ne les reconstruit donc pas, elle note seulement leur présence. Une collection WOFF2 lance
`UnsupportedFont`, tout comme un fichier WOFF2 lu sans décompresseur : passez `Brotli::decompressor()`, qui vaut
`brotli_uncompress()` lorsque `ext-brotli` est chargée (voir [Installation](installation.md#brotli-pour-woff2)).

## Faces

Une `Font` est lue à partir de six tables. Seule `name` est requise ; chaque autre table absente laisse ses valeurs à
leurs valeurs par défaut.

| Table | Ce que lit la bibliothèque |
|---|---|
| `name` | Chaque chaîne, décodée |
| `OS/2` | `usWeightClass`, `usWidthClass`, `fsType`, `fsSelection` |
| `head` | `macStyle`, lorsque la face n'a pas de table `OS/2` |
| `post` | `italicAngle`, `isFixedPitch` |
| `fvar` | Les axes de variation |
| `cmap` | La sous-table Unicode, au format 12 ou au format 4 |

| Propriété ou méthode | Ce qu'elle donne |
|---|---|
| `family()`, `subfamily()` | La famille et la sous-famille typographiques (noms 16 et 17) lorsque la police les donne, sinon les noms 1 et 2 ; une chaîne vide s'il n'y en a aucun |
| `fullName()`, `postScriptName()`, `version()` | Les noms 4, 6 et 5 |
| `copyright()`, `trademark()`, `manufacturer()`, `designer()`, `vendorUrl()`, `designerUrl()` | Les noms 0, 7, 8, 9, 11 et 12 |
| `licence()` | Les noms 13 et 14, sous forme de `Licence` |
| `weight` | `usWeightClass`, de 1 à 1000 ; 400 hors de cet intervalle ; sans `OS/2`, 700 pour un `macStyle` gras, sinon 400 |
| `widthClass` | `usWidthClass`, de 1 à 9 ; 5 hors de cet intervalle ou en son absence |
| `style` | `Oblique` pour le bit oblique de `fsSelection`, `Italic` pour son bit italique, sinon `Normal` ; sans `OS/2`, le bit italique de `macStyle` |
| `embedding` | Les `EmbeddingPermissions` de `fsType` ; voir [Permissions d'incorporation](embedding.md) |
| `outlines` | `TrueType` pour une table `glyf`, `PostScript` pour `CFF ` ou `CFF2`, sinon `None` |
| `italicAngle`, `fixedPitch` | Tirés de `post` : les degrés dans le sens antihoraire à partir de la verticale, et si chaque glyphe a la même chasse |
| `axes`, `axis($tag)`, `isVariable()` | Les axes de `fvar`, chacun un `Axis` avec son étiquette, son minimum, sa valeur par défaut, son maximum et son identifiant de nom |
| `weightRange()` | La plage de l'axe `wght`, arrondie, ou la graisse deux fois |
| `stretchRange()` | La plage de l'axe `wdth`, ou le pourcentage que représente la classe de largeur (de 50 % à 200 %), deux fois |
| `unicodeRanges` | Les plages de points de code que déclare la table de caractères, triées et fusionnées là où elles se touchent |

Les plages viennent de la sous-table Unicode telle qu'elle est déclarée : le format 12 s'il existe, sinon le format 4,
dont le segment de fin est exclu. Un point de code qu'une sous-table fait correspondre au glyphe manquant compte quand
même comme couvert.

## Noms

`$font->names` contient chaque chaîne de la table `name` sous forme de valeurs `NameRecord` : plateforme, encodage,
langue, identifiant de nom et chaîne en UTF-8. Les chaînes de la plateforme Unicode et celles de la plateforme Windows
dans ses encodages Unicode et symbole sont lues en UTF-16 ; les chaînes Macintosh dans l'encodage Roman sont lues en Mac
Roman. Les chaînes dans d'autres encodages (les encodages japonais ou chinois de Macintosh, la plateforme ISO obsolète)
sont exclues.

`get(NameId $id)` donne la chaîne qu'un lecteur attend le plus probablement : Windows en anglais des États-Unis, puis
toute autre langue Windows, puis Unicode, puis Macintosh, une chaîne vide comptant comme une absence. `all(NameId $id)`
donne chaque chaîne d'un nom, pour afficher les noms d'une police dans chaque langue pour laquelle ils ont été écrits :

```php
use Phil\Fonts\Name\NameId;

foreach ($font->names->all(NameId::FullName) as $record) {
    printf("%d/%d/0x%04X: %s\n", $record->platform, $record->encoding, $record->language, $record->value);
}
```

La langue d'un enregistrement est l'identifiant numérique de la plateforme : sur Windows, 0x0409 désigne l'anglais des
États-Unis et 0x040C le français.
