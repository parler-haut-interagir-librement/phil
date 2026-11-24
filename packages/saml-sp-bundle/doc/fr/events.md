<!-- phil-dev source-sha256=0818e3f758dfa02031147428a79e9dde5eee25f6a72713e31df1461bf541c30e -->
# Événements et console

## Événements

Les événements sont envoyés au répartiteur d'événements de l'application, sous forme d'objets simples.

| Événement | Quand | Contient |
|---|---|---|
| `SamlLoginSucceeded` | Une personne s'est connectée. | La clé du fournisseur d'identité, la `SamlIdentity`, l'utilisateur. |
| `SamlAccountCreated` | Un compte a été créé à une première connexion, avant `SamlLoginSucceeded`. | La même chose. |
| `SamlLogoutReceived` | Le fournisseur d'identité a déconnecté une personne. | La clé, la `LogoutRequest` : identifiant de nom, index de session. |
| `SamlResponseRefused` | Une réponse ou un message de déconnexion a été refusé, ou le fournisseur d'identité était hors d'atteinte. | La clé, la raison, la classe de l'exception. |

La raison de `SamlResponseRefused` nomme ce qui a été refusé, jamais une personne ni une valeur du message : journalisez-la,
comptez-la, déclenchez une alerte à son sujet. Le navigateur apprend seulement que la connexion a été refusée.

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

La commande récupère de nouveau les métadonnées de chaque fournisseur d'identité, en contournant le cache, et montre son
identifiant d'entité, ses services de connexion et de déconnexion, son `validUntil`, et chaque certificat de signature —
sujet, début de son empreinte SHA-256, expiration et état —, puis les certificats du fournisseur de service. Elle échoue
quand des métadonnées ne peuvent être lues ou qu'un certificat expire dans les `--days` jours (30 par défaut) :
lancez-la depuis le planificateur de l'application, et un échec avertit avant que les connexions s'arrêtent.
