# ChartJs

A chart of Symfony UX Chart.js in a figure, with its caption and the table of its values.

## Installation

::: installation

## Usage

```php
$chart = $chartBuilder->createChart(Chart::TYPE_LINE);
$chart->setData(['labels' => ['Jan', 'Feb', 'Mar'], 'datasets' => [['label' => 'Visits', 'data' => [12, 30, 21]]]]);
```

```twig
<twig:ChartJs :chart="chart" label="Visits in the first quarter" tableLabel="The values" />
```

## Accessibility

The canvas is hidden from screen readers, which read the table of the same values instead: the labels in its first
column, a column per data set. With `tableLabel`, the table shows to everyone in a disclosure.
