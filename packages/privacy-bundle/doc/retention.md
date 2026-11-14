# Retention schedule and purges

The schedule comes from the configuration or from a `RetentionScheduleSource` of the application. `RetentionPurger`
adapters are found by autoconfiguration. The bundle runs the job: a command, a message and a schedule.

```yaml
phil_privacy:
    retention:
        frequency: '1 day'
        rules:
            R-1: { store: trash, period: P30D }
            R-2: { store: phil_privacy_account_deletion, period: P1Y }
            R-3: { store: phil_privacy_consent_proof, period: P3Y }
            R-4:
                store: accounts
                categories: [email, person_name]
                period: { until_event: 'the account is closed' }
                mechanism: event
                purpose: Running the members' accounts
                recipients: [Membership team]
                processors:
                    - { name: Hosting provider, location: 'Canada (Montréal)' }
                    - { name: Mail service, location: United States }
                location: 'Canada (Montréal)'
```

A line has a `store`, its `categories`, a `period` (an ISO 8601 duration, `indefinite`, `{ until_event: … }` or
`{ not_yet_fixed: … }`), and `mechanism` (default `job`), `erasure` (default `deleted`) and `access` (default `returned`).
The keys `purpose`, `recipients`, `processors` and `location` are what the register of processing says of the store
([export-and-erasure.md](export-and-erasure.md#the-register-of-processing)): the schedule declares the register.
`retention_schedule_source` names a source instead: giving both stops the build, a schedule having one authority.

Purging an expired backup belongs to `phil/backup-bundle`, and the expiry of a published content to
`phil/content-expiry`.

A retention schedule is a control, not a document: every line names what enforces it, and the lines a job
enforces become purge orders that must reach a purger.

## `RetentionPeriod`

How long a store keeps a record. Four shapes, because a schedule holds all four and only one is a number.

| Factory | Kind (`RetentionPeriodKind`) | `$duration` | `$note` | Cutoff |
|---|---|---|---|---|
| `fixed('P24M')` | `Fixed` | the ISO 8601 duration | `null` | computed |
| `untilEvent('account closure')` | `UntilEvent` | `null` | the event | `null` |
| `indefinite()` | `Indefinite` | `null` | `null` | `null` |
| `notYetFixed('legal review')` | `NotYetFixed` | `null` | where the value will be decided | `null` |

- `fixed()` accepts the ISO 8601 designator form `P[nY][nM][nW][nD][T[nH][nM][nS]]`. The alternative form
  (`P0001-00-00`) and a zero duration (`P0D`) are refused — a record kept for no time is not stored.
- `untilEvent()` and `notYetFixed()` refuse a blank note.
- `isFixed(): bool`.
- `cutoff(DateTimeImmutable $now): ?DateTimeImmutable` — the instant before which a record has outlived the
  period, or `null` when the period is not a number. A "not yet fixed" period is a counted blank that **never**
  produces a cutoff.

**Cutoffs are computed in UTC, and month arithmetic clamps.** PHP turns 31 March minus one month into 3 March;
a purge on that cutoff deletes three days early. Here it is 28 February (29 in a leap year), and 29 February
minus one year is 28 February. Years and months are subtracted first, clamped to the end of the month, then the
rest of the interval. A record is never purged before its period has fully run.

```php
RetentionPeriod::fixed('P1M')->cutoff(new DateTimeImmutable('2026-03-31T10:00:00-04:00'));
// 2026-02-28T14:00:00+00:00
```

## `RetentionRule`

One line of the schedule.

```php
new RetentionRule(
    id: 'R-002',
    store: 'crm.contacts',
    categories: [DataCategory::fromString('email'), DataCategory::fromString('person_name')],
    period: RetentionPeriod::fixed('P24M'),
    mechanism: Mechanism::Job,
    erasure: ErasureEffect::Deleted,
    access: AccessResponse::Returned,
    processing: new Processing(
        purpose: 'Answering the people who write in',
        recipients: ['Support team'],
        processors: [new Processor('Mail service', 'United States')],
        location: 'Canada',
    ),
);
```

| Field | Rule |
|---|---|
| `id` | `RetentionRule::ID_PATTERN`: letter or digit first, then letters, digits, `.`, `_`, `-`; at most 64 characters. Yours; added and never renumbered, because `#[PersonalData]` declarations cite it. |
| `store` | Non-blank. The name a `RetentionPurger` answers for. |
| `categories` | `list<DataCategory>`, no duplicates. Empty states that the store holds no personal information (`holdsPersonalInformation()` is `false`). |
| `period` | A `RetentionPeriod`. |
| `mechanism` | `Mechanism`: `Job` (a purge this bundle drives through a `RetentionPurger`), `Ttl` (the store expires its own entries), `Rotation` (log or file rotation), `Event` (an action or domain event removes it), `Manual` (a person purges at a recorded cadence). |
| `erasure` | `ErasureEffect`: what erasing a person does to this store. |
| `access` | `AccessResponse`: `Returned` (field by field), `AsCategory` (named with its period, records not extracted: logs, backups), `NotReturned`. |
| `processing` | A `Processing`, empty by default: what the register of processing says of the store. |

Refused at construction, because each is a contradiction a reader would otherwise have to notice:

- **a purge job on an indefinite period** (`Mechanism::Job` with `indefinite()`): the job could never purge;
- **`AwaitsExpiry` without a fixed period**: the answer to an erasure request is the duration, and there is none.

## `Processing` and `Processor`

What the register of processing says of a store beside how long it keeps a record.

| Field | Rule |
|---|---|
| `purpose` | Why the store holds the records: 1 to 1,000 characters, or `null`. |
| `recipients` | `list<string>`: who receives the records — a team, a partner, an authority —, one line of at most 200 characters each, no duplicates. |
| `processors` | `list<Processor>`: the service providers that handle the records, each a `name` and a `location`, one line of at most 200 characters each, no two with the same name. |
| `location` | Where the store keeps the records — a country, a region, a data centre —, one line, or `null`. |

Every part is optional, so that a schedule can be written before its register is complete. `isComplete()` is true when
the purpose and the location are given, the two questions the law always asks of a store. `Processor::toString()` is
`name (location)`.

## `RetentionSchedule`

`new RetentionSchedule(iterable<RetentionRule> $rules)` — refuses a duplicate identifier; keeps schedule order.

| Method | Returns |
|---|---|
| `has(string $id): bool` | whether a rule exists |
| `rule(string $id): RetentionRule` | the rule; throws `InvalidPrivacyValue` if unknown |
| `rules(): list<RetentionRule>` | every rule, in schedule order |
| `purgeOrders(DateTimeImmutable $now): list<PurgeOrder>` | one order per `Job` rule with a fixed period, in schedule order |
| `jobsWithoutCutoff(): list<RetentionRule>` | the `Job` rules whose period is until an event or not yet fixed |

The bundle does not decide the lines of a schedule, and reads it only from its own configuration; a source of the
application reads any other file.

## `PurgeOrder`

`$ruleId`, `$store`, `$cutoff` (UTC `DateTimeImmutable`): remove what the rule covers that is older than the cutoff.

## `RetentionRun`

The body of the retention job.

```php
$run = new RetentionRun($scheduleSource, [$clicksPurger, $historyPurger]);
$purged = $run->run(new DateTimeImmutable());   // ['R-1' => 5, 'R-2' => 0]
```

- `__construct(RetentionScheduleSource $schedule, iterable<RetentionPurger> $purgers)` — throws
  `MisconfiguredPrivacy::duplicatePurger()` when two purgers answer for one store.
- `orders(DateTimeImmutable $now): list<PurgeOrder>` — what a run would purge, or the refusal below.
- `run(DateTimeImmutable $now): array<string, int>` — loads the schedule, computes the purge orders, then:
  1. **checks that every order has a purger before purging anything**, and throws
     `MisconfiguredPrivacy::missingPurger($store, $ruleIds)` otherwise. A job line with nothing behind it is a
     period stated and not enforced; purging the other stores first would report it as a partial success;
  2. hands each order to its store's purger, in schedule order;
  3. returns the records purged, by rule identifier.
- Lines that yield no cutoff are not checked and get no order; list them with `jobsWithoutCutoff()` and run or
  report them on your own terms.
- A purger that throws stops the run at that order; orders before it have been executed.

## Running it

`RetentionJob` is the run as a job: at the present instant, one at a time under a lock, with a log line per rule that
gives the rule and the count, never what was purged. A second run while one is in progress does nothing.

- `bin/console phil:privacy:retention` lists what a run would purge, and names the job lines without a cutoff;
  `--confirm` purges. A job line without a purger stops the command before anything is purged.
- The message `RunRetention` runs it from anywhere.
- With `symfony/scheduler`, the schedule `phil_privacy` sends that message every `retention.frequency`:
  `bin/console messenger:consume scheduler_phil_privacy`.

The ports are described in [extension-points.md](extension-points.md).
