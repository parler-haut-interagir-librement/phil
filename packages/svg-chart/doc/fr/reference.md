<!-- phil-dev source-sha256=fc36cc4007e5907b7f86ef218b3f89e720cd540b86929f67a4d176c59e214128 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `Chart` | Ce que montre un graphique, vérifié par rapport à des limites ; `fromConfig()` lit une configuration Chart.js. |
| `ChartType` | `bar`, `line`, `pie` et `doughnut` ; `hasAxes()`. |
| `Dataset` | Une série : son nom, ses valeurs, sa couleur ou ses couleurs par pointe. |
| `SvgChartWriter` | Dessine un `Chart` dans une image SVG. |
| `Exception\SvgChartException` | Implémentée par chaque exception du paquet. |
| `Exception\InvalidChart` | Une configuration ou une option qui ne peut pas être dessinée, avec le chemin de la valeur. |
