<!-- phil-dev source-sha256=8c9d725f56da51fd85152910eba151add717ada5d26e27444c0ee007ec10407a -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau et sans réseau, sur SQLite en mémoire : le modèle et ses
conditions, chaque type de question, les fins et les redirections, les quotas, l'anonymat des tables, le seuil des
résultats, la purge, les gabarits, les commandes et le module de vie privée. La suite fonctionnelle, sous
`tests/Functional`, démarre une petite application : la page sans JavaScript, ses erreurs, la vérification CSRF, la fin
choisie par la logique, une redirection vers une origine autorisée, les résultats publics et l'addendum. Dans le
monodépôt, Infection maintient le paquet à 100 % des mutants couverts.

La suite d'intégration exécute le stockage et les quotas sur de vrais serveurs, lus dans l'environnement et ignorés sans
lui : `PHIL_TEST_MYSQL_URL` pour MySQL 8.4 et `PHIL_TEST_POSTGRESQL_URL` pour PostgreSQL 17. Des processus simultanés
répondent à un même questionnaire, et le test vérifie qu'aucun quota ne prend plus que sa limite.

```sh
vendor/bin/phil-dev services up
eval "$(vendor/bin/phil-dev services env)"
composer test:services
```

## La cible de fuzzing

`tests/Fuzz/survey-questionnaire-json.php` alimente le lecteur de `phil-survey/1` avec des documents mutés, à partir des
germes de `tests/Fuzz/corpus/` ; il ne doit lancer que `InvalidQuestionnaire`.

```sh
composer fuzz
```

## Le contrôleur Stimulus

```sh
node --test tests/js/steps.test.mjs
```

Les tests couvrent la façon dont le contrôleur passe d'une étape à l'autre, saute les étapes masquées et calcule la
progression.
