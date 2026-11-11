<!-- phil-dev source-sha256=3df9de61bec232f88a1577b914be05d470001fc972ffff22c94dd896235896c7 -->
# Occurrences

```php
$recurrence = Recurrence::of($start, $rule, exclusions: [$cancelled], additions: [$extra]);
```

| Méthode | Donne |
|---|---|
| `occurrences()` | Chaque occurrence, dans l'ordre, sous forme de générateur |
| `between($from, $through, $limit = 1000)` | Celles qui commencent d'un moment jusqu'à un autre, au plus tant |
| `next($after)` | La première qui commence après un moment, ou `null` quand la règle est terminée |

**La première occurrence en est toujours une**, et elle compte dans `COUNT`. Les occurrences gardent son heure murale
dans son fuseau horaire : une réunion hebdomadaire à 19 h à Toronto reste à 19 h d'un changement d'heure à l'autre. Une
heure qu'un changement saute, comme 2 h 30 la nuit où l'on avance l'heure, devient l'heure qui suit, comme le dit la
RFC 5545.

**Les fins** : une heure de fin est le dernier moment où une occurrence peut commencer ; un jour de fin garde toutes
ses occurrences, dans le fuseau horaire de la première occurrence.

**Les exceptions**, `EXDATE`, sont retirées des occurrences et comptent dans `COUNT`, comme le dit la RFC 5545.

**Les dates ajoutées**, `RDATE`, sont fusionnées aux occurrences dans l'ordre, dans le fuseau horaire de la première
occurrence, à l'instant qu'elles nomment. Une date que la règle donne aussi n'arrive qu'une fois. Elles ne comptent ni
dans `COUNT` ni dans `UNTIL` : une règle de trois occurrences avec deux dates ajoutées en donne cinq, et une date ajoutée
après la fin de la règle en reste une. Une exception retire une date ajoutée comme toute occurrence. `between()` et
`next()` les trouvent dans leur fenêtre ; les dates ajoutées après la dernière occurrence de la règle la suivent.
[Dates ajoutées et retirées](dates.md) lit et écrit leurs lignes.

**Les jours qui n'existent pas** sont sautés : le 31 d'un mois de 30 jours, le 29 février d'une année ordinaire. Une
règle qui ne correspond à aucun jour, comme le 30 février, s'arrête après `Recurrence::MAX_PERIODS` périodes de suite
sans jour. Une règle qui continue de trouver des jours va jusqu'à sa propre fin, ou jusqu'à la fin de l'an 9999, la
dernière année qu'on écrit avec quatre chiffres.

**Une fenêtre coûte ce qu'elle contient** : sans `COUNT`, `between()` et `next()` commencent à la période où tombe le
moment, atteinte par calcul depuis la première occurrence, et `between()` s'arrête à la période de son dernier moment.
Un mois d'une règle coûte autant dans sa première année que dans sa cinquantième. Une règle avec `COUNT` est parcourue
depuis sa première occurrence, puisque les occurrences avant la fenêtre comptent : la dernière occurrence dépend de
chaque période qui la précède. Une période est disposée avec ses jours sous forme de nombres, et une date n'est
construite que pour un jour que la règle garde : parcourir une année d'une règle annuelle, ou un long décompte, coûte
les occurrences qu'elle contient, pas une date pour chaque jour de l'année.

**Un appel coûte au plus `Recurrence::MAX_WALKED` périodes**, 50 000, quoi qu'il trouve : `limit` plafonne les
occurrences que rend `between()`, et cette constante plafonne le travail de `between()` et de `next()`. Une règle qui
ne tombe qu'un jour en plusieurs années, comme le 29 février d'une règle quotidienne, donne les occurrences que ces
périodes contiennent, et `next()` donne `null` au-delà. Une règle avec `COUNT`, parcourue depuis sa première
occurrence, abandonne après autant de périodes. Une règle tirée d'une importation ou d'une requête coûte donc un temps
borné. `occurrences()` parcourt aussi loin que le demande son appelant.
