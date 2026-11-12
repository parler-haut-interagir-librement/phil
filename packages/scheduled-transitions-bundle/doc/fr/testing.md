<!-- phil-dev source-sha256=2994d0ad5edb2f8c31eee98c15acfe24a5ca8cac6a2c38f3a235bd84369cc10b -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `ScheduledTransition`, `ResourceReference`, les exceptions | Chaque vérification ; les heures en UTC ; les déplacements, les annulations, les tentatives ; l'association |
| `Scheduler` avec `DoctrineScheduledTransitionRepository` | Sur SQLite en mémoire : planifier et déplacer, les exécutions, les nouvelles tentatives et l'abandon, les annulations, les lots, la plus proche en premier |
| `RunTransitionsCommand`, `TransitionsSchedule`, `RunDueTransitionsHandler` | Les limites ; le message récurrent et son déclencheur |
| `WorkflowTransitionHandler` | La transition d'un workflow appliquée et enregistrée ; une transition non autorisée ; aucun workflow |
| `PhilScheduledTransitionsBundle` | Les services, les réglages, l'association |

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
