# Configuration

```yaml
# config/packages/phil_workshops.yaml
phil_workshops:
    max_seats: 10
    waitlist: true
```

| Option | Default | Meaning |
|---|---|---|
| `max_seats` | `10` | The most seats one registration, or one place on a waiting list, takes: from 1 to 1,000. A session never gives one registration more seats than its capacity either |
| `waitlist` | `true` | Whether each session has a waiting list when phil/waitlist-bundle is installed and enabled; `false` refuses a full session as `full`, without a list |

The waiting lists themselves — how long an offer is held, which waiting entries a policy picks, the notifications —
are configured under `phil_waitlist`, the options of phil/waitlist-bundle.

Everything else belongs to each session — its span, its time zone, its capacity, its registration window: see
[Sessions and registrations](registrations.md).
