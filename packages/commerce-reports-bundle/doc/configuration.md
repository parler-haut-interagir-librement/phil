# Configuration

The bundle's configuration lives under `phil_commerce_reports`:

```yaml
# config/packages/phil_commerce_reports.yaml
phil_commerce_reports:
    dead_stock_days: 90
```

| Option | Default | What it does |
|---|---|---|
| `dead_stock_days` | `90` | The days without a sale after which a SKU with units available is dead stock, from 1 to 3650. A request's `days` parameter overrides it. |

The periods, the formats, the largest number of rows, the schedules and the deliveries are the reporting bundle's
options, under `phil_reporting`.
