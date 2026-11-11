<!-- phil-dev source-sha256=2f8e20be049e4c5ae501719a93c679d57de70ea2223e309913ef3aebea1146dc -->
# Exceptions

Chaque exception de la bibliothèque implémente `Phil\Poll\Exception\PollException`.

| Exception | Étend | Lancée quand |
|---|---|---|
| `InvalidPoll` | `InvalidArgumentException` | Des règles de moins de 2 ou de plus de 100 options, une option hors de son alphabet ou déclarée deux fois, des bornes hors de 1 ≤ min ≤ max ≤ options, un bulletin unique avec d'autres bornes ; une méthode à qui l'on demande de dépouiller une sorte de bulletin qu'elle ne dépouille pas ; une méthode de dépouillement mal nommée ou déclarée deux fois. |
| `InvalidBallot` | `InvalidArgumentException` | Des choix qui ne sont pas une liste, un choix qui n'est pas l'une des options, un choix donné deux fois, trop peu ou trop de choix, un bulletin blanc que les règles refusent. |
| `UnknownCountingMethod` | `RuntimeException` | On demande à `CountingMethods::get()` ou à `count()` un nom auquel aucune méthode ne répond. |

`InvalidBallot` est l'erreur de l'électeur, ou une requête trafiquée : son message nomme le choix et dit ce qui ne va
pas, et peut être montré sans risque. `InvalidPoll` est une erreur de l'application, qu'il vaut mieux attraper là où un
scrutin est créé. `UnknownCountingMethod` signifie qu'un scrutin stocké nomme une méthode que l'application ne déclare
plus.
