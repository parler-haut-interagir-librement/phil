<!-- phil-dev source-sha256=abc4f0d4d4f559eb17b6c810471b28a1f2f5baeb1dc5c843f413145f16024bdd -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/citations` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## Non publié

La prochaine version est la 0.1.1-alpha.

Tests seulement.

### Modifié

- Le test d'une erreur JSON accepte la position de l'erreur que PHP 8.6 ajoute à sa raison.

## 0.1.0-alpha - 2026-11-13

### Ajouté

- Le modèle CSL-JSON : `Reference`, les 45 types d'éléments de CSL 1.0.2, les variables de noms et de dates, `Name` en
  ses parties ou littéral, `PartialDate` et `CslDate` avec les intervalles, les saisons, l'approximation et les
  littéraux ; `CslJson` pour le lire et l'écrire, borné en taille, en éléments et en profondeur.
- `BibTeXReader` : les entrées entre accolades ou entre parenthèses, les macros `@string` et de mois, la concaténation
  `#`, `@comment` et `@preamble`, les accents et les commandes LaTeX rendus en Unicode simple, les noms dans les trois
  formes de BibTeX, `crossref`, les dates de BibLaTeX ; `RisReader` : les enregistrements, les types, les étiquettes,
  les lignes continuées, les noms et les identifiants fabriqués ; `ImportLimits`, les valeurs d'un fichier BibTeX
  bornées avec son entrée, les macros développées ; `InvalidImport` avec la ligne du problème.
- Les intervalles de pages écrits avec un tiret demi-cadratin en temps linéaire, quelles que soient les espaces autour
  des traits d'union.
- `ReferenceList` : les doublons par DOI, ou par titre, année et premier auteur, cités comme un seul.
- `Cite`, `Citation`, `Locator` et `CitationProcessor` : les citations résolues, la bibliographie ordonnée
  alphabétiquement dans la collation de la langue ou par première citation, numérotée, avec les lettres d'année et les
  premières citations distinguées, les références non citées listées sur demande.
- `Contract\CitationStyle`, `Style\AbstractStyle` et trois styles : `AuthorDate` à la manière d'APA, `Notes` à la
  manière de Chicago, `Numeric` à la manière d'IEEE.
- `Terms` pour l'anglais et le français, `RichText` en texte brut ou en HTML échappé.
- Les exceptions sous `CitationsException` : `InvalidReference`, `InvalidImport`, `UnknownReference`, `MissingTerms`.
- Des cibles de fuzzing et leurs germes pour les lecteurs BibTeX, RIS et CSL-JSON, et les tests unitaires de chaque
  partie.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README ; `CONTRIBUTING.md`, `SECURITY.md` et
  `CODE_OF_CONDUCT.md`.
