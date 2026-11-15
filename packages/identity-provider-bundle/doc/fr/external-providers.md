<!-- phil-dev source-sha256=2c0afd7e67f433111b5589f80da5917555087d49f99fc13b49a5aec78fe0c4b9 -->
# Fournisseurs fédérés

Le module `external` lie aux comptes des identités de fournisseurs fédérés — Google, GitHub, un autre fournisseur
OpenID Connect. Un paquet par fournisseur implémente le port `ExternalProvider` et est étiqueté
`phil_identity_provider.external_provider` ; le bundle garde le sujet que le fournisseur donne à la personne, jamais ses
jetons.

## Le port

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

`/login/with/{provider}` appelle `start()` ; le fournisseur revient à `/login/with/{provider}/callback`, auquel
l'`ExternalAuthenticator` du pare-feu répond en appelant `finish()`.

## Les règles

`ExternalIdentities` décide, dans l'ordre :

1. une identité déjà liée connecte son compte — et est refusée avec `external.linked_elsewhere` quand quelqu'un d'autre
   est connecté ;
2. quelqu'un de connecté lie l'identité à son propre compte ;
3. une identité dont un compte a déjà l'adresse est refusée avec `external.email_known` : la personne se connecte à ce
   compte et lui lie l'identité. Les comptes ne sont jamais fusionnés sur une adresse que le fournisseur n'a pas
   vérifiée, et sur une adresse vérifiée seulement quand `external.link_verified_email` est activé et que le compte l'a
   vérifiée aussi ;
4. sinon, avec `external.register`, un nouveau compte sans mot de passe est créé et lié, son adresse étant vérifiée
   quand le fournisseur l'a vérifiée.

Une connexion externe n'indique aucune méthode : un compte qui a une application d'authentification se voit demander son
code ensuite.

## La page

`/account/identities` liste les identités liées au compte, offre les fournisseurs installés, et en délie une après une
preuve récente — jamais la dernière façon de se connecter d'un compte sans mot de passe ni clé d'accès.
