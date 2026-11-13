# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `Phil\ResourcePermissions\Subject` | Who a relation is given to: one object, every object of a kind, or a subject set. |
| `Phil\ResourcePermissions\RelationTuple` | The stored fact: a subject holds a relation on an object, with an optional expiry. |
| `Phil\ResourcePermissions\RelationName` | The rule of a relation's name. |
| `Phil\ResourcePermissions\RelationChecker` | `check()`, `explain()` and `objects()` from a model, a store and a budget. |
| `Phil\ResourcePermissions\RelationWriter` | Writes and deletes tuples the model accepts. |
| `Phil\ResourcePermissions\Budget` | The limits of one check or listing. |
| `Phil\ResourcePermissions\Explanation` | The tuples that give a subject a relation. |
| `Phil\ResourcePermissions\ObjectList` | The objects a subject holds a relation on, and whether the list is complete. |
| `Phil\ResourcePermissions\InMemoryTupleStore` | The tuples in an array. |
| `Phil\ResourcePermissions\Contract\TupleStore` | Where the tuples are kept. |
| `Phil\ResourcePermissions\Contract\RelationCheck` | The questions the model answers. |
| `Phil\ResourcePermissions\Model\Model` | The kinds of objects and their relations, checked when built. |
| `Phil\ResourcePermissions\Model\TypeDefinition` | A kind and its relations. |
| `Phil\ResourcePermissions\Model\Rewrite` | How a relation is computed. |
| `Phil\ResourcePermissions\Model\RewriteKind` | Direct, computed, from related, union, intersection, exclusion. |
| `Phil\ResourcePermissions\Model\SubjectType` | A kind of subject a direct relation accepts. |
| `Phil\ResourcePermissions\Tree\TreePermissions` | Entries per node and action, inherited down a tree, the most specific winning. |
| `Phil\ResourcePermissions\Tree\Grantee` | A role, or everyone else. |
| `Phil\ResourcePermissions\Tree\Decision` | Allow, deny or undecided. |
| `Phil\ResourcePermissions\Tree\TreeDecision` | A decision, the node that gave it and its grantees. |
| `Phil\ResourcePermissions\Tree\TreeEntry` | One entry of a node. |
| `Phil\ResourcePermissions\Dbal\DbalTupleStore` | The tuples in a table of a Doctrine DBAL connection. |
| `Phil\ResourcePermissions\Dbal\TupleSchemaListener` | Adds the table to the schema the ORM generates. |
| `Phil\ResourcePermissions\Exception\ResourcePermissionsException` | Every exception of the library. |
| `Phil\ResourcePermissions\Exception\InvalidRelation` | A value against the rules. |
| `Phil\ResourcePermissions\Exception\UnknownRelation` | A kind or relation the model does not declare. |
| `Phil\ResourcePermissions\Exception\TraversalBudgetExceeded` | A walk past its budget. |
| `Phil\ResourcePermissions\Exception\InvalidTree` | A node with two parents, or its own ancestor. |
