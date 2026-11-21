# Erasure as a workflow

**State: implemented and tested.** Needs phil/outbox-bundle on the connection of the bundle, Symfony Messenger and the
clock. Off by default.

`phil:privacy:erase` and `SubjectErasure` run every module's eraser in one process, one after the other: a module that
fails makes the whole erasure fail, and running it again runs every module again. The erasure workflow runs each module
on its own instead: one step per module, one message per step, retried alone, finished once, with a progress page the
person can follow.

## Turning it on

```yaml
# config/packages/phil_privacy.yaml
phil_privacy:
    erasure_workflow:
        enabled: true
        lease: 900                          # seconds a worker holds a step, 1 to 86400
        operator_role: ROLE_PRIVACY_OPERATOR
        store: ~                            # an ErasureRequestStore service; null, the tables of the bundle

# config/packages/phil_outbox.yaml
phil_outbox:
    connection: default                     # the connection of phil_privacy's tables

# config/packages/messenger.yaml
framework:
    messenger:
        routing:
            Phil\PrivacyBundle\Application\Message\EraseModule: async
```

The container build fails when the workflow is on without phil/outbox-bundle, Messenger, the clock or a store. Once it
is on, the account deletions and the requests made without an account erase through it.

## What a request erases

The **erasure inventory** is the list of the modules that hold personal data and erase it, as their erasers declare
them: every `PersonalDataEraser` and every `ExternalPersonalDataEraser` of the container. Its **version** is a
fingerprint of the sorted module names and of whether each erases in place or through an external service, 16
hexadecimal digits.

A request keeps the version of the inventory it was opened with, and its modules are its steps: a module declared
later never changes what an open request erases, and a module removed later is seen instead of skipped. Opening a
request for named modules only refuses a module the inventory does not declare.

```php
$request = $erasures->open($subject);                          // every module
$request = $erasures->open($subject, ['forum', 'billing']);    // these only; an unknown name throws InvalidPrivacyValue
$request = $erasures->open($subject, null, $deletion->id);     // under a known identifier, opened once
```

## One message per module

Opening a request writes, in one transaction of the connection: the request, one pending step per module, and one
outbox record per module, of type `phil_privacy.erase_module`. A record carries the request's identifier and the
module's name, and nothing else; its idempotency key is `<request>:<module>`. If any of it fails, none of it is kept.

The outbox relays each record as an `EraseModule` message. Relayed to the event dispatcher, the bundle hands it to the
Messenger bus; with the Messenger bridge of the outbox, it reaches the bus directly. A record whose request is gone is
skipped, never dead: the codec throws `ForgottenRecord`.

## The states

Each step is a row unique for the pair of the request and the module:

| Step | Meaning |
|---|---|
| `pending` | Waiting for its message: recorded with the request, given back after a failed attempt, or put back by a replay |
| `running` | Leased to a worker that runs the module's eraser, or sent to an external service that has not acknowledged it |
| `completed` | The module erased the person; its report is kept, store by store |
| `needs_attention` | The module could not finish on its own; the reason says why |

The request's state is drawn from its steps, except `failed`:

| Request | Meaning |
|---|---|
| `running` | At least one module is pending or running, and none needs attention |
| `needs_attention` | At least one module needs attention |
| `completed` | Every module completed |
| `failed` | An operator closed the request, deciding it cannot be completed |

The reason of a step that needs attention is one of a closed list, so that it names no value and no exception:
`retries_exhausted`, `unknown_module` (the module is no longer declared) or `subject_unavailable` (the request made
without an account it came from is gone).

## Running a step

A worker that receives `EraseModule`:

1. leaves alone an unknown or closed request, and refuses a module the request does not name;
2. marks the step as needing attention when no eraser declares the module any more;
3. leases the step for `lease` seconds, when it is pending, needs attention, or runs under a lease that ran out. A
   completed step, a step leased to another worker and a step waiting for its acknowledgement are left alone: a message
   delivered twice and a request resumed never run a finished module again;
4. resolves the person: the identifier the request keeps, or, for a request made without an account, the address
   sealed in the request it came from, through `GuestSubjects`;
5. runs the eraser and completes the step with its report. A report filed under another module's name is a failure.

The lease is the worker's: choose it longer than the slowest eraser, or another worker runs the module again, which
erasers allow, being idempotent.

## Failures

A module that fails gives its step back, pending, with one more attempt counted, and the worker throws
`ModuleErasureFailed`, so that Messenger tries the message again. That exception names the request and the module,
never the module's own failure, which can quote the person's data: Messenger keeps the message of an exception in the
envelopes it sends to the retry and failure transports. The log line gives the class of the failure only.

`ErasureFailureListener` listens to `WorkerMessageFailedEvent`. While `willRetry()` is true, it waits; once Messenger
gives up, it marks the step, and so the request, as needing attention.

From there, an operator:

- **replays** the request once the cause is fixed: the steps that need attention or wait for an acknowledgement go back
  to pending, a message is sent for every pending step, and the request is running again. Replaying the message from
  the failure transport (`messenger:failed:retry`) also runs the step and brings the request back to running;
- **closes the request as failed**, a decision only an operator takes. A failed request runs nothing more.

```
bin/console phil:privacy:erasure 3f2a…                    # the request, module by module
bin/console phil:privacy:erasure 3f2a… --replay
bin/console phil:privacy:erasure 3f2a… --fail --confirm
```

## External services

A module whose erasure an external service carries out, such as a mailing service, implements
`ExternalPersonalDataEraser`:

```php
final class MailingEraser implements ExternalPersonalDataEraser
{
    public function module(): ModuleName
    {
        return ModuleName::fromString('mailing');
    }

    public function request(DataSubject $subject, string $idempotencyKey): void
    {
        $this->client->deleteContact($this->contactOf($subject), idempotencyKey: $idempotencyKey);
    }
}
```

The worker sends the erasure with the step's idempotency key, the same however many times the step runs, and the step
stays `running`, without a lease, until the service acknowledges it. The application hands the acknowledgement to the
workflow, from the webhook the service calls, after checking that the call comes from the service:

```php
$erasures->acknowledge($idempotencyKey, ErasureReport::of('mailing', ['contacts' => ErasureEffect::Deleted]));
```

`acknowledge()` returns false for a key no step waits under, or a report of another module. A step that never gets its
acknowledgement is sent again, with the same key, by a replay.

## The progress page

`GET <prefix>/erasure/requests/{id}` shows the request module by module; `GET <prefix>/erasure/requests/{id}.json`
gives the same as JSON:

```json
{
    "id": "3f2a…",
    "state": "running",
    "inventory_version": "9c1d0e7a52b64f13",
    "requested_at": "2026-11-20T10:00:00+00:00",
    "closed_at": null,
    "completed": 1,
    "total": 2,
    "modules": [
        {"module": "billing", "state": "completed", "reason": null, "completed_at": "2026-11-20T10:00:05+00:00",
         "effects": {"invoices": {"effect": "survives", "personal_data_left": true}}},
        {"module": "forum", "state": "pending", "reason": null, "completed_at": null, "effects": {}}
    ]
}
```

It is shown to the person the request is about, to a user granted `operator_role`, and, for a request made without an
account, to whoever holds the secret of its progress link, `?token=…`. Anyone else gets the same 404 as for a request
that does not exist. The answer is never kept by a cache, sends no referrer, and is not indexed. It never names the
person.

## Requests made without an account

With the workflow on, opening the link of an erasure request made without an account opens an erasure request instead
of running every module at once. The page that follows gives the progress link, with a secret of its own, of which
only a hash is kept.

The erasure request keeps the identifier of the request it came from, never the address. The messages carry the
erasure request's identifier only; the worker reads the address from the request it came from, whose address stays
sealed in its table. That request is kept, its link no longer opening it, until the erasure is closed, or for 90 days
at most (`GuestRequests::WORKFLOW_RETENTION`), and removed then: a step that runs after it is gone needs attention.

## Account deletions

With the workflow on, the erasure stage of an account deletion opens an erasure request under the identifier of the
deletion, once. The deletion stays at `exported` while the request runs, and the next sweep closes it once the request
completed. A request closed as failed leaves the deletion at `exported`, for the operator to decide.

## The tables

`phil_privacy_erasure_request` keeps one row per request; `phil_privacy_erasure_step` one row per module of a request,
its primary key the pair, its idempotency key unique. `PrivacySchemaListener` adds them to the schema the ORM generates,
so `doctrine:migrations:diff` writes the migration. Without the ORM, the migration is:

MySQL and MariaDB:

```sql
CREATE TABLE phil_privacy_erasure_request (id VARCHAR(32) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_bin`, subject VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_bin`, guest_request VARCHAR(32) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_bin`, inventory_version VARCHAR(16) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_bin`, state VARCHAR(16) NOT NULL, requested_at DATETIME NOT NULL, closed_at DATETIME DEFAULT NULL, access_hash VARCHAR(64) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_bin`, INDEX idx_phil_privacy_erasure_subject (subject), INDEX idx_phil_privacy_erasure_state (state, requested_at), PRIMARY KEY (id));
CREATE TABLE phil_privacy_erasure_step (request_id VARCHAR(32) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_bin`, module VARCHAR(64) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_bin`, state VARCHAR(16) NOT NULL, attempts INT DEFAULT 0 NOT NULL, lease VARCHAR(32) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_bin`, lease_until DATETIME DEFAULT NULL, idempotency_key VARCHAR(97) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_bin`, effects LONGTEXT DEFAULT NULL, reason VARCHAR(32) DEFAULT NULL, updated_at DATETIME NOT NULL, completed_at DATETIME DEFAULT NULL, UNIQUE INDEX uniq_phil_privacy_erasure_step_key (idempotency_key), PRIMARY KEY (request_id, module));
```

PostgreSQL:

```sql
CREATE TABLE phil_privacy_erasure_request (id VARCHAR(32) NOT NULL, subject VARCHAR(255) DEFAULT NULL, guest_request VARCHAR(32) DEFAULT NULL, inventory_version VARCHAR(16) NOT NULL, state VARCHAR(16) NOT NULL, requested_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, closed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, access_hash VARCHAR(64) DEFAULT NULL, PRIMARY KEY (id));
CREATE INDEX idx_phil_privacy_erasure_subject ON phil_privacy_erasure_request (subject);
CREATE INDEX idx_phil_privacy_erasure_state ON phil_privacy_erasure_request (state, requested_at);
CREATE TABLE phil_privacy_erasure_step (request_id VARCHAR(32) NOT NULL, module VARCHAR(64) NOT NULL, state VARCHAR(16) NOT NULL, attempts INT DEFAULT 0 NOT NULL, lease VARCHAR(32) DEFAULT NULL, lease_until TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, idempotency_key VARCHAR(97) NOT NULL, effects TEXT DEFAULT NULL, reason VARCHAR(32) DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (request_id, module));
CREATE UNIQUE INDEX uniq_phil_privacy_erasure_step_key ON phil_privacy_erasure_step (idempotency_key);
```

Every change of a step is one `UPDATE` that names the states it starts from, and the lease it holds when it holds one:
two workers never run the same step while its lease runs, on every platform, without locking rows.

A closed request is kept until a retention rule on the store `phil_privacy_erasure_request` removes it, with its steps;
a request that is not closed is never purged. It names the person by the identifier of their account only, and it is
what an erasure is replayed from after a restore. Its outbox records go with the outbox's own retention, and a record
whose request was purged first is skipped.
