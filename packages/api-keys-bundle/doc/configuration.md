# Configuration

Every option of `phil_api_keys`, with its default:

```yaml
phil_api_keys:
    # What every token starts with, before an underscore: 2 to 16 lowercase letters and digits starting with a
    # letter. Choose the application's name, so that secret scanners recognise its tokens.
    prefix: pat

    # The key of the HMAC-SHA-256 each token is kept as, at least 32 bytes, from the secrets vault. Required. An
    # ordered list rotates the key: the first hashes new tokens, the others, at most seven keys it replaced, still
    # verify the tokens issued under them.
    hash_key: '%env(API_KEYS_HASH_KEY)%'
    # hash_key: ['%env(API_KEYS_HASH_KEY)%', '%env(API_KEYS_PREVIOUS_HASH_KEY)%']

    # The scopes a token may hold, by name: a lowercase letter followed by up to 63 lowercase letters, digits, "_",
    # ".", ":" or "-". The description is for developers; the label, a translation key, is for members and defaults
    # to "scope.<name>".
    scopes:
        projects:read: { description: 'Read the projects', label: 'scope.projects_read' }
        projects:write: { description: 'Create and change projects' }

    # The owner types a token may act for besides "user", the members, whom the firewall's provider loads: by name, the
    # user provider that loads an owner of that type when its token authenticates, and the scopes its tokens may hold —
    # every declared scope when omitted or empty. None by default.
    owner_types:
        organization: { provider: security.user.provider.concrete.organizations, scopes: [projects:read] }
        application: { provider: App\Security\ApplicationProvider }

    # The translation domain of the scope labels.
    scope_translation_domain: phil_api_keys

    # The days a token lasts when its creator gives no expiry, and the most days any token may last.
    default_lifetime: 90
    max_lifetime: 365

    # The lifetimes, in days, the members' page offers; those past max_lifetime are left out.
    lifetime_choices: [7, 30, 90, 365]

    # The seconds within which a second use of a token is not written to its row.
    last_used_throttle: 300

    # What the row keeps of the address a token was last used from: null for nothing, "truncation" for the address
    # truncated by phil/ip-anonymizer (/24 and /48), or the id of a service implementing ClientAddressAnonymizer.
    last_used_address: null

    # Where tokens are kept: "doctrine", "memory" for tests, or the id of a service implementing ApiTokenRepository.
    store: doctrine
    entity_manager: default

    # Service id of the PSR-20 clock.
    clock: clock

    # The header the extractor phil_api_keys.token_extractor reads: "Authorization", with the Bearer scheme, or another
    # header holding the token alone, such as "X-Api-Key".
    header: Authorization

    # The realm of the WWW-Authenticate challenges of #[RequiresScope] and of the problem details; give the firewall's
    # access_token.realm the same value.
    realm: api

    # Whether a request authenticated by a token also has the roles of its owner, beside the roles of its scopes.
    grant_user_roles: true

    # Whether a request authenticated otherwise — a session — is granted every scope by the voter.
    other_authentications: false

    # The days an expired or revoked token is kept before phil:api-keys:purge-expired removes it.
    purge_retention: 30

    # The endpoint GitHub's secret scanning posts leaked tokens to; see Secret scanning.
    secret_scanning:
        github:
            enabled: false
            # Where GitHub publishes the keys it signs with, an https address.
            keys_url: 'https://api.github.com/meta/public_keys/secret_scanning'
            # Service ids of the HTTP client that fetches the keys and of the cache pool that keeps them.
            http_client: http_client
            cache: cache.app
            # The seconds the keys are kept, at least 60, and the fewest seconds between two fetches, which is also
            # how long a failed fetch is kept.
            keys_lifetime: 86400
            refresh_interval: 60

    # The page where members create and revoke their tokens.
    member_page:
        enabled: false
        template: '@PhilApiKeys/tokens.html.twig'
```

The container build fails on a prefix or scope name out of shape, a `default_lifetime` longer than `max_lifetime`,
`last_used_address: truncation` without `phil/ip-anonymizer`, the members' page without the form component, the
CSRF component and Twig, and the endpoint of GitHub's secret scanning without its dependencies or with a `keys_url`
that is not an https address. A key shorter than 32 bytes, a key given twice or more than eight keys fail when the
hasher is first built, the environment variables then read.

## Choosing the lifetimes

Every token expires: a forgotten token in an old script stops working on its own. Ninety days by default and a year
at most suit scripts members maintain; a shorter longest lifetime suits sensitive scopes. A rotation keeps the
lifetime the token had, within the longest one.

## Rotating the key

Put the new key first and keep the previous one: new tokens are hashed under the new key, and a presented token is
checked against every key, all of them computed whichever matches. Every token expires within `max_lifetime`, so the
previous key can be dropped that many days after the rotation, refusing nothing still valid.

## Letting sessions through

A controller that serves both the site, by session, and the API, by token, carries `#[RequiresScope]` for the API.
With `other_authentications: false`, a session is refused there; with `true`, any signed-in user who is not
authenticated by a token passes the scope checks, and the controller's other rules decide.
