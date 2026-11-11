# Configuration

```yaml
# config/packages/phil_reporting.yaml
phil_reporting:
    timezone: America/Toronto        # the periods are whole days of this time zone; UTC by default
    chart: { width: 640, height: 320 } # the SVG images, 100 to 4000 pixels; they scale to their container
    max_rows: 100000                 # the most rows a report exports, 1 to 10 000 000
    store: directory                 # or memory, for the tests and the sync transport
    directory: '%kernel.project_dir%/var/phil_reporting'
    keep_delivered: false            # whether a delivered export stays in the export store
    mail:
        enabled: true
        from: 'Reports <reports@example.com>'
    notification:
        enabled: true
        channels: [chat/slack]       # the importance's channels when empty
    dashboards:
        sales:
            title: Sales
            role: ROLE_MANAGER       # none by default: everyone may see it
            period: last_30_days
            periods: [last_7_days, last_30_days, this_month, last_month]
            widgets:
                - { metric: orders, compare: true }
                - { metric: revenue, title: Revenue }
                - { series: visits, chart: line }
                - { series: sales_by_category, render: html }
                - { series: refunds, render: table }
    schedules:
        monthly_sales:
            report: sales
            format: csv
            every: 1 month
            from: '2026-12-01 06:00'
            period: last_month
            delivery: mail
            recipients: [manager@example.com]
            parameters: { status: paid }
```

## The dashboards

| Key | Default | Meaning |
|---|---|---|
| `title` | required | The heading of the dashboard. |
| `role` | `null` | The role a viewer needs. A dashboard that names one is refused when no authorization checker is installed. |
| `period` | `last_30_days` | The period shown first; it must be among `periods`. |
| `periods` | `[period]` | The periods the viewer chooses among, in their order; duplicates are dropped. |
| `widgets` | `[]` | The tiles, in their order. |

A dashboard is named by 1 to 64 letters, digits, dots, dashes or underscores. The periods are `today`, `yesterday`,
`last_7_days`, `last_30_days`, `this_month`, `last_month`, `last_12_months`, `this_year` and `last_year`.

A widget names either a `metric` or a `series` source by its key:

| Key | Default | Meaning |
|---|---|---|
| `metric` / `series` | — | The key of the source; exactly one of the two. |
| `title` | the source's | The heading of the widget. |
| `compare` | `false` | Whether a metric shows its change from the period before. |
| `chart` | `bar` | `bar`, `line`, `pie` or `doughnut`, for the SVG image. |
| `render` | `svg` | `svg`, `html` or `table`; see [Dashboards](dashboards.md). |

## The schedules

| Key | Default | Meaning |
|---|---|---|
| `report` | required | The key of the report. |
| `format` | `csv` | The key of the format. |
| `every` | required | The frequency, a relative date format: `1 day`, `1 week`, `1 month`. |
| `from` | `null` | The first moment, `06:00` or `2026-12-01 06:00`; the start of the scheduler when null. |
| `period` | `last_month` | The preset the report covers, at the moment it runs. |
| `delivery` | `null` | `mail`, `notification` or a delivery of the application; the export stays in the store when null. |
| `recipients` | `[]` | What the delivery reads: e-mail addresses for `mail` and `notification`. |
| `parameters` | `{}` | The parameters of the report, scalars under keys of 1 to 64 letters, digits, dots, dashes or underscores. |

A schedule is named like a dashboard. The recipients are kept in the job store with the job, never in the message.

## The deliveries

`mail` needs its `from` address; it is registered when Mailer is installed and `mail` is enabled. `notification` is
registered when Notifier is installed and `notification` is enabled.
