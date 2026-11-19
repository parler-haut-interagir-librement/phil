# Audit and privacy

## The events

Every change and every read is a `SecretEvent`: its `kind`, the `owner`, the secret's `name`, the time `at` from the
clock, and for a read the task's `purpose`. It never carries a value.

| Kind | When |
|---|---|
| `stored` | A value was stored, new or replacing the previous one |
| `revealed` | A task of the owner read the value |
| `refreshed` | An expired OAuth token was refreshed and the new one stored |
| `revoked` | The owner revoked the secret and its value was wiped |
| `removed` | The secret's row was removed |
| `erased` | The secret was removed with the rest of its owner's secrets |
| `rekeyed` | The value was sealed again under the current key |
| `revoked_at_service` | The service that issued the OAuth token revoked it, after it was revoked, removed or erased here |
| `service_revocation_failed` | The service did not revoke the OAuth token; it is gone here all the same, and the failure was logged |

Events are handed out after the change is saved; the events of the revocation at the service come after the event of
the local change.

## Writing an auditor

An auditor implements `SecretAuditor`; autoconfiguration tags it, and every auditor hears every event:

```php
use Phil\UserSecretsBundle\Application\Audit\SecretEvent;
use Phil\UserSecretsBundle\Application\Audit\SecretEventKind;
use Phil\UserSecretsBundle\Application\Contract\SecretAuditor;

final readonly class NotifyOwnerOfNewKey implements SecretAuditor
{
    public function record(SecretEvent $event): void
    {
        if (SecretEventKind::Stored === $event->kind) {
            // tell the owner a key was added to their account
        }
    }
}
```

An auditor that throws lets the exception through to the caller, after the change was saved.

## The logger auditor

With `log_events: true`, `LoggerAuditor` writes each event to the `logger` service at `info` level:

```text
User secret {secret} of {owner}: {event}.   {"event": "revealed", "owner": "…", "secret": "openai", "at": "…", "purpose": "…"}
```

The owner's identifier is personal data: route the channel where the application keeps its audit logs.

## Export and erasure with phil/privacy-bundle

With `phil/privacy-bundle` installed, the bundle registers `UserSecretsPrivacy` as the module `user_secrets`, the data
subject being the owner:

- **access and portability**: the export lists the person's secrets — name, service, kind and dates — and never a
  value, which would only travel in clear;
- **erasure**: every secret of the person is removed, store `phil_user_secret` reported `deleted`, then their OAuth
  tokens are revoked at the services an application's `TokenRevoker` serves; a service that fails does not stop the
  erasure, as [storing and reading secrets](secrets.md#revocation-at-the-service) explains. Running it again removes
  nothing more and reports the same.

The module implements the contracts `Phil\PrivacyBundle\Application\Contract\PersonalDataExporter` and
`PersonalDataEraser` of the privacy bundle, which finds it by their tags.

```sh
bin/console phil:privacy:export member-42
bin/console phil:privacy:erase member-42 --confirm
```

Without the privacy bundle, call `UserSecrets::eraseOwner()` where the application deletes an account.
