# Authentication and scopes

## The firewall

The API firewall uses Symfony's `access_token` authenticator with the bundle's token handler, service
`phil_api_keys.token_handler`, and its token extractor, service `phil_api_keys.token_extractor`:

```yaml
security:
    firewalls:
        api:
            pattern: ^/api/
            stateless: true
            provider: app_users
            access_token:
                token_handler: phil_api_keys.token_handler
                token_extractors: [phil_api_keys.token_extractor]
                realm: api
```

Symfony's authenticator gives the rest: it is the firewall's entry point, it answers a request without a token and a
refused token `401` with the challenge of RFC 6750 and a `text/plain` body, and it loads the user through the
firewall's provider. Give `access_token.realm` the value of the bundle's `realm`, so that every challenge names the
same realm.

| `header` | The extractor reads |
|---|---|
| `Authorization` (the default), in any case | `Bearer <token>`, the scheme written `Bearer`, one or more spaces |
| another, such as `X-Api-Key` | the whole value, without spaces |

A request without a token is left to the firewall's other authenticators, or to the entry point. The handler verifies
the token by `ApiTokens::verify()`, with the address of the main request for its last use; a refused token fails the
authentication with `BadCredentialsException`, which never says why. The user is loaded by the token's owner through
the firewall's user provider; a user the provider no longer knows fails the authentication like a refused token.

## Organisations and applications

A token of another owner type is loaded by the user provider `owner_types` names for that type, instead of the
firewall's: the security token then holds the user object of the organisation or the application, with its roles.
A token of a type that no provider loads — a type removed from the configuration — opens nothing.

```yaml
security:
    providers:
        members: { entity: { class: App\Entity\Member, property: email } }
        organizations: { entity: { class: App\Entity\Organization, property: slug } }

phil_api_keys:
    owner_types:
        organization:
            provider: security.user.provider.concrete.organizations
            scopes: [projects:read, billing]
```

A controller tells them apart by the owner type the request carries, or by the class of the user.

## What a request authenticated by a token carries

| Where | What |
|---|---|
| Roles | `ROLE_API_SCOPE_` and each scope in capitals, every character but letters and digits an underscore: `projects:write` gives `ROLE_API_SCOPE_PROJECTS_WRITE`; and the owner's roles, unless `grant_user_roles: false` |
| Security token attributes | `_api_token_id`, the token's identifier, `_api_token_owner_type`, the type of its owner, and `oauth2_scope`, its scopes, the attribute Symfony's authenticator keeps scopes in |
| Request attributes | `_api_token_id`, `_api_token_owner_type` and `_api_token_scopes` |

`TokenAuthenticationListener` adds the roles and the identifier, on Symfony's `AuthenticationTokenCreatedEvent` and
`LoginSuccessEvent`; it leaves every authentication the bundle's handler did not make as it is.

With `grant_user_roles: false`, a token has only the roles of its scopes: an administrator's token is not an
administrator.

## Requiring a scope

```php
use Phil\ApiKeysBundle\Infrastructure\Attribute\RequiresScope;

#[RequiresScope('projects:read')]
final class ProjectController
{
    #[RequiresScope('projects:write')]
    public function update(): Response
    {
        // ...
    }
}
```

`#[RequiresScope]` goes on a class or an action and repeats; every scope named is required. The listener checks them
before the controller runs: a request nobody is authenticated on is answered `401`, even on a route `access_control`
leaves open; otherwise each scope goes through the voter. Without Symfony's security nobody can be authenticated, and
such a controller is answered `401`.

The voter answers `API_SCOPE` on a scope name anywhere else:

```php
use Phil\ApiKeysBundle\Infrastructure\Security\ScopeVoter;

$this->denyAccessUnlessGranted(ScopeVoter::ATTRIBUTE, 'projects:write');
```

```twig
{% if is_granted('API_SCOPE', 'billing') %}…{% endif %}
```

A request authenticated otherwise than by a personal token — a session, an access token of another handler — is
denied, unless `other_authentications: true` lets any signed-in user through. Nobody signed in is always denied.

Since the scopes are in `oauth2_scope`, Symfony's own `OAUTH2_SCOPE(projects:write)` attribute works too, with
`#[IsGranted]`; its denial is answered by the firewall with the `insufficient_scope` challenge.

## The answers

| When | Status | `WWW-Authenticate` |
|---|---|---|
| No token on a route that requires a user | `401` | `Bearer realm="api"` |
| A token refused — malformed, unknown, revoked, expired | `401` | `Bearer realm="api",error="invalid_token",error_description="Invalid credentials."` |
| No authentication on a `#[RequiresScope]` controller | `401` | `Bearer realm="api"` |
| A scope missing | `403` | `Bearer realm="api",error="insufficient_scope",scope="projects:write"` |

The `error_description` is Symfony's message for `BadCredentialsException`, translated in the request's locale; it is
the same for every refusal. The body of a `401` of the firewall is that plain text. With `phil/api-contract-bundle`
installed, the service `phil_api_keys.problem_responder` answers it as RFC 9457 problem details instead, named as the
firewall's entry point and the authenticator's failure handler:

```yaml
security:
    firewalls:
        api:
            # ...
            entry_point: phil_api_keys.problem_responder
            access_token:
                # ...
                failure_handler: phil_api_keys.problem_responder
```

Its challenge of a refused token is `Bearer realm="api",error="invalid_token"`. The `401` and `403` of
`#[RequiresScope]` are `UnauthorizedHttpException` and `AccessDeniedHttpException`, answered by the application's
error handling — problem details under the paths of `phil/api-contract-bundle`. No answer says why a token was
refused.
