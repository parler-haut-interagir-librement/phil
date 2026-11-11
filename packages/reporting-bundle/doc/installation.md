# Installation

## Requirements

| Package | Version | Why |
|---|---|---|
| PHP | 8.5 or later, with `intl`, `json`, `mbstring` and `sodium` | Figures written in a locale; jobs as JSON; labels counted in characters; random identifiers. |
| `phil/svg-chart` | `^0.1` | The SVG images of the charts. |
| `symfony/config`, `symfony/dependency-injection` | `^8.2` | The bundle and its configuration. |
| `symfony/service-contracts` | `^3.6` | The locators of the sources, reports, formats and deliveries. |
| `psr/clock`, `psr/event-dispatcher` | `^1.0` | The moment the periods are counted from; `ReportExported`. |

Each of these is used when it is installed:

| Package | For |
|---|---|
| `symfony/ux-twig-component` | The components `Dashboard`, `Metric` and `Chart`. |
| `symfony/ux-live-component` | The component `LiveDashboard`, whose period changes without reloading the page. |
| `symfony/messenger` | `ReportQueue` and the handlers of `RunReport` and `RunScheduledReport`. |
| `symfony/scheduler` | The `phil_reporting` schedule. |
| `symfony/mailer`, `symfony/mime` | The `mail` delivery. |
| `symfony/notifier` | The `notification` delivery. |
| `symfony/console` | `phil:reporting:export`. |
| `symfony/http-foundation` | `ReportResponses`. |
| `symfony/security-core` | Dashboards restricted to a role. |
| `easycorp/easyadmin-bundle` | A dashboard as a page of the back office. |
| `symfony/asset-mapper` | The stylesheet mapped as `phil-reporting`. |

## Enabling the bundle

```sh
composer require phil/reporting-bundle
```

```php
// config/bundles.php
return [
    // …
    Phil\ReportingBundle\PhilReportingBundle::class => ['all' => true],
];
```

The bundle needs a `clock` service, which FrameworkBundle registers. The components need TwigBundle and the
translator, for their labels in the `phil_reporting` domain.

## The stylesheet

With AssetMapper, the stylesheet is mapped as `phil-reporting/reporting.css`:

```twig
<link rel="stylesheet" href="{{ asset('phil-reporting/reporting.css') }}">
```

It is written in the `phil.components` cascade layer on the tokens of PHIL UX (`phil/ux-css`), with a value of its own
for each when PHIL UX is not loaded. Its bars read their length from a `data-share` attribute: no inline style, so a
Content Security Policy without `'unsafe-inline'` for styles shows them.

## The directory of the claim check

The `directory` store keeps the jobs and the exports under `%kernel.project_dir%/var/phil_reporting`, created
readable by its owner only. The worker that runs the reports and the code that queues them must share it: on several
machines, alias `JobStore` and `ExportStore` to services of the application, as [Extension points](extension-points.md)
shows.
