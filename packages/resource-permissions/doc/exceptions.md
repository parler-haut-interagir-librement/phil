# Exceptions

Every exception of the library implements `Exception\ResourcePermissionsException`.

| Exception | Extends | Thrown when |
|---|---|---|
| `InvalidRelation` | `InvalidArgumentException` | A subject, a tuple, a model, a budget, a partition or a call is given a value against the rules. |
| `UnknownRelation` | `InvalidArgumentException` | A check, a listing or a write names a kind or a relation the model does not declare. |
| `TraversalBudgetExceeded` | `RuntimeException` | A check, a listing or a tree decision goes past its budget; the caller refuses access. |
| `InvalidTree` | `RuntimeException` | A node of a tree has two parents, or is its own ancestor. |
| `UnscopedTuples` | `RuntimeException` | A store is called outside any partition while its scope requires one; nothing is read or written. |
