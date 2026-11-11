<!-- phil-dev source-sha256=b1966960a4a28b5b213338bcacea1e8a59ce4186b2e847402e403dc70b5633a5 -->
# Exceptions

Chaque exception implémente `Phil\Fonts\Exception\FontsException`.

| Exception | Étend | Lancée lorsque |
|---|---|---|
| `InvalidFont` | `InvalidArgumentException` | Un fichier illisible, de moins de 12 octets, de signature inconnue, tronqué, avec une table hors du fichier, sans table `name` ; une collection sans face ou avec une face qui n'est pas un `sfnt` ; un bloc WOFF qui ne se décompresse pas à sa longueur ; un bloc Brotli de mauvaise longueur ; un nombre WOFF2 invalide ; un fichier qui dépasse une limite (32 Mio, 256 tables par face, une étiquette listée deux fois, 64 faces, 64 Mio de tables, des blocs WOFF qui se chevauchent) ; un indice de face que le fichier n'a pas |
| `UnsupportedFont` | `RuntimeException` | Une police valide que la bibliothèque ne lit pas : un fichier WOFF2 sans décompresseur Brotli (`woff2WithoutBrotli()`, dont le message dit d'installer `ext-brotli` ou de lire le fichier WOFF, TrueType ou OpenType), une collection WOFF2 |
| `InvalidFontFace` | `InvalidArgumentException` | Une règle `@font-face` sans famille ou sans source |
| `InvalidLicenceRegister` | `InvalidArgumentException` | Un registre de licences qui n'est pas du JSON valide, ou une fiche à laquelle il manque un champ ou dont un champ est du mauvais type, un format ou un usage d'incorporation inconnu, ou aucune face |

Chaque message nomme ce qui ne va pas, par exemple `The font has no "name" table.` ou
`The field "sha256" must be a string.`
