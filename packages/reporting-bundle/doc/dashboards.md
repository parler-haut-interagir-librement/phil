# Dashboards

## The components

| Component | Shows |
|---|---|
| `<twig:PhilReporting:Dashboard name="sales" />` | A configured dashboard: its title, the dates of its period, the choice of its periods, its widgets. |
| `<twig:PhilReporting:LiveDashboard name="sales" />` | The same, whose period changes without reloading the page. |
| `<twig:PhilReporting:Metric source="orders" period="this_month" compare />` | One metric, with or without a dashboard. |
| `<twig:PhilReporting:Chart source="visits" chart="line" render="svg" />` | One series source, with or without a dashboard. |

`Dashboard` takes the period chosen as its `period` prop: the choice of periods is a `GET` form whose field is named
`period`, so a page passes the query's value on.

```twig
<twig:PhilReporting:Dashboard name="sales" :period="app.request.query.get('period', '')" />
```

`LiveDashboard` keeps the period in the address itself, as a writable prop: a link, a reload or a browser without
JavaScript shows the same period. A period from the address is accepted only among those the dashboard offers; any
other value shows the dashboard's own.

`Metric` and `Chart` take `title` (the source's when empty), `period` (`last_30_days` by default) and `locale` (the
current one when empty). `Metric` takes `compare`; `Chart` takes `chart` and `render`.

## The renderings of a chart

| `render` | What is drawn |
|---|---|
| `svg` | The SVG image of `phil/svg-chart`, then the table of its values in a `<details>` element. |
| `html` | Horizontal bars of HTML and CSS, inside a table: a row per label, a column per series, each cell its bar and its value. |
| `table` | The table of the values alone. |

No script draws anything. Every rendering keeps the values readable as text:

- the SVG image carries `role="img"`, its title, a `<desc>` that reads every value, and a `<title>` on every mark;
- the image is in a `<figure>` whose `<figcaption>` is the widget's heading, and its table has a `<caption>`;
- the bars are a table with headers by row and column; the bar is decoration, the value beside it is text, and a
  negative value is hatched as well as coloured;
- the colours follow the Okabe and Ito palette, told apart by every common colour vision;
- a metric's change is written as a sentence — "Up 20% from the previous period" —, the arrow beside it hidden from
  screen readers.

## A chart as a PNG download

Where PNG charts are drawn — the `resvg` extension loaded and `png.fonts` listed, see
[Configuration](configuration.md#the-png-charts) —, `ChartResponses` gives the chart of a series source as a PNG file,
from a controller of the application, which first checks who may see it:

```php
use Phil\ReportingBundle\Infrastructure\Http\ChartResponses;
use Phil\SvgChart\ChartType;

#[Route('/reports/visits.png')]
#[IsGranted('ROLE_MANAGER')]
public function visits(ChartResponses $charts, Periods $periods): Response
{
    return $charts->png('visits', $periods->period(PeriodPreset::Last30Days), ChartType::Line);
}
```

The image is the SVG chart of the page, drawn at the configured scale; the file is named after the source and the
first and last days of the period, `visits-20261009-20261107.png`, sent as an attachment with
`Cache-Control: private, no-store` and `X-Content-Type-Options: nosniff`. A link to it is offered only when
`$charts->hasPng()` is true: elsewhere `png()` throws `PngUnavailable` before anything is drawn, and the page keeps its
SVG image and its table. An unknown source fails with `NotFound`.

## Access

A dashboard that names a `role` is shown only to a viewer who has it, asked of Symfony's authorization checker at every
rendering, a live one's included; it is refused with `AccessDenied` when no checker is installed. The application still
protects the page that shows a dashboard; the role keeps a dashboard placed on another page from showing.

The `Metric` and `Chart` components check nothing: they show what their source gives to whoever sees the page.

## EasyAdmin

When EasyAdmin is installed, `ReportingAdmin::TEMPLATE` shows a dashboard in the layout of the back office. Add an
action to the dashboard controller, and its link to the menu:

```php
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Phil\ReportingBundle\Infrastructure\EasyAdmin\ReportingAdmin;

#[AdminRoute(path: '/reports/{dashboard}', name: 'reports')]
public function reports(string $dashboard): Response
{
    return $this->render(ReportingAdmin::TEMPLATE, ['dashboard' => $dashboard, 'live' => true]);
}

public function configureMenuItems(): iterable
{
    yield ReportingAdmin::menuItem('Sales', 'sales', 'admin_reports');
}
```

With `live: false`, the page shows `PhilReporting:Dashboard` with the `period` of the query.

The back office loads its own assets, not those of the application's pages: declare them in `configureAssets()` of
the dashboard controller. The `app` entry of AssetMapper starts Stimulus, which the Live Component needs to change the
period without reloading the page, and the bundle's stylesheet draws the metrics and the bars:

```php
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;

public function configureAssets(): Assets
{
    return Assets::new()
        ->addAssetMapperEntry('app')
        ->addCssFile('phil-reporting/reporting.css');
}
```
