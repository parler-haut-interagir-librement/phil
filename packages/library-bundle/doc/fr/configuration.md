<!-- phil-dev source-sha256=245a2252d86147ace43f70675fcd6bfe3357b52cd5217b300677250c81253fc3 -->
# Configuration

La configuration du bundle se trouve sous `phil_library` ; chaque option a une valeur par défaut :

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

| Option | Valeur par défaut | Bornes | Effet |
|---|---|---|---|
| `timezone` | `UTC` | un fuseau horaire | Un prêt est dû à 23:59:59 de son dernier jour sur cette horloge ; les pages y montrent leurs dates |
| `loans.days` | `21` | de 1 à 365 | La durée d'un prêt, et de chaque renouvellement |
| `loans.max_renewals` | `2` | de 0 à 99 | Combien de fois un prêt est renouvelé |
| `loans.max_open` | `10` | de 1 à 999 | Les prêts réservés ou sortis qu'un membre détient à la fois |
| `loans.block_when_overdue` | `true` | | Si un exemplaire en retard empêche le membre d'emprunter |
| `loans.reservation_days` | `90` | de 1 à 730 | Jusqu'à quelle distance dans l'avenir une réservation commence, au plus |
| `reminders.before_due` | `[1440]` | de 1 à 525 600 chacun | Les rappels avant l'échéance, en minutes |
| `reminders.overdue_every_days` | `7` | de 1 à 365 | Les jours entre deux avis |
| `reminders.overdue_max` | `3` | de 0 à 99 | Les avis que reçoit un prêt en retard |
| `history_days` | `null` | de 0 à 36 500 | Les jours pendant lesquels un prêt clos garde son membre ; conservé jusqu'à un effacement si `null` |
| `batch` | `500` | de 1 à 10 000 | Les prêts que lit chaque exécution des rappels |
| `per_page` | `25` | de 1 à 100 | Les titres d'une page du catalogue |
| `member_resolver` | `null` | un service | Le `MemberResolver` de l'application ; sans lui, la page du membre est introuvable |
| `stockroom` | `null` | un service | Le `Stockroom` de `phil/stockroom` dans lequel les prêts des exemplaires liés déplacent les articles ; `null` prend celui de `phil/stockroom-bundle` quand il est installé, sinon aucun prêt ne déplace d'article |
| `roles.desk`, `roles.admin` | `ROLE_LIBRARIAN` | | Les rôles du comptoir et de l'administration |
| `pages.layout` | `@PhilLibrary/layout.html.twig` | | La mise en page que prolongent les pages, qui définit `title` et `content` |
| `pages.kit_subdirectory` | `null` | | L'emplacement des recettes du kit, sous le répertoire des composants |

La durée maximale d'un prêt, renouvellements compris, est de `days × (max_renewals + 1) + 1` jours ; une échéance donnée
au-delà est refusée.
