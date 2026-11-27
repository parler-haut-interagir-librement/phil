<!-- phil-dev source-sha256=ee7b5ce36bbf3be5be523c467ffd18ed0bba6b285b9d1a3b1cdbeb718592807a -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/stockroom` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.1-alpha - 2026-11-24

### Modifié

- Exige la dernière mineure de chaque dépendance (`symfony/service-contracts` 3.8).

## 0.1.0-alpha - 2026-11-13

### Ajouté

- `Container`, imbriqué dans un parent et étiqueté par un code, et `Item`, compté comme quantité ou suivi comme une
  unité (`Tracking`), avec son emplacement d'attache et son état (`ItemCondition` : utilisable, à réparer, retiré) ;
  `Identifier`, `Text` et `Quantity` bornent chaque identifiant — jamais une adresse courriel —, nom, note et quantité.
- `Place` (`PlaceKind` : contenant, événement, détenteur) avec une clé que garde un stockage, `Holding`, et `Movement`
  (`MovementKind` : entrée, sortie, transfert, perte, retour), ses lieux vérifiés d'après sa sorte et son moment gardé
  en UTC.
- `Stockroom` : des contenants ajoutés, déplacés avec tout ce qu'ils contiennent et lus comme un chemin, sur 32 niveaux
  au plus, les boucles refusées ; des articles aux codes uniques ; `receive()`, `issue()` — depuis un contenant, ou
  depuis les contenants qui détiennent l'article, son emplacement d'attache d'abord —, `transfer()`, `lose()` et
  `giveBack()`, chacun écrit au journal à mesure qu'il change les soldes, sans jamais prendre plus que ce que contient
  un lieu ; les unités suivies en un seul lieu à la fois ; les articles à réparer ou retirés gardés en stock ; les
  contenus, les sources, les historiques et les mouvements d'une référence, chaque liste bornée.
- `Deployments` : une liste de chargement par événement (`LoadLine`, `LoadList`, 500 lignes au plus), `picking()`
  (`PickLine`), `deploy()` tout ou rien ou partiel, terminé plus tard par le même appel (`Deployment`, `Shortfall`),
  `outstanding()`, et `checkIn()`, qui ramène ce qui a été compté, liste ce qui manque et le passe en perte sur demande
  (`ReturnCheck`).
- `LoadListReader`, qui lit une liste de chargement tapée en texte — quantité, code d'article, contenant — et en récrit
  une, avec la cible de fuzzing `load-list`.
- Les ports `Containers`, `Items`, `Balances`, `Journal` et `LoadLists`, leurs adaptateurs en mémoire remis à zéro entre
  les requêtes, et `StockReader`, le port de lecture du stock pour un autre paquet.
- `InvalidStock`, `UnknownStock` et `InsufficientStock`, sous `StockroomException`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
