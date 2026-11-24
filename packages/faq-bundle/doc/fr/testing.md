<!-- phil-dev source-sha256=05cfadd6f7ae0e17134e4ef1932da96a41ddfa3ed8662ee4b7175bd63fd9c835 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau ni réseau, sur SQLite en mémoire. La suite fonctionnelle,
sous `tests/Functional`, démarre une petite application avec les recettes du kit PHIL UX : la page sans JavaScript, le
JSON-LD vérifié par le `Validator` de `phil/structured-data`, la recherche et son décompte, les votes simples et en
JSON, la vérification CSRF, l'administration, les commandes et les chemins du contrôleur pour AssetMapper. Dans le
monodépôt, Infection maintient le paquet à 100 % de mutants couverts.

Les tables sont aussi testées sur de vrais serveurs, lus dans l'environnement et ignorés sans lui :
`PHIL_TEST_MYSQL_URL` pour MySQL 8.4 et `PHIL_TEST_POSTGRESQL_URL` pour PostgreSQL 17.

```sh
vendor/bin/phil-dev services up
eval "$(vendor/bin/phil-dev services env)"
composer test:services
```

## Le contrôleur Stimulus

```sh
node --import ./tests/js/register.mjs --test 'tests/js/**/*.test.mjs'
```

Les tests exécutent le contrôleur et ses modules avec le lanceur de tests de Node, sur une page d'objets simples, avec
Stimulus remplacé par un *stub* : la recherche, le décompte après une pause, la question de l'adresse, les votes, le
stockage du navigateur refusé.

## Dans un navigateur

Ce qu'une personne vérifie avec un lecteur d'écran et un clavier, une fois par version :

- le champ de recherche est annoncé avec son libellé, et le nombre de résultats est lu après la saisie ;
- une question s'ouvre et se ferme avec Entrée et Espace, et son lien l'ouvre depuis une autre page ;
- les boutons de vote sont annoncés dans leur groupe, et les remerciements sont lus une fois le vote envoyé ;
- sans JavaScript, la recherche, le vote et le lien vers le formulaire de contact fonctionnent encore.
