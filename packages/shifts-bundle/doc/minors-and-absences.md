# Minors and absences

Two optional rules guard who joins a shift: a volunteer who is a **minor** joins once the consent of a parent or
guardian is recorded, and a volunteer whose **unannounced absences** reach a threshold no longer joins by themselves.
Both are off by default; each keeps the least data that does the job.

## Volunteers who are minors

### Turning it on

The bundle stores no birth date. The application gives the ones it knows through a `BirthDates` service, and names it:

```php
use Phil\ShiftsBundle\Application\Contract\BirthDates;

final readonly class MemberBirthDates implements BirthDates
{
    public function __construct(private Members $members) {}

    public function of(string $volunteer): ?\DateTimeImmutable
    {
        return $this->members->find($volunteer)?->birthDate;
    }
}
```

```yaml
phil_shifts:
    minors:
        birth_dates: App\Shifts\MemberBirthDates
        age_of_majority: 18
```

Without `birth_dates`, nobody is a minor and nothing is asked. A volunteer whose birth date the application does not
give, `null`, is not a minor: an application that requires the birth date asks for it at registration.

### Who is a minor

A volunteer is a minor **on a shift** when, on the day the shift starts in the configured `time_zone`, they have not
reached `age_of_majority`, 1 to 30, 18 by default. Only the calendar date of the birth date counts, never its hour or
offset. A volunteer who turns 18 on the Saturday of a convention is a minor on the Friday evening shift and of age on the
Saturday morning one. A volunteer born on 29 February reaches the age on 1 March of a year that has no 29 February.

`Minors::isMinor($volunteer, $on)` answers for any instant, today by default.

### The contact of a guardian

`Minors` records the contact of a guardian and their consent; the application calls it from its own form, a
coordinator's or the guardian's:

```php
use Phil\ShiftsBundle\Application\Minors;
use Phil\ShiftsBundle\Domain\Exception\InvalidGuardianContact;

try {
    $minors->record('volunteer-42', 'Marie Tremblay', '+1 514 555-0100', null, $coordinator);
    $minors->consent('volunteer-42', $coordinator);
} catch (InvalidGuardianContact $refused) {
    $form->addError(new FormError($translator->trans($refused->reason, domain: 'phil_shifts')));
}
```

| Field | Kept | Bounds |
|---|---|---|
| Name | always | 1 to 120 characters, no control character |
| Telephone | when given | digits, spaces, `+`, `(`, `)`, `.` and `-`, 4 to 32 characters |
| Email | when given | one `@`, a dot in the domain, 254 characters at most |
| Who recorded it | always | the identifier of a person |
| The consent | once given | its instant, by the server's clock, and who recorded it |

At least a telephone number or an email address is required. The bundle keeps no address, birth date or relationship.

| Method | What it does |
|---|---|
| `record($volunteer, $name, $phone, $email, $recordedBy)` | Records the contact, or corrects it; a consent already given is kept. |
| `consent($volunteer, $recordedBy)` | Records the consent now; refused with `shifts.no_guardian` without a contact. |
| `withdrawConsent($volunteer)` | Removes the consent, keeps the contact. |
| `contactOf($volunteer)` | The `GuardianContact`, or null. |
| `forget($volunteer)` | Deletes the contact and its consent, once the volunteer is of age or leaves. |
| `mayJoin($volunteer, $shift)`, `assertMayJoin()` | Whether the volunteer may join the shift. |

### What it guards

A minor without the consent of a guardian is refused with `shifts.guardian_consent` when they sign up, say they are
interested, take an offer of the swap board, or when a coordinator assigns them; the allocation among the interested
leaves them out. Withdrawing a consent, or forgetting a contact, takes nobody off a shift: the coordinator decides.

## Unannounced absences

### Recording an absence

Once a shift has started, a coordinator records that a volunteer who held a place did not come without saying so, from
the roster page or through `Absences`:

| Method | What it does |
|---|---|
| `record($shiftId, $volunteer, $coordinator)` | Records the absence; false when it was recorded already. Refused with `shifts.not_started` before the start, `shift.not_on_roster` for a volunteer who held no place. |
| `forgive($shiftId, $volunteer)` | Takes back an absence recorded by mistake. |
| `count($volunteer)` | The absences that count: recorded over the window, not lifted. |
| `isBlocked($volunteer)`, `assertMayJoinFreely()` | Whether the volunteer reached the threshold. |
| `lift($volunteer, $organiser)` | Lifts the block: the absences that counted no longer do. |
| `ofVolunteer($volunteer)`, `absentFrom($shiftId)` | The absences of a volunteer, lifted ones too; the volunteers absent from a shift. |

A volunteer who withdrew before the shift is not absent: they are no longer on the roster.

### The threshold

```yaml
phil_shifts:
    absences:
        threshold: 3
        window_days: 365
```

Without `threshold`, the absences are recorded and counted and block nothing. With it, 1 to 100, a volunteer whose
absences over the last `window_days`, 1 to 3,650, reach the threshold no longer joins a shift **by themselves**: signing
up, saying they are interested and taking an offer are refused with `shifts.absences`, and the allocation leaves them
out. A coordinator may still assign them.

An organiser lifts the block from the roster page, where the volunteer carries the badge "Blocked by absences", or
through `Absences::lift()`. A lifted absence keeps its instant and the person who lifted it, and no longer counts. An
absence older than the window stops counting by itself.

## Privacy

The tables `phil_shifts_guardians` and `phil_shifts_absences` hold identifiers, the guardian's name and ways to reach
them, and instants. With phil/privacy-bundle, the export of a volunteer holds their guardian's contact with the consent
and their absences, and the erasure deletes both: see [Storage and privacy](storage-and-privacy.md).

An application that keeps the contact of a guardian tells the guardian why and for how long, and calls `forget()` once
the volunteer is of age or leaves.
