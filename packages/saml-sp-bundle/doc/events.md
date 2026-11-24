# Events and console

## Events

The events are dispatched on the event dispatcher of the application, as plain objects.

| Event | When | Carries |
|---|---|---|
| `SamlLoginSucceeded` | A person signed in. | The key of the identity provider, the `SamlIdentity`, the user. |
| `SamlAccountCreated` | An account was created at a first sign-in, before `SamlLoginSucceeded`. | The same. |
| `SamlLogoutReceived` | The identity provider signed a person out. | The key, the `LogoutRequest`: name identifier, session indexes. |
| `SamlResponseRefused` | A response or a logout message was refused, or the identity provider could not be reached. | The key, the reason, the class of the exception. |

The reason of `SamlResponseRefused` names what was refused, never a person nor a value of the message: log it, count
it, alert on it. The browser only learns that the sign-in was refused.

```php
use Phil\SamlSpBundle\Application\Event\SamlResponseRefused;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final readonly class SamlRefusals
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    #[AsEventListener]
    public function __invoke(SamlResponseRefused $event): void
    {
        $this->logger->warning('SAML refused for {idp}: {reason}', ['idp' => $event->identityProvider, 'reason' => $event->reason]);
    }
}
```

## `phil:saml:check`

```sh
php bin/console phil:saml:check            # every identity provider
php bin/console phil:saml:check acme --days=45
```

The command fetches the metadata of each identity provider again, bypassing the cache, and shows its entity ID, its
sign-on and logout services, its `validUntil`, and each signing certificate — subject, the start of its SHA-256
fingerprint, its expiry and its state —, then the certificates of the service provider. It fails when metadata cannot
be read or a certificate expires within `--days` (30 by default): run it from the scheduler of the application, and a
failure warns before sign-ins stop.
