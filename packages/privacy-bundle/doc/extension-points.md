# Extension points

Every interface of the bundle is a contract, under `Phil\PrivacyBundle\Application\Contract`, or under
`Phil\PrivacyBundle\Domain\Contract` for `Anonymizer`. The application implements `PersonalDataExporter`,
`PersonalDataEraser`, `RetentionScheduleSource` and `RetentionPurger`, and the bundle registers none of them; its user
class implements `DataSubjectUser`; an `Anonymizer` adds a type to the anonymization. Each other contract has an adapter
of the bundle by default, which the configuration replaces by naming a service.

## `PersonalDataExporter`

One module's part of an access or portability answer. Consumed by `SubjectExport`.

| Method | Contract |
|---|---|
| `module(): ModuleName` | The module's name. Unique across exporters, stable over time: it is the key of the module's section. |
| `export(DataSubject $subject): array<string, mixed>` | The module's records on the person, JSON-encodable, indexed as the module sees fit. `[]` when it holds nothing. |

Invariants an adapter keeps:

- built from the read models the module already queries, not from a parallel view;
- returns only data about `$subject`;
- returns JSON-encodable values (scalars, arrays, `null`; format dates yourself);
- read-only: an export changes nothing;
- ends within the lifetime of the lock it runs under, five minutes for a request without an account or a download,
  fifteen for an account deletion: the lock is renewed between modules, not during one
  ([Locks and long work](export-and-erasure.md#locks-and-long-work)).

```php
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;

final readonly class BillingExporter implements PersonalDataExporter
{
    public function __construct(private InvoiceReadModel $invoices) {}

    public function module(): ModuleName
    {
        return ModuleName::fromString('billing');
    }

    public function export(DataSubject $subject): array
    {
        $invoices = $this->invoices->forCustomer($subject->value);

        return [] === $invoices ? [] : ['invoices' => array_map(
            static fn (Invoice $i): array => ['number' => $i->number, 'issued_at' => $i->issuedAt->format(DATE_ATOM)],
            $invoices,
        )];
    }
}
```

## `PersonalDataEraser`

One module's part of an erasure. Consumed by `SubjectErasure`.

| Method | Contract |
|---|---|
| `module(): ModuleName` | The module's name. Unique across erasers. |
| `erase(DataSubject $subject): ErasureReport` | Erases the module's records on the person and reports every store it holds. |

Invariants an adapter keeps:

- **idempotent**: a second run on an already erased person succeeds and reports the same effects (retries,
  replays after a backup restore);
- the report names **this** module (`ErasureReport::of($this->module()->value, ...)`); a report under another
  name is recorded as a failure;
- the report names **every** store the module holds, with `Survives` or `AwaitsExpiry` where nothing is removed;
- throws on failure rather than returning a partial report: `SubjectErasure` records the throwable and carries on
  with the other modules;
- ends within the lifetime of the lock it runs under, five minutes for a request without an account, fifteen for an
  account deletion: a worker that takes over a lost lock runs every eraser again, which idempotence makes safe
  ([Locks and long work](export-and-erasure.md#locks-and-long-work)).

```php
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;

final readonly class BillingEraser implements PersonalDataEraser
{
    public function __construct(private Connection $db) {}

    public function module(): ModuleName
    {
        return ModuleName::fromString('billing');
    }

    public function erase(DataSubject $subject): ErasureReport
    {
        // Idempotent: on an already pseudonymised row this sets the same values again.
        $this->db->executeStatement(
            'UPDATE invoices SET customer_name = NULL, customer_email = NULL WHERE customer_id = ?',
            [$subject->value],
        );

        return ErasureReport::of('billing', [
            'invoices' => ErasureEffect::Survives,        // kept under a legal obligation, without identity
            'payment_logs' => ErasureEffect::AwaitsExpiry,
        ]);
    }
}
```

## `ExternalPersonalDataEraser`

One module's part of an erasure that an external service carries out, for the [erasure workflow](erasure-workflow.md).
Autoconfiguration tags it `phil_privacy.external_eraser`.

| Method | Contract |
|---|---|
| `module(): ModuleName` | The module's name, unique among every eraser, external or not. |
| `request(DataSubject $subject, string $idempotencyKey): void` | Sends the erasure with the step's key, which stays the same however many times the step runs: the service erases once for a key. Idempotent. Throws when the service refuses, and the step is given back. |

The step ends when the application hands the service's acknowledgement to `ErasureRequests::acknowledge()` with that
key.

## `ErasureRequestStore`

Keeps the erasure requests and their steps, for the [erasure workflow](erasure-workflow.md). The tables of the bundle
are the default; name another with `erasure_workflow.store`. A step changes state only through its methods, each of
which changes it only from the states it names, in one statement: `open()` adds the request and its steps in the
transaction of the outbox records; `lease()`, `complete()`, `await()`, `release()` and `flag()` move a step;
`acknowledge()` completes a step waiting under its idempotency key; `reopen()` puts back the steps that need attention or
wait for an acknowledgement; `settle()` sets the state of a request and never changes a closed one.

## `ErasureMessages`

`record(string $requestId, string $module, string $idempotencyKey): void` records the message of one module, in the
transaction `ErasureRequestStore::open()` runs, to be sent once it commits. The default, `OutboxErasureMessages`,
writes an outbox record of phil/outbox-bundle; alias another to the interface. A message carries the identifiers only,
and recording again under a key already used adds nothing.

## `RetentionScheduleSource`

Reads the application's retention schedule, wherever and however it is kept. Consumed by `RetentionRun`. A schedule
can also be written under `retention.rules`, without a source.

| Method | Contract |
|---|---|
| `load(): RetentionSchedule` | The current schedule. Construction errors of rules and periods surface here as `InvalidPrivacyValue`. |

Invariants: the identifiers are stable (declarations cite them); `load()` is called once per `RetentionRun::run()`.

```php
use Phil\PrivacyBundle\Application\Contract\RetentionScheduleSource;
use Phil\PrivacyBundle\Domain\Retention\{AccessResponse, ErasureEffect, Mechanism, RetentionPeriod, RetentionRule, RetentionSchedule};
use Phil\PrivacyBundle\Domain\Subject\DataCategory;
use Symfony\Component\Yaml\Yaml;

final readonly class YamlScheduleSource implements RetentionScheduleSource
{
    public function __construct(private string $path) {}

    public function load(): RetentionSchedule
    {
        $rules = [];

        foreach (Yaml::parseFile($this->path) as $id => $line) {
            $rules[] = new RetentionRule(
                (string) $id,
                $line['store'],
                array_map(DataCategory::fromString(...), $line['categories']),
                isset($line['duration']) ? RetentionPeriod::fixed($line['duration']) : RetentionPeriod::untilEvent($line['until']),
                Mechanism::from($line['mechanism']),
                ErasureEffect::from($line['erasure']),
                AccessResponse::from($line['access']),
            );
        }

        return new RetentionSchedule($rules);
    }
}
```

(`symfony/yaml` is the application's dependency in this example, not the bundle's.)

## `RetentionPurger`

Purges one store of what has outlived its period. Consumed by `RetentionRun`.

| Method | Contract |
|---|---|
| `store(): string` | The store this purger answers for, exactly as the schedule names it. Unique across purgers. |
| `purge(PurgeOrder $order): int` | Removes the records of `$order->store` older than `$order->cutoff` that `$order->ruleId` covers; returns how many. |

Invariants: compare against the cutoff strictly ("older than"), in UTC; purge only what the rule covers when
several rules share a store; be safe to re-run (a second run on the same cutoff purges nothing more); end within the
hour of the job's lock, which is renewed between orders and not during one, purging a large store in batches. Whether
the purge is a `DELETE`, a partition drop or an object deletion is the adapter's choice.

```php
use Phil\PrivacyBundle\Application\Contract\RetentionPurger;
use Phil\PrivacyBundle\Domain\Retention\PurgeOrder;

final readonly class ClickPurger implements RetentionPurger
{
    public function __construct(private Connection $db) {}

    public function store(): string
    {
        return 'clicks';
    }

    public function purge(PurgeOrder $order): int
    {
        return (int) $this->db->executeStatement(
            'DELETE FROM clicks WHERE created_at < ?',
            [$order->cutoff->format('Y-m-d H:i:s')],
        );
    }
}
```

## `AccountDeletionStore`

Keeps account deletions between the request and the end of the delay. The table of the bundle is the default; name
another with `account_deletion.store`.

| Method | Contract |
|---|---|
| `find(string $id)` | The request, or `null`. A fresh copy: the request is read again once its lock is held. |
| `openFor(DataSubject $subject)` | The request of this person that is `requested` or `exported`, or `null`. |
| `save(AccountDeletion $deletion)` | Creates or replaces the row. |
| `dueIdentifiers(DateTimeImmutable $now, int $limit)` | The open requests whose `effectiveAt` has passed, oldest first. |

## `ExportArchive`

Keeps the export written before an erasure. The default is `EncryptedExportArchive`.

| Method | Contract |
|---|---|
| `put(string $reference, string $content, DateTimeImmutable $expiresAt)` | Stores the document, out of the logs. |
| `get(string $reference)` | The document, or `null`. |
| `purgeExpired(DateTimeImmutable $now)` | Removes what has expired, and says how many. |

## `ConsentProofs`

`record(ConsentChoice $choice)` keeps the dated proof of a choice. The record holds no address and nothing about the
person. Name another with `consent.proofs`.

## `CurrentDataSubject` and `DataSubjectUser`

`CurrentDataSubject::current()` is the member of the current request, or `null`. The default reads the signed-in user,
whose class implements `DataSubjectUser::dataSubjectIdentifier()`: a stable identifier, never an e-mail address. Name
another with `pages.current_subject`.

## `ExportReadme`

`render(array $modules, DateTimeImmutable $madeAt): string` writes the `README.txt` of an export archive, given the
number of top-level entries of each module's file. The default is `TwigExportReadme`, over
`@PhilPrivacy/export/README.txt.twig`, or `PlainExportReadme` without Twig. Alias another to the interface.

## `GuestRequestStore`

Keeps the requests made without an account while their link is valid. The table of the bundle is the default; name
another with `guest_requests.store`.

| Method | Contract |
|---|---|
| `save(GuestRequest $request)` | Adds the request. Keeps the address out of reach of a copy of the store. |
| `find(string $id)` | The request, or `null`, also when it cannot be read. |
| `delete(string $id)` | Removes it; nothing when it is gone. |
| `retain(string $id, DateTimeImmutable $until)` | Keeps a request whose erasure runs as a workflow until `$until`: its link no longer opens it, and it is purged at `$until` at the latest. |
| `purgeExpired(DateTimeImmutable $now)` | Removes those whose expiry has come, and says how many. |

## `GuestLinkSender`

`send(GuestRequest $request, string $token)` sends the link of a request to `$request->email`, built from the route
`phil_privacy_guest_confirm` with the parameters `id` and `token`. The token is the secret of the link: send it at once,
never through a message queue, and never write it in a log. The default is `MailerGuestLinkSender`; name another with
`guest_requests.sender`.

## `GuestSubjects`

`subjectOf(string $email): DataSubject` says which identifier the modules index the records of a verified address on.
The default, `EmailGuestSubjects`, is the address itself. Name another with `guest_requests.subjects`.

## `IncidentRegister`

The register of confidentiality incidents. The table of the bundle is the default; name another with
`incidents.store`.

| Method | Contract |
|---|---|
| `save(Incident $incident)` | Adds the line, or replaces the line of the same identifier. |
| `find(string $id)` | The line, or `null`. |
| `all()` | Every line, the most recently discovered first. |

A register that keeps its corrections also implements `IncidentHistory`, and `phil:privacy:incidents` prints them:

| Method | Contract |
|---|---|
| `revisions(string $id)` | What the line held before each correction, as `IncidentRevision` (the field, its previous value, when), the oldest first. |

A register that also purges, as a `RetentionPurger` on its own store, never removes a line known for less than
`Incident::MINIMUM_RETENTION`, whatever the cutoff it is given. See [incidents.md](incidents.md#keeping-it).

## `NoticeOrganisationSource`

Who gives the notices of an incident, which `phil:privacy:incident-notice` writes in its drafts. With
phil/legal-notices-bundle, the bundle reads the identity of the site; alias this interface to a service of your own to
give another.

| Method | Contract |
|---|---|
| `organisation()` | A `NoticeOrganisation`: the name, the Québec enterprise number, the person to contact and how to reach them, each null when not known; a null value leaves its rubric to complete. |

The drafts themselves are templates of `@PhilPrivacy/incident_notice/` and texts of the domain `phil_privacy`; see
[incident-notices.md](incident-notices.md#changing-the-drafts).

## `EmailTrackingStore`

Where each member's choice about tracked emails is kept, when `email_tracking.enabled`. The table of the bundle is the
default; name another with `email_tracking.store`. See [email-tracking.md](email-tracking.md).

| Method | Contract |
|---|---|
| `find(DataSubject $subject)` | The member's last choice, or `null` when they never made one. |
| `save(EmailTrackingChoice $choice)` | Keeps the choice in place of the member's earlier one; a choice that refuses both is kept too. |
| `forget(DataSubject $subject)` | Removes the member's choice; idempotent, since an erasure is replayed. |
| `store()` | The name of the store, as the erasure report of the module `email_tracking` names it. |

## `Anonymizer`

A column whose values no built-in type fits — a VAT number, a licence plate, an internal reference with a check
digit — takes an anonymizer of the application's:

```php
use Phil\PrivacyBundle\Domain\Anonymization\AnonymizationContext;
use Phil\PrivacyBundle\Domain\Attribute\AsAnonymizer;
use Phil\PrivacyBundle\Domain\Contract\Anonymizer;

#[AsAnonymizer('vat_number')]
final class VatNumberAnonymizer implements Anonymizer
{
    public function anonymize(string|int|float|bool $value, AnonymizationContext $context): string
    {
        return $context->fill($context->options->string('prefix', 'CA').'#########');
    }
}
```

With autoconfiguration, `#[AsAnonymizer]` tags the service `phil_privacy.anonymizer` with its type, and
`#[Anonymize('vat_number')]` or `tables: { invoice: { vat: vat_number } }` names it. A type the bundle already
answers, such as `email`, is replaced by the application's anonymizer.

The invariants an anonymizer keeps:

- **It is given non-null values only**, one row at a time; the run leaves a null value null without asking.
- **It reads its options through `$context->options`**, whose `string()`, `int()` and `scalar()` refuse a missing
  or mistyped option with its name. It throws `InvalidPrivacyValue` for an option or a value it cannot read: the
  run is a transaction, rolled back.
- **It draws from `$context->random`**, or through `pick()` and `fill()`, never from `random_int()` or `rand()`, so
  that `--seed` makes a run repeatable. A value derived from the original one goes through `$context->digest()`,
  under the run's salt.
- **It returns a value the column accepts**: a string for a text column, `null` only for a nullable one.

## `LocalePack`

A locale the bundle does not ship, or a pack of the application's own names, is a `LocalePack` service; with
autoconfiguration it is tagged `phil_privacy.locale_pack` and joins the built-in packs, replacing one of the same
code:

```yaml
services:
    app.locale_pack.fr_be:
        class: Phil\PrivacyBundle\Domain\Anonymization\Locale\LocalePack
        arguments:
            - fr_BE
            - [Lucie, Louis, Emma, Arthur]
            - [Peeters, Janssens, Maes, Dubois]
            - [de la Loi, Royale, Neuve, du Midi]
            - ['{street} {number}']
            - [Bruxelles, Liège, Namur, Charleroi]
            - [Wallonie, Bruxelles-Capitale]
            - ['####']
            - []                       # no fictional telephone range: the phone anonymizer needs a pattern
            - BE
            - Belgique
            - '{line}, {postal_code} {city}'
```

Telephone patterns must be ranges the country's regulator keeps out of service; leave the list empty rather than
invent one.

## `AnonymizationTarget`

The port anonymization writes through, implemented over a Doctrine DBAL connection. An `AnonymizationTarget` gives
each table's primary key (`AnonymizationRefused::withoutKey()` when there is none), every row with the key and the
asked columns keyed by the names as given, writes one row's values by its key, and runs the work in one transaction.

## Services and tags you can rely on

| Id or tag | Defined by | Notes |
|---|---|---|
| `Phil\PrivacyBundle\Infrastructure\Monolog\RedactingProcessor` | the bundle | Private service; can be referenced, decorated or retagged from a compiler pass. |
| `monolog.processor` | MonologBundle | The tag the bundle puts on the processor. |
| `phil_privacy.exporter`, `phil_privacy.eraser` | the bundle | Given by autoconfiguration to every service implementing `PersonalDataExporter` or `PersonalDataEraser` (`PhilPrivacyBundle::EXPORTER_TAG`, `ERASER_TAG`). Do not add them by hand to an autoconfigured service: the module would be counted twice, and `MisconfiguredPrivacy::duplicateModule()` thrown. |
| `Phil\PrivacyBundle\Application\SubjectExport`, `SubjectErasure` | the bundle | Public services over those tags. |
| `Phil\PrivacyBundle\Application\Register\ProcessingRegister` | the bundle | Public service over `retention_schedule_source`, if any. |
| `phil:privacy:export`, `phil:privacy:erase`, `phil:privacy:register` | the bundle | Commands, registered with Symfony Console. |
| `phil_privacy.anonymizer` | the bundle | Given by `#[AsAnonymizer]` with its `type` attribute (`PhilPrivacyBundle::ANONYMIZER_TAG`). |
| `phil_privacy.locale_pack` | the bundle | Given by autoconfiguration to every `LocalePack` service (`PhilPrivacyBundle::LOCALE_PACK_TAG`). |
| `Phil\PrivacyBundle\Application\Anonymization\AnonymizationRun` | the bundle | Public service over the anonymizers and the packs. |
| `phil_privacy.database_identity` | the bundle | Private `DatabaseIdentity` of `database.connection`, which the production guard reads (`PhilPrivacyBundle::DATABASE_IDENTITY`). |
| `phil:privacy:anonymize` | the bundle | Command, registered with Console and Doctrine DBAL, when the connection is a service. |
| `phil_privacy.retention_purger` | the bundle | Given by autoconfiguration to every `RetentionPurger` service (`RightsServices::PURGER_TAG`). |
| `Phil\PrivacyBundle\Application\RetentionRun`, `RetentionJob` | the bundle | Public services, with a schedule. |
| `phil:privacy:retention`, `phil:privacy:deletions`, `phil:privacy:incident`, `phil:privacy:incidents` | the bundle | Commands, registered with Symfony Console. |
| `phil:privacy:incident-notice` | the bundle | Command, registered with Symfony Console when Twig and the translator are there. |
| `Phil\PrivacyBundle\Application\Erasure\ErasureRequests` | the bundle | Public service, when `erasure_workflow.enabled`. |
| `phil_privacy.external_eraser` | the bundle | Given by autoconfiguration to every `ExternalPersonalDataEraser` service (`ErasureWorkflowServices::EXTERNAL_ERASER_TAG`). |
| `phil:privacy:erasure` | the bundle | Command, when `erasure_workflow.enabled`. |
| `Phil\PrivacyBundle\Domain\Cookie\CookieInventory` | the bundle | Public service: the inventory of `cookies.inventory`, with the consent cookie. |
| `Phil\PrivacyBundle\Application\Export\ExportPackage` | the bundle | Public service: the archive of an access answer. |
| `Phil\PrivacyBundle\Application\Contract\IncidentRegister` | the bundle | Public alias of the register of incidents. |
| `Phil\PrivacyBundle\Application\GuestRequest\GuestRequests` | the bundle | Public service, when `guest_requests.enabled`. |
| `Phil\PrivacyBundle\Application\EmailTracking\EmailTrackingConsents` | the bundle | Public service, when `email_tracking.enabled`: what the sender of an email asks before it counts the opens or follows the clicks of a member. |
| `phil_privacy.guest_requests.per_client`, `phil_privacy.guest_requests.per_address` | the bundle | The two rate limiter factories of the requests without an account (`GuestRequestServices::PER_CLIENT`, `PER_ADDRESS`). |
| `Phil\PrivacyBundle\Infrastructure\Testing\AssertsDeclaredCookies` | the bundle | The PHPUnit trait that fails on an undeclared cookie. |
| `workflow.phil_privacy_account_deletion.*` | the bundle | The events of the account deletion; see [account-deletion.md](account-deletion.md). |
| `phil_privacy` | the bundle | The schedule, `PrivacySchedule::NAME`. |
| `@PhilPrivacy/` | the bundle | The templates, overridden in `templates/bundles/PhilPrivacy/`. |

An exporter, an eraser or a purger therefore takes part as soon as it is a service with autoconfiguration on; a service
registered without it is tagged by hand, once.

## Other extension surfaces

- **Your own redactor composition.** `ContextRedactor`, `UrlRedactor`, `CommandLineRedactor` and `Denylist` are
  public, final, and usable outside Monolog — for an error tracker's `before_send` hook, for instance.
- **Catching the bundle's errors.** Everything the bundle throws implements
  `Phil\PrivacyBundle\Domain\Exception\PrivacyError`, while keeping its SPL type (`InvalidArgumentException`,
  `LogicException`, `RuntimeException`).
