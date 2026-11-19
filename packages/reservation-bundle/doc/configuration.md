# Configuration

```yaml
# config/packages/phil_reservation.yaml
phil_reservation:
    batch: 500
    calendar: true
```

| Option | Default | Meaning |
|---|---|---|
| `batch` | `500` | The most reservations each run of the commands reads, and the calendar shows in a period: from 1 to 10,000 |
| `calendar` | `true` | Whether the confirmed reservations are items of `phil/calendar-bundle`'s views, when it is installed |

Everything else belongs to each resource: see [Resources](resources.md).
