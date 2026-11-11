<!-- phil-dev source-sha256=f06740193170d8f0f5b4f98c212143c8622e7b2ca26f9568577d49ce892cd406 -->
# Points d'extension

La bibliothèque est du PHP simple : elle ne déclare ni service ni port. Ce que choisit une application passe par des
arguments.

| Point | Comment |
|---|---|
| Les axes | N'importe quels axes, jusqu'à 8 : une taille et une couleur, un format et un papier, une version |
| L'UGS | N'importe quel patron de littéraux et de marqueurs, dont `{design}` |
| Les prix | Une base, un prix par valeur d'un axe, des suppléments sur n'importe quel axe |
| Les canaux | Les canaux par lesquels chaque valeur est vendue, tels que les nomme le catalogue de l'application |
| Les images | Des chemins relatifs au stockage d'images de l'application : photos, pastilles, illustrations |
| La limite d'un plan | `new VariantPlanner($maxVariants)`, au plus `VariantPlanner::MAX_VARIANTS` |
| Le stockage | `TemplateArray` et `DesignArray`, ou la propre correspondance de l'application pour les membres publics |

Ce que devient un plan — produits d'un catalogue, maquettes, options d'une ligne — appartient à l'application, ou au
bundle.
