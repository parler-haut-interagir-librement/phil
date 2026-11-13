<!-- phil-dev source-sha256=b161ba65d0a5b184012e0493a547273a02aad1807823edaf17cd66023d0cc5db -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension ne sont stables qu'à partir de sa version 1.0. D'ici là, une version
mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Accepter des formules plus longues ou plus profondes, ou plus de macros | Passez `$maxLength`, `$maxDepth` et `$maxExpansions` à `LatexToMathMl` |
| Montrer la raison d'un refus là où il s'est produit | Lisez `InvalidLatex::$offset` |

L'ensemble des commandes ne s'étend pas de l'extérieur : une commande s'ajoute dans le paquet, avec ses tests. Une formule
peut définir ses propres macros avec `\newcommand` ; une application qui en veut de communes place leurs définitions
devant les formules qu'elle convertit. Les classes sous `Internal` ne sont pas des points d'extension.
