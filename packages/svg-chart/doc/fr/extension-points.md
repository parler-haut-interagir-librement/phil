<!-- phil-dev source-sha256=e2ca0c4fb463ff7937de970bc7e8df7d8684dfcd43f51986135b8236da365f4a -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension ne sont stables qu'à partir de sa version 1.0. D'ici là, une version
mineure peut les modifier, et son CHANGELOG explique comment.

| Pour | Faites |
|---|---|
| Lire un autre format de configuration | Construisez un `Chart` à partir de `ChartType`, de libellés et de valeurs `Dataset` |
| Utiliser les couleurs d'une marque | Passez une palette à `SvgChartWriter` |
| Dessiner une autre sorte de graphique | Lisez le `Chart` et écrivez l'image vous-même ; `SvgChartWriter` est final |

Les classes sous `Internal` ne sont pas des points d'extension.
