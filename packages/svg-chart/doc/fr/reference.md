<!-- phil-dev source-sha256=3f41ca05d695a6aa1cb7918d29fa4e33ee42da6da4e2bec0827ec7f5c0c4788a -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `Chart` | Ce que montre un graphique, vérifié par rapport à des limites ; `fromConfig()` lit une configuration Chart.js. |
| `ChartType` | `bar`, `line`, `pie` et `doughnut` ; `hasAxes()`. |
| `Dataset` | Une série : son nom, ses valeurs, sa couleur ou ses couleurs par pointe. |
| `SvgChartWriter` | Dessine un `Chart` dans une image SVG. |
| `Contract\Rasterizer` | Le port des images PNG : `png(string $svg, int $scale)`. |
| `Raster\ResvgRasterizer` | Dessine du SVG en PNG par l'extension `resvg`, nettoyé, avec les polices données, dans des limites. |
| `Exception\SvgChartException` | Implémentée par chaque exception du paquet. |
| `Exception\InvalidChart` | Une configuration ou une option qui ne peut pas être dessinée, avec le chemin de la valeur. |
| `Exception\RasterizerUnavailable` | L'extension `resvg` n'est pas chargée : pas de PNG. |
| `Exception\InvalidRasterizer` | Une police ou une limite avec lesquelles le rasterizer ne peut pas être construit. |
| `Exception\RasterizationFailed` | Une image non dessinée en PNG, avec la raison ; jamais une image partielle. |
