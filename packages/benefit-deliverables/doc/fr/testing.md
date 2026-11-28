<!-- phil-dev source-sha256=4135c9e32969bae9d0a859193bdd2e54223fc88ee7661c4b4c1758a9af3b0fdc -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau : les ports sont
implémentés en mémoire sous `tests/Support`, et `LocalExportFileStore` écrit dans un répertoire temporaire. Dans le
monodépôt, Infection maintient le paquet à 100 % des mutants couverts.

## La cible de fuzzing

`tests/Fuzz/benefit-deliverables-token.php` lit des jetons de téléchargement que personne n'a signés : chaque entrée ne
donne l'identifiant d'un export que si c'est le jeton signé pour lui, jamais une exception. Dans le monodépôt :

```sh
composer fuzz -- benefit-deliverables-token
```

Ses graines se trouvent sous `tests/Fuzz/corpus/benefit-deliverables-token`.
