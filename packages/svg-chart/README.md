# phil/svg-chart

**Charts drawn as SVG on the server, from the configuration a chart editor already writes.**

phil/svg-chart reads the shape of a Chart.js configuration — the kind of chart, its labels, its series and their
colours, its title — and draws it as an SVG image: grouped bars or lines over a value axis with round ticks, or the
slices of a pie or a doughnut, with a title above and a legend below. Every mark carries its label and value for the
pointer, and the image carries a description that reads every value for screen readers.

It is for any PHP code that must show a chart without a script: a document block drawn once when it is saved, a
PDF, an e-mail, a page read without JavaScript. The configuration is checked against limits, so one sent by a client
costs no more than a large honest one.

It is a plain PHP library built on `ext-mbstring`. Its output is deterministic.

## Status

| Part | State |
|---|---|
| `Chart::fromConfig()`: type, labels, series, colours and title of a Chart.js configuration, with limits | Implemented and tested |
| Bars grouped by label, negative values below the zero line | Implemented and tested |
| Lines through the values, broken where a value is missing | Implemented and tested |
| Pies and doughnuts, from the first series | Implemented and tested |
| Round ticks, labels thinned when they crowd, a legend in rows, Okabe-Ito colours | Implemented and tested |
| `<title>` on every mark, a `<desc>` of every value, `role="img"` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Configuration](doc/configuration.md) | What `Chart::fromConfig()` reads, what it ignores, and its limits. |
| [Drawing](doc/drawing.md) | The image `SvgChartWriter` draws, its options and its accessibility. |
| [Extension points](doc/extension-points.md) | What an application may replace or add, and when it is stable. |
| [Testing](doc/testing.md) | The package's tests and its mutation bar. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/svg-chart
```

```php
use Phil\SvgChart\Chart;
use Phil\SvgChart\SvgChartWriter;

$svg = new SvgChartWriter(640, 400)->write(Chart::fromConfig([
    'type' => 'bar',
    'data' => ['labels' => ['Jan', 'Feb'], 'datasets' => [['label' => 'Sales', 'data' => [12, 19]]]],
    'options' => ['plugins' => ['title' => ['text' => 'Sales']]],
]));
```

## Requirements

PHP 8.5 or later, with `ext-mbstring`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
