# Chart

A chart drawn by the server in SVG, bars or lines, with the table of its values.

```twig {"preview":true}
<twig:Chart label="Visits" :labels="['Jan', 'Feb', 'Mar']" :series="[{label: 'Visits', values: [12, 30, 21]}]" />
```

## Installation

::: installation

## Usage

```twig
<twig:Chart
    type="line"
    label="Visits and sign-ups in the first quarter"
    :labels="['Jan', 'Feb', 'Mar']"
    :series="[{label: 'Visits', values: [12, 30, 21]}, {label: 'Sign-ups', values: [2, 5, null]}]"
    tableLabel="The values"
/>
```

## Accessibility

The drawing is hidden from screen readers, which read the table of the same values instead. With several series, the legend writes each name beside its swatch and the lines differ by their dashes, so the colour never stands alone. With `tableLabel`, the table shows to everyone in a disclosure.
