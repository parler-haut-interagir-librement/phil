<!-- phil-dev source-sha256=4e9b4e7998d7b62dcfefe8fcaf6aa3764b5d8133d6b7c621e48f4afc62e56adf -->
# Tests

## Les tests du bundle

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

- `tests/Unit` s'exécute sans noyau, sans base de données et sans réseau : les services et la configuration du bundle,
  le type de formulaire et son transformateur, les validateurs, les filtres Twig et le type de colonne sur la
  plateforme SQLite. Dans le monodépôt, Infection maintient le bundle à 100 % des mutants couverts.
- `tests/Functional` démarre un noyau en `fr_CA` avec FrameworkBundle, TwigBundle et DoctrineBundle sur SQLite en
  mémoire : un poids saisi dans un formulaire dans l'unité d'un canal, validé avec des messages français, écrit par
  Twig, et stocké dans sa colonne avec l'unité propre à l'application, puis relu.
- `tests/Integration` écrit des mesures dans une table créée à partir du type de colonne et les relit : sur SQLite en
  mémoire, et sur MySQL et PostgreSQL quand `PHIL_TEST_MYSQL_URL` et `PHIL_TEST_POSTGRESQL_URL` donnent leurs URL, les
  tests étant ignorés sans elles.
