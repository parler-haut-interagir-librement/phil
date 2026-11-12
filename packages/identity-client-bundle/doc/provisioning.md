# Provisioning

The bundle signs a user in on the `sub` claim alone. The application decides what that means in its own terms, through
one port:

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

Without a port of its own, the application gets `StatelessUserProvisioner`: a user known by its subject, with the
`ROLE_USER` role.

## The identity

`VerifiedIdentity` carries the issuer, the subject and the claims named in `claims`. Everything else the provider
asserted stops at the bundle.

An `email` only appears when `email_verified` is exactly `true`, and a `phone_number` only with
`phone_number_verified`. Linking an existing account therefore goes through `verifiedEmail()`, and an unverified
address cannot reach the code that would match it. Key the account on the issuer and the subject, so that two providers
never share one.

## What the session keeps

The security token keeps the provider session id, the authentication class, the proofs of authentication and the start
of the session. Tokens are kept only when something reads them:

| Token | Kept when |
|---|---|
| Refresh token | `refresh.enabled` |
| Access token and its expiry | `refresh.enabled` or `tokens.keep_access_token` |
| ID token | `refresh.enabled`, `end_session.enabled` or `tokens.keep_id_token` |

Set `keep_access_token` when the application calls an API on behalf of the user.
