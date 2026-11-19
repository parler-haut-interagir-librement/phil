# Configuration

The bundle's configuration lives under `phil_library`; every option has a default:

```yaml
# config/packages/phil_library.yaml
phil_library:
    timezone: America/Toronto          # the library's clock: due dates, and the moments the pages show
    loans:
        days: 21                       # how many days a loan lasts, due at the end of its last day
        max_renewals: 2                # how many times a loan is renewed
        max_open: 10                   # how many loans, reserved or out, a member holds at once
        block_when_overdue: true       # a member with a copy overdue borrows no more
        reservation_days: 90           # how far ahead a copy is reserved
    reminders:
        before_due: [2880, 1440]       # minutes before the due date
        overdue_every_days: 7          # days between two notices of a loan overdue
        overdue_max: 3                 # notices at most; none with 0
    history_days: 365                  # days a closed loan keeps its member; until an erasure when null
    batch: 500                         # loans read by each run of phil:library:send-reminders
    per_page: 25                       # titles on a page of the catalogue
    member_resolver: App\Library\Members  # the service that names the member of a request
    stockroom: Phil\Stockroom\Stockroom   # the stockroom the loans of linked copies move items in; phil/stockroom-bundle's by default
    roles:
        desk: ROLE_LIBRARIAN
        admin: ROLE_LIBRARIAN
    pages:
        layout: base.html.twig         # the layout the pages extend
        kit_subdirectory: null         # where the recipes of the kit are; the subdirectory of ux_toolkit when null
```

| Option | Default | Bounds | What it does |
|---|---|---|---|
| `timezone` | `UTC` | a time zone | A loan is due at 23:59:59 of its last day on this clock; the pages show their dates on it |
| `loans.days` | `21` | 1 to 365 | The length of a loan, and of each renewal |
| `loans.max_renewals` | `2` | 0 to 99 | How many times a loan is renewed |
| `loans.max_open` | `10` | 1 to 999 | Loans reserved or out a member holds at once |
| `loans.block_when_overdue` | `true` | | Whether a copy overdue stops the member from borrowing |
| `loans.reservation_days` | `90` | 1 to 730 | How far ahead a reservation starts at most |
| `reminders.before_due` | `[1440]` | 1 to 525 600 each | The reminders before the due date, in minutes |
| `reminders.overdue_every_days` | `7` | 1 to 365 | The days between two notices |
| `reminders.overdue_max` | `3` | 0 to 99 | The notices a loan overdue gets |
| `history_days` | `null` | 0 to 36 500 | The days a closed loan keeps its member; kept until an erasure when null |
| `batch` | `500` | 1 to 10 000 | The loans each run of the reminders reads |
| `per_page` | `25` | 1 to 100 | The titles on a page of the catalogue |
| `member_resolver` | `null` | a service | The application's `MemberResolver`; without one, the member's page is not found |
| `stockroom` | `null` | a service | `phil/stockroom`'s `Stockroom` the loans of linked copies move items in; `null` takes `phil/stockroom-bundle`'s when it is installed, else no loan moves an item |
| `roles.desk`, `roles.admin` | `ROLE_LIBRARIAN` | | The roles of the desk and of the administration |
| `pages.layout` | `@PhilLibrary/layout.html.twig` | | The layout the pages extend, which defines `title` and `content` |
| `pages.kit_subdirectory` | `null` | | Where the recipes of the kit are, under the components directory |

The longest a loan lasts, renewals included, is `days × (max_renewals + 1) + 1` days; a due date given beyond it is
refused.
