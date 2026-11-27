<!-- phil-dev source-sha256=4616f3a7336a8e49458f7207f3898e816eb02b99bae7ac0b3f1aac47018c16f5 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/print-preflight-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.1-alpha - 2026-11-18

### Modifié

- `phil/media-bundle` 0.2 est accepté à côté de 0.1.

## 0.1.0-alpha - 2026-11-13

### Ajouté

- `PrintProfiles` : les profils d'imprimeur de la configuration par nom, un profil par défaut, et `UnknownProfile`.
- `Lengths` : des longueurs (`3mm`, `0.125in`, `9pt`, `12px`, `0`) et des formats (`a4`, `a5`, `letter`, `148x210mm`)
  lus d'un texte.
- `Preflighter` et `#[AsPreflightCheck]` : le contrôle en amont d'un document d'après un profil, avec les vérifications
  de l'application après celles intégrées.
- `MeasuredDocument` et le port `FontResolver` : un document mesuré sur une page mise en page pour l'impression, lu de
  JSON en pixels CSS ou dans une autre unité — pages, fond perdu, images avec leurs tailles naturelles et mises à
  l'échelle, boîtes, espaces de couleur, couverture d'encre, surimpression, épaisseur des traits et transparence quand
  le script les connaît, polices par nom et `fsType` ou par famille, graisse et style —, et `InvalidMeasurement`, qui
  nomme le champ qu'elle ne peut pas lire.
- `PhilPrintPreflightBundle` et la configuration `phil_print_preflight` : `default_profile` et `profiles`, chacun avec
  chaque règle du profil de la bibliothèque — couverture d'encre, épaisseur des traits, surimpression et transparence
  parmi elles — et vérifié par la bibliothèque à la construction du conteneur.
- La commande `phil:print-preflight:check`, les constats en texte ou en JSON (`--format`), d'après un profil
  (`--profile`), en échec sur une erreur.
- `PdfLayout`, qui consigne ce que place le rédacteur PDF natif de `phil/paged-media` — images, rectangles, lignes de
  texte et leurs polices — ; `MediaImages`, les images de `phil/media-bundle` placées sur une page ; et
  `CatalogueFontResolver`, les polices du catalogue de `phil/fonts-bundle`, retiré par `FontCataloguePass` quand ce
  bundle n'est pas activé.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
