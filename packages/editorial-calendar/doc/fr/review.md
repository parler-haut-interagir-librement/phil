<!-- phil-dev source-sha256=75b8dea815ac6ea44a197438db56251eff6074bc0b23e9065d6fcda7c3b5009d -->
# Revue

`Review::issues($calendar, $from, $to)` liste les conflits et les périodes vides d'un calendrier de `$from`, inclus, à
`$to`, exclu. Chaque `Issue` contient son genre, les moments UTC qu'elle couvre et les identifiants des éléments
qu'elle concerne.

| `IssueKind` | Signalé quand | `from` et `to` |
|---|---|---|
| `DuplicateContent` | Le même contenu dans la même langue est prévu plus d'une fois | Le premier et le dernier moment |
| `NotReady` | Un élément est prévu pour un moment alors qu'il est encore une idée ou un brouillon | Son moment |
| `TooClose` | Deux publications sont plus rapprochées que l'espacement minimal | Les deux moments |
| `Gap` | Une période plus longue que le maximum permis n'a aucune publication | La période |

Les problèmes viennent dans cet ordre de genres, chaque genre dans l'ordre de ses moments. Un lot compte comme une
seule publication à son moment, ses éléments étant chacun comptés pour le contenu répété et l'état de préparation ; les
éléments retirés sont écartés ; les transitions prévues ailleurs ne sont pas des publications.

## Les règles

`ReviewRules` règle ce que la revue cherche en plus du contenu répété et des éléments non prêts :

```php
new Review(new ReviewRules(minimumSpacing: 3_600, maximumGap: 172_800));
```

| Règle | Valeur par défaut | Effet |
|---|---|---|
| `minimumSpacing` | 0 | Les secondes voulues au moins entre deux publications ; 0 ignore l'espacement |
| `maximumGap` | `null` | Les secondes sans publication au-delà desquelles une période vide est signalée, du début de l'intervalle à la première publication, entre les publications, et de la dernière à la fin ; `null` n'en signale aucune |

Un espacement négatif, ou une période inférieure à une seconde, lance `InvalidItem`.
