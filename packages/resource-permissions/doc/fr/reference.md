<!-- phil-dev source-sha256=fee83edc0daeb5c79f6afb3e66a7adb78111e859ed9bb4d1a99f3bcefd9185f5 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `Phil\ResourcePermissions\Subject` | Celui à qui une relation est donnée : un objet, tous les objets d'un type, ou un ensemble de sujets. |
| `Phil\ResourcePermissions\RelationTuple` | Le fait stocké : un sujet détient une relation sur un objet, avec une expiration facultative. |
| `Phil\ResourcePermissions\RelationName` | La règle du nom d'une relation. |
| `Phil\ResourcePermissions\RelationChecker` | `check()`, `explain()` et `objects()` à partir d'un modèle, d'un magasin et d'un budget. |
| `Phil\ResourcePermissions\RelationWriter` | Écrit et supprime les tuples que le modèle accepte. |
| `Phil\ResourcePermissions\Budget` | Les limites d'une vérification ou d'une liste. |
| `Phil\ResourcePermissions\Explanation` | Les tuples qui donnent une relation à un sujet. |
| `Phil\ResourcePermissions\ObjectList` | Les objets sur lesquels un sujet détient une relation, et si la liste est complète. |
| `Phil\ResourcePermissions\InMemoryTupleStore` | Les tuples dans un tableau. |
| `Phil\ResourcePermissions\Partition` | La règle du nom d'une partition, et la partition par défaut. |
| `Phil\ResourcePermissions\SinglePartition` | Chaque tuple dans la partition par défaut. |
| `Phil\ResourcePermissions\FixedPartition` | Chaque tuple dans une seule partition nommée. |
| `Phil\ResourcePermissions\Contract\TupleStore` | L'endroit où les tuples sont conservés. |
| `Phil\ResourcePermissions\Contract\TupleScope` | La partition que l'unité de travail en cours lit et écrit. |
| `Phil\ResourcePermissions\Contract\RelationCheck` | Les questions auxquelles le modèle répond. |
| `Phil\ResourcePermissions\Model\Model` | Les types d'objets et leurs relations, vérifiés à la construction. |
| `Phil\ResourcePermissions\Model\TypeDefinition` | Un type et ses relations. |
| `Phil\ResourcePermissions\Model\Rewrite` | La façon dont une relation est calculée. |
| `Phil\ResourcePermissions\Model\RewriteKind` | Directe, calculée, issue d'un objet lié, union, intersection, exclusion. |
| `Phil\ResourcePermissions\Model\SubjectType` | Un type de sujet qu'une relation directe accepte. |
| `Phil\ResourcePermissions\Tree\TreePermissions` | Entrées par nœud et par action, héritées le long d'un arbre, la plus précise l'emporte. |
| `Phil\ResourcePermissions\Tree\Grantee` | Un rôle, ou tous les autres. |
| `Phil\ResourcePermissions\Tree\Decision` | Permission, refus ou indécision. |
| `Phil\ResourcePermissions\Tree\TreeDecision` | Une décision, le nœud qui l'a donnée et ses bénéficiaires. |
| `Phil\ResourcePermissions\Tree\TreeEntry` | Une entrée d'un nœud. |
| `Phil\ResourcePermissions\Dbal\DbalTupleStore` | Les tuples dans une table d'une connexion Doctrine DBAL. |
| `Phil\ResourcePermissions\Dbal\TupleSchemaListener` | Ajoute la table au schéma que l'ORM génère. |
| `Phil\ResourcePermissions\Exception\ResourcePermissionsException` | Chaque exception de la bibliothèque. |
| `Phil\ResourcePermissions\Exception\InvalidRelation` | Une valeur contraire aux règles. |
| `Phil\ResourcePermissions\Exception\UnknownRelation` | Un type ou une relation que le modèle ne déclare pas. |
| `Phil\ResourcePermissions\Exception\TraversalBudgetExceeded` | Un parcours qui dépasse son budget. |
| `Phil\ResourcePermissions\Exception\InvalidTree` | Un nœud à deux parents, ou son propre ancêtre. |
| `Phil\ResourcePermissions\Exception\UnscopedTuples` | Un magasin appelé hors de toute partition alors qu'une est exigée. |
