# Expiry and recertification

A certificate may end, and a course too. Before a certificate ends, the bundle tells the application, which reminds
its holder; the holder is then recertified, with credits of continuing education gathered meanwhile or with a new
attainment, and receives a new certificate linked to the one it renews.

## The end of a certificate and of a course

A course's `validityMonths` gives how long its certificates stay valid; its `endsAt`, when the course itself ends. A
certificate is valid until the earlier of the two — the months counted from its award —, and for ever when neither is
set. From its `endsAt` on, a course awards no more certificates, and renews none.

```php
$courses->define('first-aid', 'First aid', 'The gestures that save lives.', 'Score 70 % or more.',
    new PassCriteria(minimumScore: 70), validityMonths: 24, endsAt: new DateTimeImmutable('2030-01-01 UTC'));
```

A certificate's `status()` at a moment is `CertificateStatus::Valid`, `Expired` once its `validUntil` is reached, or
`Revoked` once revoked, whatever its dates. Expired and revoked are distinct: an expired certificate was valid for the
whole of its period and stays so on its page and in its PDF, its credential still verifies for that period, and its
bit stays clear in the status list; only a revocation sets it. The credential carries its `validUntil`, so verifiers
elsewhere learn the end without asking the issuer.

## Reminders before the end

`Phil\CertificatesBundle\Application\ExpiryReminders` dispatches `CertificateExpiring` when a certificate, neither
revoked nor renewed, comes within a threshold of its end: 60, 30 and 7 days by default, `reminders.days` in the
configuration. Each threshold is reminded once — the certificate keeps when its holder was last reminded —, and a
certificate first seen within several thresholds is reminded once, at the nearest. The bundle sends nothing: the
application listens to the event and reminds the holder by the means it chooses.

```php
#[AsEventListener]
final readonly class RemindHolder
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(CertificateExpiring $expiring): void
    {
        $this->bus->dispatch(new RemindHolderMessage($expiring->certificateId, $expiring->daysLeft));
    }
}
```

The event carries the certificate's identifier, its code, its course's slug and the whole days left; nothing about
the holder. `phil:certificates:remind` runs the reminders once — from cron or the application's scheduler, once a
day —, `--limit` reminders at most per run; `ExpiryReminders::remind()` does the same from the application's code.

## Recertification

```php
$renewal = $awarding->recertify('7KQ2-M9XA-04TZ', 'ada@example.org');
$renewal = $awarding->recertify('7KQ2-M9XA-04TZ', 'ada@example.org', new Attainment(score: 85));
```

`Awarding::recertify()` awards a new certificate of the same course that renews the one of that code:

- the identity given is the one the first credential was issued to, checked against its salted hash; another is
  refused;
- a course with `recertificationCredits` asks for that many credits of continuing education, gathered by the
  certificate's holder from the moment it was awarded until now; the certificate must name its holder;
- a course without asks for an attainment, checked against its criteria; one given to a course with credits is
  checked too;
- a revoked certificate, one already renewed, and a certificate of a course retired or ended are not renewed.

The new certificate has its own code, status index and credential, signed to the same identity under a new salt; it
keeps the first one's name, holder and listing, unless `listed` says otherwise. It is valid from the end of the first
when that end is still to come, so that renewing early loses nothing, and from now otherwise. The first certificate
stays as it is, valid until its own end; the register links each to the other ("Renews", "Renewed by"), and
`CertificateRepository::findRenewalOf()` finds the renewal. `CertificateAwarded`, then `CertificateRenewed` — which
names the certificate renewed — are dispatched.

A refusal throws `CertificateRefused`, its message saying what is missing: the credits gathered against those asked
for, a holder to count them for, an attainment.

## Credits of continuing education

`Phil\CertificatesBundle\Application\ContinuingEducation` keeps the activities each member followed — a workshop, a
conference, a course elsewhere — with the credits they earn: hours, or credits, as the application counts them, to the
hundredth.

```php
use Phil\CertificatesBundle\Domain\ValueObject\Credits;
use Phil\CertificatesBundle\Domain\ValueObject\Period;

$education->record('member-42', 'Workshop on triage', Credits::fromString('3.5'),
    completedAt: new DateTimeImmutable('2026-11-12 UTC'), course: 'first-aid', source: 'workshop-17');

$education->total('member-42', new Period(new DateTimeImmutable('2026-01-01 UTC'), new DateTimeImmutable('2027-01-01 UTC')));
$education->progress('7KQ2-M9XA-04TZ');    // ['gathered' => Credits, 'required' => ?Credits, 'missing' => ?Credits]
```

| Field | Rule |
|---|---|
| Member | The stable identifier the application gives the member, up to 191 characters without spaces; an e-mail address is refused |
| Title | 1 to 255 characters |
| Credits | More than 0, up to 9 999 999.99 |
| Completed at | Any moment; now when left out |
| Course | The course whose recertification it counts toward; every course when left out |
| Source | Where it comes from, up to 255 characters, unique for the member: recording it again returns the activity already recorded |

A period includes its start and excludes its end. The total toward a course counts the activities of that course and
those that name none. `activities()` lists a member's activities of a period, the latest first, 500 at most;
`remove()` removes one by its identifier. `LearningActivityRecorded`, which carries the activity's and the member's
identifiers, is dispatched once an activity is stored.

A certificate names its holder when it is awarded with `holder:` — or `--holder` on the console —; its credits are then
counted for its recertification. `phil:certificates:credit` and `phil:certificates:credits` record and show them from
the console ([Console commands](console.md#credits-of-continuing-education)).
