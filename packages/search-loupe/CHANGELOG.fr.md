<!-- phil-dev source-sha256=2926f14307a408da7fe6c6080e6e3b5af0055810971a7c25b0bfb198d3622342 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/search-loupe` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.2-alpha - 2026-11-24

### Modifié

- Exige la dernière mineure de chaque dépendance (`phil/search-bundle` 0.2).

## 0.1.1-alpha - 2026-11-20

Il s'installe avec `phil/search-bundle` 0.2 aussi bien qu'avec 0.1. Une version corrective et non mineure : le port
`SearchIndex` qu'il implémente est le même dans les deux.

### Modifié

- `phil/search-bundle` 0.2 est accepté à côté de 0.1.

## 0.1.0-alpha - 2026-11-12

### Ajouté

- `LoupeIndex` : le `SearchIndex` de `phil/search-bundle` sur Loupe, un dossier par index ou la mémoire, ses champs
  appris à mesure que les documents arrivent et gardés dans `phil_search.json` avec le dossier des fichiers de Loupe ;
  les champs cherchés dans l'ordre de leur arrivée, selon lequel Loupe classe ; le fichier gardé en mémoire, relu quand
  il a changé, et élargi sous un verrou ; une reconstruction dans un nouveau dossier, adopté une fois complet ; sur
  disque, un nouveau champ copie les documents dans un nouveau dossier sous le verrou, puisque la migration propre des
  fichiers de Loupe échoue au-delà d'une centaine de documents ; les écritures sous le verrou partagé, jamais dans un
  dossier en cours de copie ; les noms d'index vérifiés ; la liste de valeurs d'une requête cherchée avec `IN` ; les
  facettes tirées de la distribution des facettes de Loupe, chaque facette dont le filtre est réglé comptée par une
  recherche de plus sans lui.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
