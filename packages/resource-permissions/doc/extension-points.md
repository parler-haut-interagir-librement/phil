# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Keep the tuples elsewhere | Implement `Contract\TupleStore`: exact matches, bounded and ordered reads, expiry by its clock, all-or-nothing updates |
| Answer checks another way — a cache, a remote service | Implement `Contract\RelationCheck` and pass it where a check is taken |
| Declare the kinds and relations of the application | Build a `Model` from `TypeDefinition`s; `TypeDefinition::with()` adds relations to a kind declared elsewhere |
| Bound the walks | Pass a `Budget` to `RelationChecker`, a maximum depth to `TreePermissions` |
| Name the parent relation of a tree | Pass it to `TreePermissions` |
| Name the table | Pass it to `DbalTupleStore` |
| Read time | Pass any PSR-20 clock to a store |
