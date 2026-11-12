<!-- phil-dev source-sha256=4824593d3caac53cd7021cb9c684266cc684746d9672ec96d2c1cb4cdd7800ee -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Garder les listes ailleurs — une table, un service distant | Implémentez `Application\Contract\SynonymStore` — voir [Magasins](stores.md) |
| Lire ou écrire un autre format | Construisez `SynonymSet` et `SynonymList` à partir de lui ; leurs fabriques vérifient chaque limite |
| Vérifier une liste avant de l'enregistrer | Appelez `SynonymValidator` et montrez ses `Violation` |
| Chercher avec les synonymes | Donnez un magasin à `phil/search-bundle`, `synonyms.directory` ou `synonyms.store` |
