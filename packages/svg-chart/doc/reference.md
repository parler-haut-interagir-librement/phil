# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `Chart` | What a chart shows, checked against limits; `fromConfig()` reads a Chart.js configuration. |
| `ChartType` | `bar`, `line`, `pie` and `doughnut`; `hasAxes()`. |
| `Dataset` | One series: its name, its values, its colour or its colours per slice. |
| `SvgChartWriter` | Draws a `Chart` as an SVG image. |
| `Exception\SvgChartException` | Implemented by every exception of the package. |
| `Exception\InvalidChart` | A configuration or an option that cannot be drawn, with the path of the value. |
