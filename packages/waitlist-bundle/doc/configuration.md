# Configuration

The bundle's configuration lives under `phil_waitlist`; every option has a default, and the bundle needs no
configuration file.

```yaml
# config/packages/phil_waitlist.yaml
phil_waitlist:
    offer_window: 172800        # two days
    max_places: 10
    policy: first_in_line
    batch: 100
    notifications:
        joined: ~
        offered: waitlist.offered
        accepted: ~
        lapsed: waitlist.lapsed
```

| Option | Default | What it does |
|---|---|---|
| `offer_window` | `172800` | How long an entry holds an offer, in seconds, for the lists opened without their own window: from 60 (a minute) to 2,592,000 (thirty days). |
| `max_places` | `10` | The most places one entry asks for: from 1 to 1,000. More is refused with `RefusalReason::TooManyPlaces`. |
| `policy` | `first_in_line` | Which waiting entries are offered the places held: `first_in_line` strictly by rank, `first_that_fits` passing over those that need more. Alias `OfferPolicy` to a service of your own for another. |
| `batch` | `100` | The most entries read at a time: waiting entries when places free up, offers and lists when offers lapse, entries listed; from 1 to 10,000. |
| `notifications.joined` | `null` | The notification type sent when an entry joins a list; `null` sends none. |
| `notifications.offered` | `waitlist.offered` | The notification type sent when an entry is offered places. |
| `notifications.accepted` | `null` | The notification type sent when an entry accepts its offer. |
| `notifications.lapsed` | `waitlist.lapsed` | The notification type sent when an entry's offer lapses. |

The `notifications` options matter only when phil/notifications-bundle is enabled, and a type sends only once it is
declared to that bundle; see [Notifications](notifications.md).

A list opened with its own window keeps it: `Waitlists::open($type, $id, offerWindow: 3600)`, or
`Waitlists::changeOfferWindow()` later, for the offers made from then on.
