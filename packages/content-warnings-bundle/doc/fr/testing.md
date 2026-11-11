<!-- phil-dev source-sha256=53d14c7553a9d2776e934d160ed8fe8a056ee4ed7d2d7fb9610d20c99462dcab -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `CatalogueFactory`, `CatalogueLabels`, `WarningScreen` | La liste de base, les fichiers et la définition dans l'ordre, les libellés par locale et par repli, les choix, les décisions |
| `SessionWarningPreferences`, `InMemoryWarningPreferences` | L'enregistrement et la lecture, la requête principale, l'absence de session, les préférences illisibles, la réinitialisation |
| `ContentWarningsColumnType` | Le JSON, null et vide, chaque refus |
| `ContentWarningsType`, `WarningPreferencesType` et leurs transformateurs | Les choix par catégorie et par locale, les sévérités, les notes et les thèmes non énumérés conservés, les options |
| `ContentWarningsExtension`, `PhilContentWarningsBundle` | Les fonctions ; les services, la configuration, un catalogue brisé, la configuration ajoutée au préalable |
| Le contrôleur Stimulus | Les événements, la révélation avec et sans réduction des animations, le repli, avec l'exécuteur de tests de Node |

Une suite fonctionnelle démarre un noyau avec FrameworkBundle, DoctrineBundle sur SQLite, TwigBundle et ce bundle :
`ContentWarningsBundleTest` stocke des avertissements, produit le rendu du gabarit replié, masqué et affiché, et celui
des formulaires en `fr_CA`. Lorsque le bundle est installé seul, où FrameworkBundle est un prérequis de développement,
la suite est ignorée.

```sh
vendor/bin/phpunit packages/content-warnings-bundle/tests
node --import ./tests/js/register.mjs --test tests/js   # from packages/content-warnings-bundle
```
