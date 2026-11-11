<!-- phil-dev source-sha256=8333a8193731d7220da62c40a0629b8f594ce46d5c6c4443dc9304d3aaa0a19c -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, vérifie la lecture des configurations et chaque refus, les graduations rondes de
l'échelle, la légende, l'éclaircissement des libellés, les arcs des pointes, et des images entières par rapport à des
fichiers de référence dans `tests/Unit/Fixture`, examinés à leur écriture. Dans le monodépôt, Infection maintient le
paquet à 100 % de mutants couverts.

## Tester du code qui dessine des graphiques

L'image est déterministe : comparez-la avec un fichier de référence, ou cherchez le `<title>` d'une marque.
