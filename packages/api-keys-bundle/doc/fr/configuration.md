<!-- phil-dev source-sha256=d748fc5b931289722ddef799755f33dc7c7296dc4f4fffb578237a72399a0cb4 -->
# Configuration

Chaque option de `phil_api_keys`, avec sa valeur par défaut :

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

La construction du conteneur échoue sur un préfixe ou un nom de portée mal formé, un `default_lifetime` plus long que
`max_lifetime`, `last_used_address: truncation` sans `phil/ip-anonymizer`, la page des membres sans le composant de formulaire, le
composant CSRF et Twig, et le point de terminaison de l'analyse de secrets de GitHub sans ses dépendances ou avec un
`keys_url` qui n'est pas une adresse https. Une clé de moins de 32 octets, une clé donnée deux fois ou plus de huit clés
échouent à la première construction du calculateur d'empreinte, au moment où les variables d'environnement sont lues.

## Choisir les durées de vie

Chaque jeton expire : un jeton oublié dans un vieux script cesse de fonctionner tout seul. Quatre-vingt-dix jours par
défaut et un an au plus conviennent aux scripts que maintiennent les membres; une durée de vie maximale plus courte
convient aux portées sensibles. Un renouvellement garde la durée de vie qu'avait le jeton, dans la limite de la durée
maximale.

## Renouveler la clé

Mettez la nouvelle clé en premier et gardez la précédente : les nouveaux jetons sont hachés sous la nouvelle clé, et un
jeton présenté est vérifié contre chaque clé, toutes calculées quelle que soit celle qui correspond. Chaque jeton expire
dans `max_lifetime` : la clé précédente peut donc être retirée ce nombre de jours après le renouvellement, sans refuser
rien de ce qui est encore valide.

## Laisser passer les sessions

Un contrôleur qui sert à la fois le site, par session, et l'API, par jeton, porte `#[RequiresScope]` pour l'API. Avec
`other_authentications: false`, une session y est refusée. Avec `true`, tout utilisateur connecté qui n'est pas
authentifié par un jeton passe les vérifications de portée, et les autres règles du contrôleur décident.
