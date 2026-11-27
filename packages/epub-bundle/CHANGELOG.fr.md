<!-- phil-dev source-sha256=9aff24b2985db3efbbf1907e073950377aa1bb0866c914b3e40068856dad6972 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/epub-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.1-alpha - 2026-11-18

### Modifié

- `phil/media-bundle` 0.2 est accepté à côté de 0.1.

## 0.1.0-alpha - 2026-11-13

### Ajouté

- `Epubs` : un livre écrit en octets ou gardé dans le stockage, et le livre qu'une source construit à partir d'un
  identifiant.
- `BookSource`, `#[AsBookSource]` et `BookSources` : les livres de l'application construits à partir d'un identifiant,
  enregistrés par nom, et `UnknownBookSource`.
- Le port `EpubStorage`, `DirectoryStorage`, qui refuse un chemin qui sort de son dossier, `MediaLibraryStorage`, qui
  garde chaque livre comme un média de `phil/media-bundle`, dans une collection quand elle est configurée, et
  `StorageFailed`.
- `PhilEpubBundle` et la configuration `phil_epub` : `temporary_directory`, `storage` (`directory` ou `media`, le
  dossier, la collection de médias) et `fonts` (obscurcissement, dossier, feuille de style).
- Le message Messenger `WriteEpub`, qui porte un nom de source, un identifiant et un chemin, `EpubHandler`, qui réessaie
  un échec qui peut passer, et l'événement `EpubStored`.
- La commande `phil:epub:write`, vers le stockage ou vers un fichier (`--output`).
- `EpubFonts`, les familles du dossier de polices de `phil/fonts-bundle` intégrées à un livre — le plus petit fichier de
  chaque style, obscurci quand c'est configuré, une feuille de style de leurs règles —, qui refuse avec
  `FontNotEmbeddable` un style dont la licence interdit l'intégration ou ne permet que les bitmaps ; `MediaAssets`, les
  médias de `phil/media-bundle` comme images, une couverture avec son texte de remplacement, ou des polices ;
  `RequiredServicesPass`, qui les retire quand leur bundle n'est pas activé.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
