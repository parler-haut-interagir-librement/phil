<!-- phil-dev source-sha256=c538e88da937442866ea4cd8144ab36af19cab8a081f994343f22903dbfffebe -->
# Approvisionnement

Le bundle connecte un utilisateur sur la seule revendication `sub`. L'application décide de ce que cela signifie dans ses
propres termes, par un seul port :

```php
use Phil\IdentityClientBundle\Application\Contract\UserProvisioner;
use Phil\IdentityClientBundle\Application\Identity\VerifiedIdentity;

final class AccountProvisioner implements UserProvisioner
{
    public function provision(VerifiedIdentity $identity): UserInterface
    {
        // find or create the account of ($identity->issuer, $identity->subject)
    }

    public function find(string $subject): ?UserInterface
    {
        // the account on a later request; null ends the session
    }
}
```

```yaml
services:
    Phil\IdentityClientBundle\Application\Contract\UserProvisioner: '@App\Security\AccountProvisioner'
```

Sans port à elle, l'application reçoit `StatelessUserProvisioner` : un utilisateur connu par son sujet, avec le rôle
`ROLE_USER`.

## L'identité

`VerifiedIdentity` porte l'émetteur, le sujet et les revendications nommées dans `claims`. Tout le reste de ce
qu'affirme le fournisseur s'arrête au bundle.

Un `email` n'apparaît que si `email_verified` vaut exactement `true`, et un `phone_number` seulement avec
`phone_number_verified`. Lier un compte existant passe donc par `verifiedEmail()`, et une adresse non vérifiée ne peut
pas atteindre le code qui la ferait correspondre. Indexez le compte sur l'émetteur et le sujet, pour que deux
fournisseurs n'en partagent jamais un.

## Ce que garde la session

Le jeton de sécurité garde l'identifiant de session du fournisseur, la classe d'authentification, les preuves
d'authentification et le début de la session. Les jetons ne sont gardés que si quelque chose les lit :

| Jeton | Gardé quand |
|---|---|
| Jeton de renouvellement | `refresh.enabled` |
| Jeton d'accès et son expiration | `refresh.enabled` ou `tokens.keep_access_token` |
| Jeton d'identité | `refresh.enabled`, `end_session.enabled` ou `tokens.keep_id_token` |

Activez `keep_access_token` quand l'application appelle une API au nom de l'utilisateur.
