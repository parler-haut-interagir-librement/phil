<!-- phil-dev source-sha256=b5451fb5498b005f7d087fbbb6bfbbd4e6f0715e60971903e609fb913a4d7f82 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `OpeningHoursType` et ses transformateurs | La soumission, les noms de jours en plusieurs langues, les fuseaux horaires, l'affichage, les heures vides, les champs illisibles |
| `OpeningHoursColumnType` | Le JSON, l'imbrication, les refus |
| `OpeningHoursExtension`, `Intl`, `PhilOpeningHoursBundle` | Ouvert maintenant avec une horloge, les prochaines ouvertures, la semaine et les heures spéciales en français et en anglais, la configuration |

Une suite fonctionnelle démarre un noyau avec FrameworkBundle, DoctrineBundle sur SQLite, TwigBundle et le bundle à
Toronto : `OpeningHoursBundleTest` stocke des heures, produit le rendu du formulaire et du gabarit de tableau en
`fr_CA`. Quand le bundle est installé seul, FrameworkBundle n'étant qu'un prérequis de développement, la suite est
sautée.

```sh
vendor/bin/phpunit packages/opening-hours-bundle/tests
```

## Bancs d'essai

Les bancs d'essai phpbench de `tests/Benchmark/` mesurent `opening_hours_week` sur des listes d'un à deux cents lieux,
en comparaison avec un formateur construit pour chaque texte. Depuis la racine du monodépôt :

```sh
composer bench -- packages/opening-hours-bundle/tests/Benchmark
```
