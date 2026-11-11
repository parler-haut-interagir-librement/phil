<!-- phil-dev source-sha256=9049c49d6162d6838b4ac704d00d1b33a8f24c0ea80e309bb7e8b30b2ebfc35e -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `RuleDescription` | Chaque partie en anglais et en français : pluriels, rangs, jours du mois comptés depuis la fin, mois, positions, fins |
| `RecurrenceRuleType`, `RecurrenceRuleTransformer` | Chaque fréquence soumise, règles impossibles, affichage, cibles des rangées, noms dans la langue |
| `RecurrenceRuleColumnType`, `RecurrenceExtension`, `PhilRecurrenceBundle` | Stockage, fonctions Twig, services |
| Le contrôleur Stimulus | Rangées montrées et champs désactivés, avec l'exécuteur de tests de Node |

Une suite fonctionnelle démarre un noyau avec FrameworkBundle, DoctrineBundle sur SQLite, TwigBundle et le bundle :
`RecurrenceBundleTest` enregistre une règle, produit le rendu du formulaire en `fr_CA` et écrit une règle en mots.
Quand le bundle est installé seul, FrameworkBundle n'étant qu'un prérequis de développement, la suite est sautée.

```sh
vendor/bin/phpunit packages/recurrence-bundle/tests
node --import ./tests/js/register.mjs --test tests/js   # from packages/recurrence-bundle
```

## Bancs d'essai

Les bancs d'essai phpbench de `tests/Benchmark` mesurent les chemins critiques à plusieurs tailles. À partir de la
racine du monodépôt :

```sh
composer bench -- packages/recurrence-bundle/tests/Benchmark
```
