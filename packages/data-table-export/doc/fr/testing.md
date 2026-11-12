<!-- phil-dev source-sha256=48b767702c7bd782ac167f6120ef4e6dd222a9078a846e62dfbf046e7e3e302f -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau. Elle écrit chaque sorte
de cellule dans les deux formats, la garde contre les formules, la marque d'ordre des octets et les séparateurs, relit
les classeurs avec ZipArchive et DOM, vérifie les limites, les noms des feuilles de calcul, les mêmes octets pour la même
feuille, et qu'aucun fichier temporaire ne reste. Dans le monodépôt, Infection maintient le paquet à 100 % des mutants
couverts.

## La cible de fuzzing

`tests/Fuzz/cell.php` écrit en cellule un texte que personne n'a écrit : en XLSX, il donne toujours du XML bien formé,
et en CSV la garde ne le laisse jamais commencer comme une formule. Depuis la racine du monodépôt :

```sh
composer fuzz -- cell --budget=60
```

## Les bancs d'essai

`tests/Benchmark/ExportBench.php` écrit dix mille lignes de cinq cellules en CSV et en XLSX, depuis la racine du
monodépôt :

```sh
composer bench -- --filter=ExportBench
```
