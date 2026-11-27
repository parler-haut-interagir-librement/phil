<!-- phil-dev source-sha256=addeea13523e6e2fbef22db4c37b75cbb578746fe889510621e1bf22b5d42b3c -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/commerce-product-template` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `Label` et `Swatch` : des textes par locale, lus dans une locale, sa langue ou le premier donné ; une couleur
  hexadécimale ou la photo d'un échantillon.
- `AxisValue` et `Axis` : des valeurs dans l'ordre avec leur code d'UGS, leur libellé, leur échantillon, leur nom chez
  le fournisseur, leur photo par face et leurs canaux ; jusqu'à 500 valeurs par axe, `design`, `lang` et `template`
  réservés.
- `PrintZone`, `PhotoBox` et `Face` : des zones d'impression en millimètres avec leur résolution et leur rectangle sur
  les photos de la face, la première zone d'une face étant celle par défaut.
- `Pricing` : un prix de base, un prix par valeur d'un axe et des suppléments sur n'importe quel axe, dans une seule
  devise, jamais négatifs.
- `Exclusion` et `Combination` : les combinaisons qu'un gabarit ne fait pas, par valeurs d'axes ; une valeur de chaque
  axe, confrontée à une condition.
- `SkuPattern` : des marqueurs et des littéraux, chaque marqueur une fois, `{design}` exigé, rendu par produit.
- `Template` : un produit vierge avec jusqu'à 8 axes et 10 000 combinaisons, ses faces, ses prix, ses exclusions, son
  motif d'UGS et son fournisseur, chaque nom qu'il emploie vérifié ; ses combinaisons dans l'ordre, le dernier axe
  variant le plus vite.
- `LanguageVersion`, `Artwork` et `Design` : le code d'un motif, son nom, jusqu'à 8 versions linguistiques, jusqu'à 200
  œuvres par face, zone, langue et condition, les gabarits sur lesquels il est fait et les valeurs qu'il permet.
- `VariantPlanner`, `Variant` et `PlacedArtwork` : un produit par version linguistique et par combinaison permise, son
  UGS, son prix, ses canaux, l'œuvre la plus précise par zone, son nom, ses attributs et ses photos ; les UGS qu'un
  catalogue refuse, deux produits qui partagent une UGS, une zone que le gabarit n'a pas, deux œuvres aussi précises
  l'une que l'autre et plus que sa limite de produits refusés.
- `TemplateArray` et `DesignArray` : les gabarits et les motifs comme tableaux versionnés de scalaires, relus avec
  chaque valeur vérifiée.
- `ProductTemplateException`, implémentée par `InvalidDefinition` et `PlanRefused`.
- Les cibles de fuzzing `sku-pattern` et `template-array`, et le banc d'essai `VariantPlannerBench`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
