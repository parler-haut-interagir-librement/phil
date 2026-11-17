<!-- phil-dev source-sha256=833c2399a7b22c55f719c41d2f9cd425d25263a597835b461bd80e317c693bdf -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Partitionner les tuples par locataire | Implémentez `Contract\TupleScope` et passez-la au magasin ; lancez `UnscopedTuples` hors de tout locataire |
| Conserver les tuples ailleurs | Implémentez `Contract\TupleStore` : correspondances exactes, lectures bornées et ordonnées, expiration selon son horloge, mises à jour d'un seul coup |
| Répondre aux vérifications autrement — un cache, un service distant | Implémentez `Contract\RelationCheck` et passez-le là où une vérification est reçue |
| Déclarer les types et les relations de l'application | Construisez un `Model` à partir de `TypeDefinition` ; `TypeDefinition::with()` ajoute des relations à un type déclaré ailleurs |
| Borner les parcours | Passez un `Budget` à `RelationChecker`, une profondeur maximale à `TreePermissions` |
| Nommer la relation parent d'un arbre | Passez-la à `TreePermissions` |
| Nommer la table | Passez-la à `DbalTupleStore` |
| Lire l'heure | Passez n'importe quelle horloge PSR-20 à un magasin |
