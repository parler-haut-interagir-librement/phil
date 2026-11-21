# Step-up

An application can ask for a stronger or more recent authentication on the pages that need one.

## Assurance levels

```yaml
phil_identity_client:
    elevation:
        levels:              # weakest first
            password: { acr: 'urn:example:password' }
            two_factor: { acr: 'urn:example:two-factor' }
            confirm: { acr: 'urn:example:two-factor', max_age: 300 }
            recent: { max_age: 900 }
```

Each is granted as `OIDC_ASSURANCE:<name>`:

```php
#[IsGranted('OIDC_ASSURANCE:two_factor')]
public function payout(): Response
```

A level is reached by its class or a stronger one on the scale. An `acr` the scale does not list reaches nothing.
`max_age` compares the last authentication proof with the clock.

## The required level

`elevation.required_level` names a level every sign-in has to reach:

```yaml
phil_identity_client:
    elevation:
        required_level: password
        levels:
            password: { acr: 'urn:example:password' }
            two_factor: { acr: 'urn:example:two-factor' }
```

The level needs an `acr`. Each authorization request asks for its class and the stronger ones in `acr_values`; a
step-up to a weaker level, or to one without a class, asks for the required classes instead. The provider may still
answer with a weaker class: the `acr` it returns is checked, and a sign-in below the level fails with
`AssuranceLevelNotReachedException`, landing on `paths.failure`. An `acr` the scale does not list, or none, reaches
nothing.

A session below the level, such as one begun before the requirement was raised, is ended on its next request.
`LocalSessionEnded` reports it with `LocalSessionEndReason::AssuranceNotReached`.

## What the user sees

`AssuranceVoter` denies and asks the firewall for a re-authentication. The browser goes to the provider with `acr_values`
naming the level and the stronger classes, and `prompt=login`. On return, it lands on the page that asked.

A provider may answer without reaching the level. `ElevationAttempts` records that, and the page answers with a `403` for
`elevation.retry_after` seconds instead of sending the browser back in a loop. `ElevationRefused` reports it.

## Time alone

Symfony's own `IS_AUTHENTICATED_RECENTLY` and `IS_AUTHENTICATED_VERY_RECENTLY` work unchanged, on the `auth_time` of the
ID token. Their windows are `security.recent_authentication_lifetime` and
`security.very_recent_authentication_lifetime`.

`prompt=login` is a request. `elevation.max_age` puts `max_age` on every authorization request, and the authenticator
then refuses an ID token whose `auth_time` is too old.

## Scripts

A request that is not a page navigation (an `XMLHttpRequest`, or an `Accept` other than HTML) cannot follow a redirect to
a provider. It gets the challenge of RFC 9470:

```http
HTTP/1.1 401 Unauthorized
WWW-Authenticate: Bearer error="insufficient_user_authentication", error_description="A stronger authentication is required.", acr_values="urn:example:two-factor", max_age="300"
```
