# Configuration

```yaml
# config/packages/phil_lti.yaml
phil_lti:
    store: dbal                      # dbal | memory
    connection: default
    table_prefix: phil_lti_
    clock: clock
    cache: cache.app
    http_client: ~                   # under the phil/safe-http clients; a client of its own when null
    message_bus: ~                   # scores sent at once when null
    allow_localhost: false
    kit_subdirectory: ~

    keys:
        source: dbal                 # dbal | config
        kms: Symfony\Component\KeyManagement\DataKeyGeneratorInterface
        master_key: lti
        lifetime: 7776000            # 90 days
        overlap: 604800              # 7 days
        bits: 2048
        private_key: ~               # source config
        passphrase: ~
        key_id: ~
        retired_public_keys: []

    tool:
        enabled: true
        state_lifetime: 600
        refused_score_retention: 2592000
        platforms:
            - issuer: 'https://lms.example.org'
              client_id: '%env(LTI_CLIENT_ID)%'
              authorization_url: 'https://lms.example.org/lti/authorize'
              token_url: 'https://lms.example.org/lti/token'
              key_set_url: 'https://lms.example.org/lti/jwks'
              token_audience: ~
              deployments: ['1']

    platform:
        enabled: true
        issuer: 'https://courses.example.com'
        token_url: 'https://courses.example.com/lti/platform/token'
        token_lifetime: 3600
        deep_linking_retention: 86400
        tools:
            - client_id: 'client-42'
              name: 'The quiz tool'
              login_url: 'https://tool.example.com/lti/login'
              redirect_urls: ['https://tool.example.com/lti/launch']
              key_set_url: 'https://tool.example.com/lti/jwks'
              deep_linking_url: ~
              scopes:
                  - 'https://purl.imsglobal.org/spec/lti-ags/scope/score'
                  - 'https://purl.imsglobal.org/spec/lti-nrps/scope/contextmembership.readonly'
              deployments: ['1']
```

## Common keys

| Key | Default | What it is |
|---|---|---|
| `store` | `dbal` | `dbal`: tables of a DBAL connection; `memory`: one process, for tests. |
| `connection` | `default` | The DBAL connection of DoctrineBundle the tables are in. |
| `table_prefix` | `phil_lti_` | The prefix of the eight tables: 1 to 40 lowercase letters, digits or `_`. |
| `clock` | `clock` | The service of the PSR-20 clock. |
| `cache` | `cache.app` | The service of the cache of the key sets and the access tokens. |
| `http_client` | null | The service of the Symfony HTTP client under the two `phil/safe-http` clients of the bundle — `phil_lti.service_http_client` for the token endpoint and the services, `phil_lti.key_set_http_client` for the key sets — which refuse private networks, keep the headers on the origin of the address and bound the time and the bytes of an answer. |
| `message_bus` | null | The service of the Messenger bus that sends the scores of the tool. |
| `allow_localhost` | `false` | Whether `localhost` and the names under `.localhost` are addresses: in development only. |
| `kit_subdirectory` | null | The components subdirectory of the kit's recipes; the Toolkit's when null. |

## Keys

| Key | Default | What it is |
|---|---|---|
| `keys.source` | `dbal` | `dbal`: keys made, rotated and sealed in the table; `config`: one private key from the environment. |
| `keys.kms` | `DataKeyGeneratorInterface` | The KMS of Symfony KeyManagement that seals the keys of the table. |
| `keys.master_key` | null | The master key of the KMS; required with `dbal` keys in a `dbal` store. |
| `keys.lifetime` | 90 days | How long a key signs before `phil:lti:rotate-keys --if-due` replaces it. |
| `keys.overlap` | 7 days | How long a retired key stays published. |
| `keys.bits` | `2048` | The bits of a new key, 2048 to 4096. |
| `keys.private_key` | null | The private key in PEM; required with `config` keys. |
| `keys.passphrase` | null | Its passphrase. |
| `keys.key_id` | null | Its identifier in the key set; its thumbprint when null. |
| `keys.retired_public_keys` | `[]` | Public keys in PEM of earlier rotations, still published. |

With the store `memory`, the keys come from the configuration whatever the source.

## The tool

| Key | Default | What it is |
|---|---|---|
| `tool.enabled` | `false` | Whether the application is a tool. |
| `tool.state_lifetime` | `600` | The seconds between the login initiation and the launch, 60 to 3,600. |
| `tool.refused_score_retention` | `2592000` | The seconds a score the platform refused for good is kept, as a trace, before `phil:lti:purge` removes it; a day at least. |
| `tool.platforms` | `[]` | The platforms the tool is registered with: issuer, client, authorization, token and key set addresses, token audience, deployments. |

## The platform

| Key | Default | What it is |
|---|---|---|
| `platform.enabled` | `false` | Whether the application is a platform. |
| `platform.issuer` | null | The issuer the tools register the platform by; required. |
| `platform.token_url` | null | The address of the token endpoint, as the tools register it: the audience of their assertions; required. |
| `platform.token_lifetime` | `3600` | The seconds an access token lives, 60 to 86,400. |
| `platform.deep_linking_retention` | `86400` | The seconds a verified deep linking response is kept, to be handled again when its handling failed, 600 to 2,592,000. |
| `platform.tools` | `[]` | The tools registered: client, name, login address, redirect addresses, key set, deep linking address, scopes of LTI Advantage, deployments. |

The platform side needs the application's `LaunchMessages`; the container stops with a message saying so when it is
missing.
