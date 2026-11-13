<!-- phil-dev source-sha256=8f125680935315114cf5c30558b0237a18481a989b6f3621f9886ec4634cd98a -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau. Elle épelle des
cardinaux, des ordinaux et des montants dans chacune des six langues et dans une langue sans règles, vérifie chaque
correction du français dans les deux orthographes, et chaque refus : une locale, un genre, un pluriel, une forme
détaillée, une devise, un montant et un nombre hors limites. Les mots attendus sont ceux que donnent à l'identique
ICU 76 et les versions suivantes. Dans le monodépôt, Infection maintient le paquet à 100 % des mutants couverts.
