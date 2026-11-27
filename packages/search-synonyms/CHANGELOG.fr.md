<!-- phil-dev source-sha256=5f195672a14f6f6b551b748bf5030e0bf14d02787b844f52a9f15f9e19d33316 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/search-synonyms` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## Non publié

La prochaine version est la 0.1.1-alpha.

Tests seulement.

### Modifié

- Le test d'une erreur JSON accepte la position de l'erreur que PHP 8.6 ajoute à sa raison.

## 0.1.0-alpha - 2026-11-12

### Ajouté

- `SynonymSet`, une règle d'équivalence ou à sens unique, ses expressions normalisées, gardées une fois et vérifiées
  d'après leurs limites ; `SynonymList`, les règles d'une langue sous sa sous-étiquette principale ; `InvalidSynonyms`,
  avec la ligne d'un fichier.
- `SynonymValidator` et `Violation` : une expression à gauche de deux règles, une règle à sens unique qui mène à une
  expression qu'une autre règle réécrit.
- `SolrSynonymsReader` et `SolrSynonymsWriter` : le format de synonymes de Solr, les commentaires, les échappements, une
  marque d'ordre des octets et chaque saut de ligne lus, une ligne qui n'est pas une règle refusée avec son numéro, un
  fichier de 1 Mio et des lignes de 4096 octets au plus, ce qui est écrit relu comme les mêmes règles ; `JsonSynonyms`,
  le document JSON.
- `SynonymStore` ; `FileSynonymStore`, un fichier par langue au format de Solr, lu sur demande et écrit atomiquement ;
  `InMemorySynonymStore`.
- Les cibles de fuzzing des deux lecteurs.
- Le paquet : `composer.json`, `phil-dev.php` et `composer-dependency-analyser.php`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
