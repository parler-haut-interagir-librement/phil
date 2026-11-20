# Deleting an account

`AccountDeletions` deletes an account in stages, on `symfony/workflow`: a request, a delay during which the person can
change their mind, an export, then the erasure of every module, through `SubjectExport` and `SubjectErasure`.

| Transition | From | To | When |
|---|---|---|---|
| `cancel` | `requested` | `cancelled` | During the delay, by the person. |
| `export` | `requested` | `exported` | Once the delay is over. |
| `erase` | `exported` | `erased` | Right after the export. |

The workflow is the service `phil_privacy.account_deletion_workflow`, named `phil_privacy_account_deletion`. Its events
are dispatched as usual, which is how an application answers a stage:

```php
#[AsEventListener('workflow.phil_privacy_account_deletion.completed.export')]
public function deliver(CompletedEvent $event): void
{
    $deletion = $event->getSubject();
    $document = $this->archive->get($deletion->exportReference());
    // Send it to the person: their account is erased when this listener returns.
}
```

## The person

A request designates a person by the opaque identifier every module indexes its records on (`DataSubject`): a UUID, an
OIDC `sub`, a customer number. **Never an e-mail address**: the request outlives the account, so that an erasure can be
replayed after a backup restore. The application's user class says which identifier designates it, by implementing
`DataSubjectUser`.

## What guarantees what

- **One execution per person.** Every stage runs under a lock of `symfony/lock`, named after a hash of the identifier,
  and the request is read again once the lock is held. A cancellation that wins the race is seen.
- **A stage stops when its lock is lost.** The lock lives 900 seconds and is renewed before each module of the export
  and of the erasure, before the archive is written and before each place is saved. A stage whose lock expired and was
  taken by another worker stops there and leaves the request at its last saved place
  ([Locks and long work](export-and-erasure.md#locks-and-long-work)).
- **A failure is resumed, not restarted.** The place is saved after each stage. An export is written once. An erasure
  that fails with `ErasureIncomplete` leaves the request at `exported`, to be run again; erasers are idempotent.
- **Messages carry identifiers.** `AdvanceAccountDeletion` carries the identifier of the request,
  `ProcessDueAccountDeletions` and `RunRetention` nothing at all. The export never travels in a message: it is the
  person's whole file, written to an `ExportArchive`, and what moves is its reference. The archive is the claim check,
  so no message needs Messenger's `ClaimCheckSerializer`.
- **Logs carry identifiers.** A line names the request and, on a failure, the modules. Never the person, and never the
  text of an exception, which a module could have filled with personal information.
- **Transports carry identifiers.** A failed erasure reaches Messenger as `AccountErasureIncomplete`, which names the
  request and the modules and chains no previous exception. Messenger copies the message of what a handler throws,
  and of every previous exception, into the `ErrorDetailsStamp` of the envelope it sends to the retry and failure
  transports: the exception of an eraser, such as a duplicate-key error quoting an address, never reaches them.

## Running it

The delay is checked by a sweep. With `symfony/scheduler` installed, the schedule `phil_privacy` runs it:

```bash
bin/console messenger:consume scheduler_phil_privacy
```

`ProcessDueAccountDeletions` sends one `AdvanceAccountDeletion` per request that is due, a batch of 100 at a time, and
removes the exports that have expired. Route those messages to a transport like any other: an erasure that fails throws,
so Messenger retries it, and the next sweep comes back to it anyway.

Without a scheduler, run `phil:privacy:deletions` from cron. It exits with a failure when an erasure failed, and names
requests by their identifier.

### A failure transport of its own

Give the privacy messages a transport and a failure transport of their own, so that their failures are kept apart
from the application's, for a short time, and removed once the request is closed:

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            privacy:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options: { queue_name: privacy }
                failure_transport: privacy_failed
                retry_strategy: { max_retries: 3, delay: 60000, multiplier: 4 }
            privacy_failed:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options: { queue_name: privacy_failed }
        routing:
            Phil\PrivacyBundle\Application\Message\AdvanceAccountDeletion: privacy
```

A message in `privacy_failed` holds the identifier of a request and the names of the modules that failed, and its
stamps. Remove it once the request is closed, whether a sweep or a retry erased the person since:

```bash
bin/console messenger:failed:remove --transport=privacy_failed 42 --force
```

and remove the rest on a short retention, from cron:

```bash
bin/console messenger:failed:remove --transport=privacy_failed --failed-before='-7 days' --force
```

The sweep still comes back to a request whose message was removed: the failure transport is a record of failures,
not the place the work waits in.

## The export

The export is the ZIP archive of an access answer, a `README.txt` and one JSON file per module
([export-and-erasure.md](export-and-erasure.md#the-archive-of-an-access-answer)). `EncryptedExportArchive` keeps it as a
`0600` file of a `0700` directory (`account_deletion.export_directory`), sealed with libsodium under a key derived from
the application secret, for `account_deletion.export_retention`. Each sweep removes those that have expired.

## The tables

`phil_privacy_account_deletion` holds the requests. `PrivacySchemaListener` adds it, with the tables of the consent
proofs, of the requests without an account and of the incidents, to the schema the ORM generates, so
`doctrine:migrations:diff` writes the migration. An application without Doctrine creates them from
`PrivacyTables::configure()`, or names its own `account_deletion.store`.

Identifiers, people, export references and token hashes compare byte for byte. On MySQL and MariaDB, whose default
collations fold case and accents, those columns take `utf8mb4_bin`: `PrivacySchemaListener` gives
`PrivacyTables::configure()` the platform of its connection, and an application that calls it itself passes the
platform as its second argument. Without it, the people `Alice` and `alice` would share a deletion request.

A closed request is kept until a retention rule on the store `phil_privacy_account_deletion` removes it: its identifier
is what an erasure is replayed from after a restore.
