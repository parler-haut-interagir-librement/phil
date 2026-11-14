# Integrations

The trail reads its context through ports, each with an adapter the bundle registers when the component it reads is
installed. An application that decides otherwise aliases the port to its own service; its alias wins.

| Port | Adapter | Registered when |
|---|---|---|
| `Application\Contract\TenantScope` | `TenancyTenantScope`, on `phil/tenancy-bundle`'s `TenantContext` | `phil/tenancy-bundle` is installed and registered |
| `Application\Contract\ActorResolver` | `SecurityActorResolver`, on the Security component's token storage | the Security component is installed and SecurityBundle registered |
| `Application\Contract\AuditNotifier` | `MessengerAuditNotifier`, on a Messenger bus | `dispatch_bus` names a bus |
| `Domain\Contract\FactExporter` | `JsonLinesFactExporter`, `OtlpLogExporter` on Symfony HttpClient | `feed` names one ([dashboards.md](dashboards.md)) |

The adapters of the tenant and the actor are registered when their class exists, and removed by the compiler pass
`ContextPass` when the service they read is not in the container.

## Changes of state from Symfony Workflow

With `workflow_segment` set, the bundle listens to `workflow.completed` and records each transition of a subject that
implements `Application\Contract\Auditable`, in that segment:

- the action `workflow.<workflow>.<transition>`, in lower case, every character an action refuses replaced by `_`,
  cut to 64 characters;
- the subject that `auditSubject()` returns, and the current actor when an `ActorResolver` gives one;
- in the payload, `workflow`, `transition`, `from` and `to`, the places joined by `,`: names of the workflow, never a
  value of the record.

```yaml
phil_audit:
    serializer: App\Audit\CanonicalSerializerV1
    workflow_segment: states
```

```php
use Phil\AuditBundle\Application\Contract\Auditable;
use Phil\AuditBundle\Domain\Chain\Subject;

final class Order implements Auditable
{
    public function auditSubject(): Subject
    {
        return Subject::of('order', $this->id->toString());
    }
}
```

A subject that does not implement `Auditable` is not recorded. The history of the order — `AuditLog::history()` —
then holds its changes of state beside every other event recorded about it. Recording needs a serializer: a
`workflow_segment` without one fails the container build.

## The current account from the Security component

`SecurityActorResolver` names the user of the token when it implements `Application\Contract\AuditActor`:

```php
use Phil\AuditBundle\Application\Contract\AuditActor;

final class User implements UserInterface, AuditActor
{
    public function auditActorId(): string
    {
        return $this->id->toString();   // stable and opaque, never the e-mail address
    }
}
```

A user that does not implement it is not named: its user identifier is often an e-mail address, which the trail never
holds. The resolver names the actor of the rows the bundle records itself — a workflow transition, an export of
`phil/audit-viewer`; the application gives the actor of its own rows in their `Facts`.

## The tenant from `phil/tenancy-bundle`

`TenancyTenantScope` reads the tenant the request, the message or the command acts in, and never changes it. A row
recorded without a tenant of its own is stamped with the current one, and `AuditLog` holds every read to it. Outside
any tenant, rows are recorded without one and the log is read whole. With `site_segments`, each tenant's rows also
go to a chain of its own, and `ChainStatus` shows a tenant its own segments only ([sites.md](sites.md)).

## Dispatch to dashboards with Messenger

With `dispatch_bus` naming a bus, each recorded row is dispatched as an `Application\Message\AuditRecorded` message
that carries the row's segment and sequence, and nothing it says. A handler that needs the row reads it back through
`AuditLog::row()`, so that no field of the trail travels on a transport:

```yaml
phil_audit:
    dispatch_bus: messenger.bus.default

framework:
    messenger:
        routing:
            Phil\AuditBundle\Application\Message\AuditRecorded: async
```

```php
#[AsMessageHandler]
final readonly class FeedSecurityDashboard
{
    public function __construct(private AuditLog $log, private Dashboard $dashboard) {}

    public function __invoke(AuditRecorded $message): void
    {
        $row = $this->log->row($message->segment, $message->sequence);

        if (null !== $row && str_starts_with($row->facts->action ?? '', 'account.')) {
            $this->dashboard->count($row->facts->action, $row->recordedAt);
        }
    }
}
```

The message is dispatched once the row is in the trail, after a race retried. Routed to an asynchronous transport, a
dashboard is fed without slowing the request that recorded the row; on the synchronous bus, a handler's failure
reaches the caller of `record()` with the row already written.

With a `feed` configured as well, the bundle registers its own handler, `FeedOnRecorded`, which sends the facts of
the message's segment from the feed's place: the handler above is for what the feed does not do, such as counting in
the application itself ([dashboards.md](dashboards.md)).
