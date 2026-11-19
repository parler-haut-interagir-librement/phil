<!-- phil-dev source-sha256=dc7173982d7695faa15553f63d93b6a8b415b0468ac357f820017fc45fdb72a9 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension ne sont stables qu'à partir de sa version 1.0. D'ici là, une version
mineure peut les changer, et son CHANGELOG explique comment.

| Pour | Faites |
|---|---|
| Garder les habitudes ailleurs | Implémentez `Contract\HabitStore` |
| Garder les validations ailleurs | Implémentez `Contract\CheckInStore` |
| Changer le mouvement de la force | Implémentez `Contract\StrengthModel`, ou donnez une autre demi-vie à `HalfLifeStrength`, et passez-le à `ProgressCalculator` |
| Choisir ce qui est célébré | Passez vos propres `Milestones` à `Habits` |
| Agir après une validation | Écoutez les événements : voir [Événements](events.md) |
| Nommer les tables | Passez les noms à `DbalHabitStore` et `DbalCheckInStore` |

## Les dépôts

`HabitStore::ofMember()` liste par identifiant ; `CheckInStore::between()` liste une plage de jours, bornes comprises,
les plus anciens d'abord. `save()` remplace ce qui est gardé sous la même clé — l'identifiant de l'habitude, l'habitude
et le jour d'une validation. Un dépôt en base de données sert `between()` à partir d'un index sur l'habitude et le jour,
comme le fait `DbalCheckInStore` avec sa clé primaire.

Pour tester un dépôt à vous, étendez le contrat de la bibliothèque comme le fait `InMemoryStoreTest`, si les tests de la
bibliothèque vous sont accessibles, ou copiez ses cas.

## Le modèle de force

`StrengthModel::next($strength, $completion, $days)` reçoit la force avant une période, la part qui en a été faite et le
nombre de jours qu'elle représente, et renvoie la force après elle, de 0 à 1. Il est appelé une fois par période
respectée ou manquée, dans l'ordre ; il ne garde aucun état.
