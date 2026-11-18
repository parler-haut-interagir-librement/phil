<!-- phil-dev source-sha256=7b8f96a6404efe3f7b7ad5daf492dad1adc4d33cbbdefcc9cd80d5cd77ebdd36 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `ScheduledTransition`, `ResourceReference`, les exceptions | Chaque vérification ; les heures en UTC ; les déplacements, les annulations, les tentatives ; l'association |
| `Scheduler` avec `DoctrineScheduledTransitionRepository` | Sur SQLite en mémoire : planifier et déplacer, les exécutions, les nouvelles tentatives et l'abandon, les annulations, les lots, la plus proche en premier |
| `RunTransitionsCommand`, `TransitionsSchedule`, `RunDueTransitionsHandler` | Les limites ; le message récurrent et son déclencheur |
| `WorkflowTransitionHandler` | La transition d'un workflow appliquée et enregistrée ; une transition non autorisée ; aucun workflow |
| `PhilScheduledTransitionsBundle` | Les services, les réglages, l'association |
| `BinaryKeysListener` | Les clés en `utf8mb4_bin` dans le schéma généré pour MySQL et MariaDB, la collation par défaut sur SQLite, les options qu'un champ a déjà conservées |

La suite `tests/Integration` exécute `BinaryKeysListener` sur un vrai MySQL 8.4 et un vrai PostgreSQL 17 : les
transitions de quatre ressources dont les identifiants ne diffèrent que par la casse ou l'accent sont chacune trouvées
par leur seule ressource, et sur MySQL les clés sont en `utf8mb4_bin` et les tables de la version précédente y arrivent
par le diff du schéma. Le test de chaque serveur lit son URL dans `PHIL_TEST_MYSQL_URL` ou `PHIL_TEST_POSTGRESQL_URL`
et est sauté sans elle :

```sh
PHIL_TEST_MYSQL_URL='mysql://phil:phil@127.0.0.1:13306/phil_test?serverVersion=8.4' \
PHIL_TEST_POSTGRESQL_URL='postgresql://phil:phil@127.0.0.1:15432/phil_test?serverVersion=17' \
vendor/bin/phpunit packages/scheduled-transitions-bundle/tests/Integration
```

Une suite fonctionnelle démarre un noyau avec FrameworkBundle et un workflow `page`, DoctrineBundle sur SQLite et le
bundle : une page est publiée puis retirée à mesure qu'une horloge déplacée atteint chaque moment.

```sh
vendor/bin/phpunit packages/scheduled-transitions-bundle/tests
```

## Bancs d'essai

Les bancs d'essai phpbench de `tests/Benchmark` mesurent les chemins critiques à plusieurs tailles. À la racine du
monodépôt :

```sh
composer bench -- packages/scheduled-transitions-bundle/tests/Benchmark
```
