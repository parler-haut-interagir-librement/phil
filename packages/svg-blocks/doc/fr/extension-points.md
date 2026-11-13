<!-- phil-dev source-sha256=2e65c4a359536e3ee620b48f9923aabd81df629f4bca10b4cdd71bb28ab697e9 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension ne sont stables qu'à partir de sa version 1.0. D'ici là, une version
mineure peut les modifier, et son CHANGELOG explique comment.

| Pour | Faites |
|---|---|
| Dessiner un autre type de bloc, ou en dessiner un autrement | Implémentez `Contract\MarkupRenderer` et donnez-le à `BlockMarkup` sous le type |
| Appeler un autre service de diagrammes | Implémentez `Contract\MermaidService` |
| Dessiner un type seulement en arrière-plan | Nommez-le dans le `$deferred` de `BlockMarkup` |
| Dimensionner les graphiques, les codes QR ou les formules | Donnez aux moteurs de rendu un `SvgChartWriter`, un `SvgWriter` et un niveau configurés, ou `LatexToMathMl` |

Un moteur de rendu doit retourner un balisage sûr à écrire dans une page : le dessin est signé, puis écrit sans être
assaini de nouveau.
