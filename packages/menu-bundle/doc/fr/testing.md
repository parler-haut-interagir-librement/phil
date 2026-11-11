<!-- phil-dev source-sha256=52a0f4fd1ab963a3cb23d68cee09471e510863795c35a8aa5c0bd3a17053716f -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `MenuItem`, `Menu` | La construction, les refus, les enfants, `map()` avec les enfants en premier, un élément aux enfants inchangés conservé, les enfants renommés vérifiés, le fil d'Ariane |
| `Menus` | Les éléments courants par route, paramètres et chemin, les ancêtres, les décorateurs dans l'ordre et par menu, les noms, les refus |
| `MenuExtension`, `PhilMenuBundle` | La requête lue, les services et la configuration automatique |

Une suite fonctionnelle démarre un noyau avec FrameworkBundle, TwigBundle et le bundle, ainsi qu'un constructeur et un
décorateur trouvés par configuration automatique : `MenuBundleTest` produit le rendu des trois gabarits en français sur
une page du menu et sur une page hors du menu. Lorsque le bundle est installé seul, où FrameworkBundle est un prérequis
de développement, la suite est ignorée.

```sh
vendor/bin/phpunit packages/menu-bundle/tests
```

## Bancs d'essai

Les bancs d'essai phpbench de `tests/Benchmark` mesurent les chemins critiques à plusieurs tailles. Depuis la racine du
monodépôt :

```sh
composer bench -- packages/menu-bundle/tests/Benchmark
```
