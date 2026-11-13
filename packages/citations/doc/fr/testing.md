<!-- phil-dev source-sha256=c501b1b6944cd8b180dbb79ed6f82ced1f70b0edf5d5bc47cce23846abb6e4aa -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau. Elle vérifie le modèle
et ses refus, le CSL-JSON lu puis réécrit, chaque accent et chaque commande LaTeX, les noms, les types, les champs, les
macros, le `crossref` et les erreurs de syntaxe de BibTeX avec leurs lignes, les enregistrements, les types, les
balises et les identifiants fabriqués de RIS, les limites de chaque lecteur, les doublons, et les trois styles en
anglais et en français sur un même jeu de références de toutes formes, en texte et en HTML. Dans le monodépôt,
Infection maintient le paquet à 100 % des mutants couverts.

## Fuzzing

`tests/Fuzz/bibtex.php`, `tests/Fuzz/ris.php` et `tests/Fuzz/csl-json.php` donnent aux trois lecteurs des entrées que
personne n'a écrites, à partir des germes de `tests/Fuzz/corpus/<target>` : quoi qu'ils lisent, ils renvoient des
références ou lèvent `InvalidImport` — `InvalidReference` pour CSL-JSON —, rien d'autre.

```sh
composer fuzz -- bibtex ris csl-json --budget=60     # from the monorepo
```
