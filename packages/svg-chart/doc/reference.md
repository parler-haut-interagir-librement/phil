# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `Chart` | What a chart shows, checked against limits; `fromConfig()` reads a Chart.js configuration. |
| `ChartType` | `bar`, `line`, `pie` and `doughnut`; `hasAxes()`. |
| `Dataset` | One series: its name, its values, its colour or its colours per slice. |
| `SvgChartWriter` | Draws a `Chart` as an SVG image. |
| `Contract\Rasterizer` | The port of the PNG images: `png(string $svg, int $scale)`. |
| `Raster\ResvgRasterizer` | Draws SVG as PNG through the `resvg` extension, sanitized, with the fonts given, within limits. |
| `Exception\SvgChartException` | Implemented by every exception of the package. |
| `Exception\InvalidChart` | A configuration or an option that cannot be drawn, with the path of the value. |
| `Exception\RasterizerUnavailable` | The `resvg` extension is not loaded: no PNG. |
| `Exception\InvalidRasterizer` | A font or a limit the rasterizer cannot be built with. |
| `Exception\RasterizationFailed` | An image not drawn as a PNG, with the reason; never a partial image. |
