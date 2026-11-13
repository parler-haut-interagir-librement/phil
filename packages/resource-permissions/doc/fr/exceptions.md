<!-- phil-dev source-sha256=cdeb71dfe96d8fd0ba206dd9fb222762da21912447811f5024be7fa30263bac8 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Exception\ResourcePermissionsException`.

| Exception | Étend | Levée quand |
|---|---|---|
| `InvalidRelation` | `InvalidArgumentException` | Un sujet, un tuple, un modèle, un budget ou un appel reçoit une valeur contraire aux règles. |
| `UnknownRelation` | `InvalidArgumentException` | Une vérification, une liste ou une écriture nomme un type ou une relation que le modèle ne déclare pas. |
| `TraversalBudgetExceeded` | `RuntimeException` | Une vérification, une liste ou une décision d'arbre dépasse son budget ; l'appelant refuse l'accès. |
| `InvalidTree` | `RuntimeException` | Un nœud d'un arbre a deux parents, ou est son propre ancêtre. |
