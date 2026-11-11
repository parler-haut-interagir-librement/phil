<!-- phil-dev source-sha256=4188a463fbd5dd8192258a58790918a95e256749520c88a6dfbdc1d037773971 -->
# Référence

| Type | Genre | Rôle |
|---|---|---|
| `Phil\Fonts\FontFile` | `final readonly class` | Un fichier de police : son format, ses faces, son SHA-256 et ses métadonnées étendues |
| `Phil\Fonts\Brotli` | `final class` | Le décompresseur Brotli de `ext-brotli` pour WOFF2, ou aucun sans l'extension |
| `Phil\Fonts\Format` | enum | TrueType, OpenType, collection, WOFF ou WOFF2, chacun étant le mot-clé de `format()` en CSS |
| `Phil\Fonts\Font` | `final readonly class` | Les métadonnées d'une face |
| `Phil\Fonts\FontStyle` | enum | Normal, italique ou oblique |
| `Phil\Fonts\Outlines` | enum | TrueType, PostScript ou aucun |
| `Phil\Fonts\Axis` | `final readonly class` | Un axe de variation : étiquette, minimum, valeur par défaut, maximum, identifiant de nom |
| `Phil\Fonts\EmbeddingPermissions` | `final readonly class` | Le `fsType` de `OS/2` : usage, sous-ensembles, bitmaps |
| `Phil\Fonts\EmbeddingUsage` | enum | Installable, modifiable, aperçu et impression, restreinte |
| `Phil\Fonts\Name\Names` | `final readonly class` | Chaque chaîne de la table `name`, et celle à afficher |
| `Phil\Fonts\Name\NameRecord` | `final readonly class` | Une chaîne avec sa plateforme, son encodage, sa langue et son identifiant de nom |
| `Phil\Fonts\Name\NameId` | enum | Les identifiants de nom prédéfinis, de 0 à 25 |
| `Phil\Fonts\Css\FontFaceRule` | `final readonly class` | Une règle `@font-face` écrite à partir d'une face |
| `Phil\Fonts\Css\FontDisplay` | enum | Les valeurs de `font-display` |
| `Phil\Fonts\Licence\Licence` | `final readonly class` | La description et l'URL de la licence d'une face, et l'identifiant SPDX reconnu |
| `Phil\Fonts\Licence\FaceLicence` | `final readonly class` | Ce qu'une face dit de sa licence, tel que conservé dans une fiche |
| `Phil\Fonts\Licence\LicenceRecord` | `final readonly class` | La fiche de licence d'un fichier |
| `Phil\Fonts\Licence\LicenceRegister` | `final readonly class` | Une fiche par fichier : balayée, conservée en JSON, comparée |
| `Phil\Fonts\Licence\Field` | `final readonly class`, interne | Les champs typés d'une fiche relue à partir du JSON |
| `Phil\Fonts\Reader\FontReader` | `final readonly class`, interne | Les conteneurs, jusqu'aux tables de chaque face |
| `Phil\Fonts\Reader\FaceReader` | `final readonly class`, interne | Une `Font` tirée des tables d'une face |
| `Phil\Fonts\Reader\NameTable` | `final readonly class`, interne | Les chaînes d'une table `name`, décodées |
| `Phil\Fonts\Reader\CharacterMap` | `final readonly class`, interne | Les plages de points de code d'une table `cmap` |
| `Phil\Fonts\Reader\Binary` | `final readonly class`, interne | Des nombres gros-boutistes lus avec vérification des limites |
| `Phil\Fonts\Exception\FontsException` | interface | Chaque exception de la bibliothèque |
| `Phil\Fonts\Exception\InvalidFont` | `final class` | Un fichier qui n'est pas une police que la bibliothèque peut lire |
| `Phil\Fonts\Exception\UnsupportedFont` | `final class` | Une police valide que la bibliothèque ne lit pas |
| `Phil\Fonts\Exception\InvalidFontFace` | `final class` | Une règle `@font-face` qui ne peut pas être écrite |
| `Phil\Fonts\Exception\InvalidLicenceRegister` | `final class` | Un registre qui ne peut pas être relu |
