# Configuration

Every key of `phil_identity_client`, with its default. Only `issuer`, `client_id` and one credential are required.

```yaml
phil_identity_client:
    firewall: main
    issuer: ~                         # required; https, or a loopback host for local development
    discovery_uri: ~                  # when the document is not under the issuer
    http_client: ~                    # the HTTP client service for the calls to the provider
    client_id: ~                      # required

    # exactly one of the three
    client_secret: ~                  # from a Symfony secret: '%env(OIDC_CLIENT_SECRET)%'
    client_key: ~                     # a JSON-encoded private JWK, for private_key_jwt
    public_client: false              # no secret, bound by PKCE alone

    client_secret_transport: basic    # basic | post
    client_key_algorithm: RS256
    scopes: [openid]
    claims: []                        # the claims handed to the UserProvisioner
    claims_source: id_token           # id_token | userinfo
    signature_algorithm: RS256        # the one algorithm accepted on ID tokens and logout tokens
    allowed_time_drift: 5
    authorization_params: {}          # extra parameters of each authorization request
    lock_factory: lock.factory

    paths:
        check: /oidc/callback
        start: /oidc/start
        landing: /                    # where a sign-in lands without a valid target
        failure: /                    # where a failed sign-in lands

    return_to:
        parameter: return_to
        origins: []

    session:
        max_lifetime: 43200           # seconds from the start of the session at the provider; 0 or null for none
        start_claim: session_start    # the ID token claim that dates it; auth_time when the token lacks it

    back_channel_logout:
        enabled: true
        path: /oidc/back-channel-logout
        replay_ttl: 86400
        cache: cache.security
        cold_start_floor:
            enabled: false            # refuse what predates the last time the pool was found empty
            not_before: ~             # a floor of its own, in seconds since the Unix epoch

    end_session:
        enabled: false                # sign out at the provider too, on local logout
        post_logout_path: /

    elevation:
        max_age: ~                    # sent on every authorization request
        retry_after: 60
        required_level: ~             # a level of `levels` with an acr, which every sign-in has to reach
        levels: {}

    refresh:
        enabled: false
        leeway: 30
        wait_timeout: 10
        lock_ttl: 30
        outcome_ttl: 120
        cache: cache.security

    tokens:
        keep_access_token: false
        keep_id_token: false
```

## Values of a network

The values a group of applications signed in by one provider has to agree on come from configuration, never from code:

| Key | What the provider and its clients agree on |
|---|---|
| `elevation.levels` | The `acr` values of the scale, weakest first |
| `elevation.required_level` | The level below which no application of the group signs anyone in |
| `session.start_claim`, `session.max_lifetime` | The claim that dates a session at the provider, and how long a session lives from it |
| `back_channel_logout.replay_ttl` | How long an accepted `jti` is remembered |
| `back_channel_logout.cold_start_floor.not_before` | An instant before which nothing is accepted, such as a key rollover |

An environment variable carries an integer with the `int:` processor:

```yaml
phil_identity_client:
    back_channel_logout:
        cold_start_floor:
            enabled: true
            not_before: '%env(int:OIDC_REPLAY_NOT_BEFORE)%'
```

## What is fixed

Some `oidc_login` options are not offered, because the bundle's guarantees rest on them:

| Option | Value | Why |
|---|---|---|
| `user_identifier_claim` | `sub` | The only claim stable and unique for a user at an issuer |
| `id_token_signature.required` | `true` | Logout tokens are verified by the same key set |
| `id_token_signature.algorithms` | `[signature_algorithm]` | One algorithm, and never a shared-key one |
| `pkce` | `S256` | Binds the code to the browser that asked for it |

A value set directly in `security.yaml` still wins. Doing so leaves the guarantees of this bundle.

## Secrets

`client_secret` and `client_key` accept any Symfony secret or environment variable, and are never read at compile time.
