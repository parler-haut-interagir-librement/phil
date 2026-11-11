<!-- phil-dev source-sha256=cdfea86271a16bcdba2dc9280ceefbdf14913a2e6ad7cf66d32a318d5d9c5d65 -->
# Formulaires

`RecurrenceRuleType`, vers un `RecurrenceRule` et à partir de celui-ci :

| Champ | Visible pour | Devient |
|---|---|---|
| `frequency` | toujours | `FREQ` ; sans fréquence, pas de règle |
| `interval` | chaque fréquence | `INTERVAL` |
| `weekdays` | quotidienne, hebdomadaire | `BYDAY`, sans rang : *chaque jour de semaine* est quotidien du lundi au vendredi |
| `month_day` | mensuelle, annuelle | `BYMONTHDAY`, compté depuis la fin s'il est négatif |
| `month_weekday`, `weekday` | mensuelle, annuelle | `BYDAY` avec son rang — le deuxième mardi, le dernier vendredi —, qui remplace le jour du mois |
| `months` | annuelle | `BYMONTH` |
| `end`, `count`, `until` | toujours ; `count` et `until` pour leur fin | `COUNT` ou `UNTIL`, un jour |

ICU nomme les jours et les mois dans la langue de la requête ; les libellés sont dans le domaine `phil_recurrence`. Une
règle impossible, comme un intervalle de 0, un intervalle ou un décompte au-dessus de 9999 ou une fin sans sa valeur,
rend le formulaire invalide ; les champs `interval` et `count` portent `min` 1 et `max` 9999. Une règle qui dépasse
ce que contiennent les champs — positions dans l'ensemble, plusieurs jours du mois, un jour de la semaine avec un jour
du mois (*vendredi 13*), un début de semaine, une heure de fin — montre ce que les champs peuvent en contenir : le
formulaire sert aux règles que les gens fixent, la bibliothèque les lit toutes.

Un formulaire soumis sans changement ne réécrit jamais une telle règle. Le transformateur se souvient de la règle qu'il
a montrée et de ce que voulaient dire ses champs. Quand les champs reviennent avec le même sens, le formulaire rend la
règle qu'il a reçue, entière. Dès qu'un champ change, le formulaire donne la règle que disent les champs, et ce qu'ils
ne pouvaient pas contenir disparaît : un rédacteur qui change l'horaire d'une règle importée la fixe de nouveau. Un
transformateur sert un seul formulaire, tel que le construit `RecurrenceRuleType`.

## Le contrôleur Stimulus

Le formulaire porte `phil--recurrence-bundle--recurrence` ; chaque rangée indique, dans `data-show`, les fréquences ou
les fins qui la concernent. Quand la page se connecte, et à chaque changement, le contrôleur montre les rangées de la
fréquence et de la fin choisies et désactive les champs des autres, pour qu'ils ne soient pas envoyés.
