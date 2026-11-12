<!-- phil-dev source-sha256=961109565c92137bf7c61b4849b1b9aa70da4efe7208b9737090c17e2e9b6707 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `StoredDate`, `Definitions`, `ConfiguredDates`, `AlmanacFactory` | Une définition conservée, rendue et modifiée ; les dates stockées qui remplacent les dates configurées ; l'almanach qui voit une date aussitôt, dans son fuseau horaire |
| `DateDraft` | Un brouillon à partir d'une définition, et retour ; chaque refus, sur son champ, et la date entière vérifiée seulement une fois ses champs valides |
| `TagsTransformer`, `AlmanacExtension`, `SkyExtension`, `Places` | Étiquettes lues et écrites ; les dates d'une période dans Twig ; le jour solaire d'un lieu par son nom ou comme `Place`, un texte lu dans son fuseau horaire ; les éclipses d'une période, d'un astre ou des deux, et un astre inconnu refusé ; un lieu inconnu nommé avec ceux qui existent |
| `PhilAlmanacBundle` | Les services avec et sans base de données, les dates configurées construites en définitions, les éclipses déclarées et une date qui en remplace une, les lieux et leur fuseau horaire par défaut, Doctrine trouvé parmi les bundles d'un noyau, chaque configuration refusée |

Une suite fonctionnelle démarre un noyau avec FrameworkBundle, DoctrineBundle sur SQLite, TwigBundle et le bundle : des
dates stockées enregistrées, lues, qui remplacent une date configurée, puis supprimées ; le rendu de `almanac_dates`, éclipses déclarées comprises ;
le rendu de `sun_times` et de `almanac_eclipses` ; le formulaire soumis, refusé en français, et son rendu. Lorsque le bundle est installé seul, FrameworkBundle n'étant
qu'un prérequis de développement, elle est sautée.

```sh
vendor/bin/phpunit packages/almanac-bundle/tests
```
