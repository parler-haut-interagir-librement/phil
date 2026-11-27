# Mail per tenant

A tenant's mail can leave its own way: through its own SMTP server, from its own address, or — for a tenant without a
domain of its own — from the platform's address with the tenant's address as `Reply-To`. The bundle applies these
settings to every message sent in a tenant, through the Mailer component's own transports.

## Configuring it

```yaml
# config/packages/framework.yaml
framework:
    mailer:
        transports:
            main: '%env(MAILER_DSN)%'
            rose_smtp: '%env(ROSE_MAILER_DSN)%'

# config/packages/phil_tenancy.yaml
phil_tenancy:
    mailer:
        platform_sender: 'Shops <mail@platform.example>'
        tenants:
            rose:
                transport: rose_smtp
                sender: 'Rose <hello@rose.example>'
            fair:
                name: Summer Fair
                reply_to: fair.organisers@mail.example
```

| Key | Effect |
|---|---|
| `platform_sender` | The sender of the tenants without a domain of their own |
| `tenants.<tenant>.transport` | A transport named under `framework.mailer.transports` |
| `tenants.<tenant>.sender` | The tenant's own sender, on its own domain |
| `tenants.<tenant>.name` | The name its mail is sent under, its own sender's or the platform's |
| `tenants.<tenant>.reply_to` | Where replies go, for a tenant sent from the platform's address |
| `settings` | The id of an `Infrastructure\Mailer\Contract\TenantMailSettings` service, instead of `tenants` |

The resulting sender of a tenant:

| Tenant | `From` | `Reply-To` |
|---|---|---|
| `sender` set | The tenant's sender, under `name` when set | `reply_to` when set |
| No `sender`, `reply_to` or `name` set | `platform_sender`, under `name` when set | `reply_to` when set |
| `transport` only | The application's own sender | — |

Every address is checked as the container compiles; a tenant sent from the platform's address with no
`platform_sender` stops the compilation. Sending from the platform's domain keeps the mail under the SPF, DKIM and
DMARC records of a domain the platform controls, while replies still reach the tenant.

## How it works

`Infrastructure\Mailer\TenantTransports` decorates `mailer.transports`, the service both the mailer and its Messenger
handler send through. For a message sent in a tenant with settings, it adds:

| Header | When |
|---|---|
| `X-Transport` | The tenant has a `transport`; Symfony's transports read the header, send through that transport and remove it |
| `From` | The tenant has a sender; the message has no `From` |
| `Reply-To` | The tenant has a `reply_to`; the message has no `Reply-To` |

What the message already says wins: a header it carries is never replaced. A message sent in no tenant, or in a
tenant without settings, leaves as the application's. A message queued by Messenger is sent by the worker in the
tenant it was dispatched in, which `TenantMiddleware` restores on every bus, the one that carries `SendEmailMessage`
included ([integrations.md](integrations.md)).

The decorator holds no state: the tenant is read from the context at each message, and the transports' connections
are closed between requests and messages as Symfony closes them.

## Settings from a database

```php
final readonly class StoredTenantMail implements TenantMailSettings
{
    public function __construct(private Connection $database) {}

    public function for(TenantId $tenant): ?TenantMail
    {
        $row = $this->database->fetchAssociative('SELECT transport, sender, reply_to FROM tenant_mail WHERE tenant = ?', [$tenant->value]);

        return false === $row ? null : new TenantMail(
            $row['transport'],
            null === $row['sender'] ? null : Address::create($row['sender']),
            null === $row['reply_to'] ? null : Address::create($row['reply_to']),
        );
    }
}
```

```yaml
phil_tenancy:
    mailer:
        settings: App\Mail\StoredTenantMail
```

A transport a stored row names must still be declared under `framework.mailer.transports`: an unknown name makes
the send fail.
