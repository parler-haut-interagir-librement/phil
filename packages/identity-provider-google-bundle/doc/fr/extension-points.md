<!-- phil-dev source-sha256=567a114c1c6ab497a80be4f7449b0798d90c58542195e34068f706908493fb95 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension ne sont stables qu'à partir de sa version 1.0. D'ici là, une version
mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Garder les comptes retenus ailleurs | Implémentez `Application\Contract\SubjectHolds` et donnez-lui un alias, ou pointez `risc.holds_pool` vers un bassin PSR-6 durable |
| Réagir à un événement de sécurité | Écoutez `Application\Event\SecurityEventReceived` |
| Appeler Google par un autre transport | Réglez `http_client` sur un service de client HTTP de Symfony ; phil/safe-http l'enveloppe toujours |
| Recevoir les événements de sécurité d'un autre fournisseur | Construisez un `SecurityEventReceiver` avec ses propres `TransmitterSettings` et son propre vérificateur, et routez un `RiscController` vers lui |
| Décider comment une identité est liée | Configurez le module `external` de phil/identity-provider-bundle : `register` et `link_verified_email` |

## Les comptes retenus

```php
namespace Phil\IdentityProviderGoogleBundle\Application\Contract;

interface SubjectHolds
{
    public function hold(string $provider, string $subject): void;

    public function release(string $provider, string $subject): void;

    public function isHeld(string $provider, string $subject): bool;
}
```

`CacheSubjectHolds` les garde dans un bassin PSR-6. Une application qui a sa propre table donne un alias à son
implémentation :

```yaml
services:
    App\Identity\DoctrineSubjectHolds: ~
    Phil\IdentityProviderGoogleBundle\Application\Contract\SubjectHolds: '@App\Identity\DoctrineSubjectHolds'
```

Le magasin doit être durable : un magasin qui oublie laisse un compte Google détourné se reconnecter.

## L'événement

`SecurityEventReceived` est envoyé une fois par événement d'un jeton d'événement de sécurité, après que le récepteur a
fermé les sessions et retenu ou libéré le sujet. Il ne porte que des identifiants :

| Propriété | Contient |
|---|---|
| `provider` | `google`, ou le fournisseur d'un autre émetteur |
| `type` | Le `SecurityEventType` |
| `accountId` | L'identifiant du compte lié ; nul quand le sujet n'est lié à aucun compte, ou que l'événement ne nomme aucun sujet |
| `reason` | Le `reason` de l'événement, tel que `hijacking` ou `bulk-account` ; nul s'il n'y en a pas |
| `sessionsClosed` | Les sessions fermées |

```php
use Phil\IdentityProviderGoogleBundle\Application\Event\SecurityEventReceived;
use Phil\IdentityProviderGoogleBundle\Domain\Risc\SecurityEventType;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class AskForReview
{
    public function __invoke(SecurityEventReceived $event): void
    {
        if (SecurityEventType::AccountCredentialChangeRequired === $event->type && null !== $event->accountId) {
            // Flag the account for review; a heavy task goes to a Messenger message carrying the identifier.
        }
    }
}
```

## Ce qui reste fermé

- Le flux lui-même — l'état, le *nonce*, PKCE, les vérifications du jeton d'identité — n'est pas configurable au-delà
  des options de [Configuration](configuration.md) : un flux plus faible n'est pas offert.
- La règle de l'adresse de confiance est fixe : une application qui ferait confiance à plus d'adresses laisserait un
  compte Google prendre le contrôle d'un compte qui n'est pas le sien.
- Aucun jeton de Google n'est conservé, et aucun jeton de rafraîchissement n'est demandé.
