# Federated providers

The module `external` links identities of federated providers — Google, GitHub, another OpenID Connect provider — to
accounts. One package per provider implements the port `ExternalProvider` and is tagged
`phil_identity_provider.external_provider`; the bundle keeps the subject the provider gives the person, never its
tokens.

## The port

```php
use Phil\IdentityProviderBundle\Application\ExternalIdentity;
use Phil\IdentityProviderBundle\Infrastructure\Federation\Contract\ExternalProvider;

final class ExampleProvider implements ExternalProvider
{
    public function name(): string { return 'example'; }
    public function label(): string { return 'Example'; }

    public function start(Request $request, string $callbackUrl): Response
    {
        // Redirect to the provider with a state and a PKCE challenge kept in the session.
    }

    public function finish(Request $request, string $callbackUrl): ExternalIdentity
    {
        // Check the state, exchange the code, read the identity; throw Refused::because('external.failed') otherwise.
        return new ExternalIdentity('example', $subject, $email, $emailVerified, $name);
    }
}
```

`/login/with/{provider}` calls `start()`; the provider comes back to `/login/with/{provider}/callback`, which the
firewall's `ExternalAuthenticator` answers by calling `finish()`.

## The rules

`ExternalIdentities` decides, in order:

1. an identity already linked signs its account in — and is refused with `external.linked_elsewhere` when someone else
   is signed in;
2. someone signed in links the identity to their own account;
3. an identity whose address an account already has is refused with `external.email_known`: the person signs in to
   that account and links the identity from it. Accounts are never merged on an address the provider did not verify,
   and on a verified one only when `external.link_verified_email` is on and the account verified it too;
4. otherwise, with `external.register`, a new account without a password is created and linked, its address verified
   when the provider verified it.

An external sign-in states no method: an account with an authenticator application is asked its code next.

## The page

`/account/identities` lists the identities linked to the account, offers the providers installed, and unlinks one after
a recent proof — never the last way to sign in of an account without a password nor a passkey.
