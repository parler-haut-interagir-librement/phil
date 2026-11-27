<!-- phil-dev source-sha256=7f0c55c16877f91c125b77fea703a3a55684828e4fca8bc41a89f8c09c83ced3 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/fonts-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## Non publié

La prochaine version est la 0.1.2-alpha.

Développement seulement.

### Modifié

- Les prérequis de développement demandent la dernière mineure de chaque dépendance (`twig/twig` 3.31).
- Les prérequis de développement prennent `twig/twig` 3.31.1 ou plus récent, qui corrige les avis sur son bac à sable
  publiés le 2026-11-24.

## 0.1.1-alpha - 2026-11-18

### Modifié

- `phil/media-bundle` 0.2 est accepté à côté de 0.1.

## 0.1.0-alpha - 2026-11-13

### Ajouté

- `FontFiles` : les fichiers de polices lus avec le décompresseur Brotli dont dispose PHP — le WOFF2 quand `ext-brotli`
  est chargée, ce que Composer suggère, tout autre format partout —, et `FontUnreadable`, qui nomme le fichier et, pour
  un fichier WOFF2 sans `ext-brotli`, dit de l'installer ou d'employer le fichier WOFF, TrueType ou OpenType de la
  police.
- `FontCatalogue` et `CatalogueEntry` : les fichiers de polices d'un dossier et de ses sous-dossiers, lus une fois,
  triés par chemin, avec la raison de chaque fichier illisible ; un fichier par chemin, un style par famille, graisse —
  dans la plage d'une police variable — et style parmi les formats dans l'ordre, le style lui-même, et `FontNotFound` ;
  les fiches de licence des fichiers, un fichier illisible gardant la fiche déjà gardée.
- `FontFaces` : une règle `@font-face` par style, ses fichiers en plusieurs formats listés WOFF2, WOFF, OpenType,
  TrueType, avec des URL sous le chemin public ou propres à l'application ; les collections, les styles sans nom et les
  styles dont aucun fichier n'a été lu laissés de côté.
- `Licences` : le registre des licences gardé en JSON à côté des polices, comparé au catalogue, et écrit une fois les
  fichiers vérifiés.
- `PhilFontsBundle` et la configuration `phil_fonts` : `directory`, `public_path`, `font_display` et `licence_register`.
- Les commandes `phil:fonts:list`, `phil:fonts:licences` (en échec tant que la licence d'un fichier n'est pas vérifiée,
  `--write`) et `phil:fonts:css` (`--output`), et la fonction Twig `fonts_css()`.
- `PdfFonts`, les styles TrueType du catalogue pour le rédacteur PDF natif de `phil/paged-media`, et `MediaFonts`, les
  polices téléversées dans `phil/media-bundle`, chacun enregistré quand son paquet est installé ;
  `RequiredServicesPass`, qui retire `MediaFonts` quand la médiathèque n'est pas activée.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
