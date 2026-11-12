<!-- phil-dev source-sha256=dbc73bceb2e4a0e37b01f4e02304252e0bc56b8523dbb1fc27747af66b160756 -->
# Configuration

Chaque clé de `phil_identity_client`, avec sa valeur par défaut. Seuls `issuer`, `client_id` et un identifiant de
connexion sont obligatoires.

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
        max_lifetime: 43200           # seconds; 0 or null for none
        start_claim: session_start

    back_channel_logout:
        enabled: true
        path: /oidc/back-channel-logout
        replay_ttl: 86400
        cache: cache.security

    end_session:
        enabled: false                # sign out at the provider too, on local logout
        post_logout_path: /

    elevation:
        max_age: ~                    # sent on every authorization request
        retry_after: 60
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

## Ce qui est fixé

Certaines options de `oidc_login` ne sont pas offertes, parce que les garanties du bundle en dépendent :

| Option | Valeur | Pourquoi |
|---|---|---|
| `user_identifier_claim` | `sub` | La seule revendication stable et unique pour un utilisateur chez un émetteur |
| `id_token_signature.required` | `true` | Les jetons de déconnexion sont vérifiés avec le même jeu de clés |
| `id_token_signature.algorithms` | `[signature_algorithm]` | Un seul algorithme, et jamais un algorithme à clé partagée |
| `pkce` | `S256` | Lie le code au navigateur qui l'a demandé |

Une valeur définie directement dans `security.yaml` l'emporte toujours. Vous sortez alors des garanties de ce bundle.

## Secrets

`client_secret` et `client_key` acceptent n'importe quel secret Symfony ou variable d'environnement. Ils ne sont jamais
lus à la compilation.
