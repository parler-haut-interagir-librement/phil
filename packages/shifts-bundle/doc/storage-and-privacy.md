# Storage and privacy

## The tables

| Table | Holds | Key |
|---|---|---|
| `phil_shifts_shifts` | each shift: its definition and its places as the fields of the library's formats, its window, its revision, the instant of its last write | `shift_id` |
| `phil_shifts_places` | one row per volunteer on a roster, with its status, rewritten with the roster | `shift_id`, `volunteer` |
| `phil_shifts_swaps` | each offer of the swap board as the fields of its format, with its shift, its volunteers, its status and its revision | `id` |
| `phil_shifts_guardians` | the contact of the guardian of a volunteer who is a minor: name, telephone, email, who recorded it, and the consent with its instant and who recorded it | `volunteer` |
| `phil_shifts_absences` | each unannounced absence: the shift, the volunteer, its instant and the coordinator who recorded it, and its lift | `shift_id`, `volunteer` |

The instants are `datetime_utc_immutable` columns. On MySQL and MariaDB the keys and identifiers use the `utf8mb4_bin`
collation, so that they compare byte by byte, as on PostgreSQL and SQLite. With the ORM, `ShiftsSchemaListener` adds
the tables to the schema of the configured connection, and `doctrine:migrations:diff` writes their migration. Without
it, an application creates them from the schema the bundle describes:

```php
use Doctrine\DBAL\Schema\Schema;
use Phil\ShiftsBundle\Infrastructure\Doctrine\ShiftsTables;

foreach (ShiftsTables::configure(Schema::editor()->create(), $connection->getDatabasePlatform())->toSql($connection->getDatabasePlatform()) as $sql) {
    $connection->executeStatement($sql);
}
```

## The revisions

Each write of a roster or of an offer is conditional on the revision read — `… WHERE shift_id = ? AND revision = ?` —,
which the database runs atomically. `Rosters` and `SwapBoard` read again and apply the change again when another
request came first, three times at most, then refuse with `shifts.busy`. A completed swap writes its offer and its
roster in one transaction: either the place passed and the offer is completed, or neither.

The lists are bounded: 500 shifts to come at most, 500 shifts of a volunteer, 500 offers on the board.

## The events

Once a change is stored, its events — `VolunteerConfirmed`, `VolunteerReserved`, `InterestExpressed`,
`VolunteerWithdrew`, `ReservePromoted`, `PlaceTransferred`, `SwapOffered`, `SwapTaken` — go to the application's event
dispatcher. They carry identifiers only: a listener that sends a notice reads the shift and the volunteer from the
store, or forwards the event to a Messenger transport as it is.

```php
use Phil\Shifts\Event\ReservePromoted;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class TellThePromotedVolunteer
{
    public function __invoke(ReservePromoted $event): void
    {
        // $event->shiftId, $event->volunteerId
    }
}
```

## Messenger

`AllocateInterest` carries the identifier of a shift. Its handler allocates the free places among the interested; a
shift removed, closed or no longer allocated is logged as a notice with its identifier, and a roster busy three times is
thrown, so that Messenger retries the message.

```yaml
framework:
    messenger:
        routing:
            Phil\ShiftsBundle\Application\Message\AllocateInterest: async
```

## A volunteer's data

With phil/privacy-bundle, `ShiftsPrivacy` exports and erases a volunteer's data under the module `shifts`:

- the **export** holds the shifts on which they stand — the shift, its title, its window and minutes, their status,
  since when, and the coordinator who assigned them —, the offers they made or took, the contact of their guardian with
  the consent, and their unannounced absences with their lift;
- the **erasure** takes them off every roster — nobody is promoted, no event is dispatched — and deletes those offers,
  that contact and those absences. The shifts remain for the others. Its report names the four tables:
  `phil_shifts_places`, `phil_shifts_swaps`, `phil_shifts_guardians` and `phil_shifts_absences`, each `Deleted`.

The identifier of a volunteer is the one the application gives. The only name, telephone number and email address the
tables hold are those of the guardian of a volunteer who is a minor; the birth dates stay with the application.
