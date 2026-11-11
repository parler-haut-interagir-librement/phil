<!-- phil-dev source-sha256=abec03fa7b7f9a969dd667354e257082b872ba2cd378ebd92f38122c932e5718 -->
# Points d'extension

La bibliothèque est du PHP simple : elle ne déclare aucun service ni aucun port, et ses classes sont finales. Ce
qu'une application choisit passe par des arguments.

| Point | Comment |
|---|---|
| Le fuseau horaire d'un lieu | Le `$timezone` de `OpeningHours::of()` : chaque question — ouvert maintenant, prochaine ouverture — y trouve sa réponse, quel que soit le fuseau du moment demandé |
| Jours fériés et fermetures | Les `$specials` de `OpeningHours::of()` : des `SpecialHours` pour un jour ou une période, calculées par l'application à partir de son propre calendrier |
| Où les heures sont gardées | `OpeningHoursArray::from()` donne un tableau pour une seule colonne JSON, `OpeningHoursArray::to()` le relit |
| Ce que les gens saisissent | `HoursText` lit et écrit la forme texte ; un formulaire d'une autre forme construit lui-même `DayHours` et `TimeRange` |
| Données structurées | `OpeningHoursSchema::of()` donne l'`openingHoursSpecification` de schema.org ; l'application la met dans son JSON-LD |
| Attraper les erreurs de la bibliothèque | Chaque exception implémente `Phil\OpeningHours\Exception\OpeningHoursException` |

Les noms des jours et la mise en page d'un tableau d'heures relèvent de l'application : `groups()` donne les suites de
jours qui ont les mêmes heures, et l'application les écrit dans sa langue.
