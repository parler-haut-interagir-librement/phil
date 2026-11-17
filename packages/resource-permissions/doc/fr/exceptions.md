<!-- phil-dev source-sha256=3746c67aa4ac0e5e5a8ca9bb4daab420557a16c421f85be3b3619a7d56945fe8 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Exception\ResourcePermissionsException`.

| Exception | Étend | Levée quand |
|---|---|---|
| `InvalidRelation` | `InvalidArgumentException` | Un sujet, un tuple, un modèle, un budget, une partition ou un appel reçoit une valeur contraire aux règles. |
| `UnknownRelation` | `InvalidArgumentException` | Une vérification, une liste ou une écriture nomme un type ou une relation que le modèle ne déclare pas. |
| `TraversalBudgetExceeded` | `RuntimeException` | Une vérification, une liste ou une décision d'arbre dépasse son budget ; l'appelant refuse l'accès. |
| `InvalidTree` | `RuntimeException` | Un nœud d'un arbre a deux parents, ou est son propre ancêtre. |
| `UnscopedTuples` | `RuntimeException` | Un magasin est appelé hors de toute partition alors que sa portée en exige une ; rien n'est lu ni écrit. |
