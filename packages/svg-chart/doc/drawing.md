# Drawing

`SvgChartWriter` draws a `Chart` as a standalone SVG image.

```php
new SvgChartWriter(int $width = 640, int $height = 400, array $palette = SvgChartWriter::PALETTE, string $font = 'system-ui, sans-serif')->write(Chart $chart): string
```

| Option | Range |
|---|---|
| `$width`, `$height` | 100 to 4 000 pixels, the `viewBox` of the image |
| `$palette` | one or more `#rrggbb` colours, taken in turn by the series or the slices |
| `$font` | font families, by letters, digits, spaces, commas and dashes |

## What is drawn

- **Bars**: one group per label, one bar per series, from the zero line; the axis always holds zero.
- **Lines**: one path per series through the centre of each label, broken where a value is `null`, a dot per value.
- **Pies and doughnuts**: the first series, one slice per label clockwise from the top; a negative or missing value
  counts as nothing, and a chart whose values sum to nothing is an empty ring.

The value axis has about five round ticks — 1, 2 or 5 times a power of ten —, and labels too wide for their band are
shown one in two, or fewer. The legend lists the series of a chart with axes when there are several or a named one,
and the labels of a pie; its entries wrap in rows. Text widths are estimated, at 0.6 em a character.

The default palette is the Okabe-Ito set, told apart by every common colour vision.

## Accessibility

The image carries `role="img"`, the chart's title as its `<title>` (or its kind, "Bar chart"), and a `<desc>` that
reads every value: "Bar chart. Sales: Jan 12, Feb 19." Each bar, dot and slice has a `<title>` with its series, label
and value — and its share for a slice —, which browsers show on hover.

## Safety

Every text is escaped for XML, colours and font are checked, and nothing but SVG shapes and text is written: the
image is safe to write inline in a page.
