# Configuration

```yaml
phil_open_badges:
    issuer:
        id: https://example.org/open-badges/issuer    # required: the URL the profile is served at
        name: Example                                  # required
        url: https://example.org
        email: badges@example.org
        image: https://example.org/logo.png
    key:
        algorithm: EdDSA                               # EdDSA (a seed of 32 bytes) or RS256 (an RSA key in PEM)
        secret: '%env(base64:OPEN_BADGES_SEED)%'       # required
        passphrase: null
    securing: jwt                                      # jwt or data_integrity
    allow_localhost: false                             # whether the issuer's and the lists' addresses may name localhost
    clock: clock                                       # the PSR-20 clock
    max_age: 3600                                      # seconds the profile and the keys may be cached
    contexts:                                          # JSON-LD context documents the application provides, by URL
        'https://purl.imsglobal.org/spec/ob/v3p0/context-3.0.3.json': '%kernel.project_dir%/config/jsonld/ob-v3p0-context-3.0.3.json'
    trusted_keys:                                      # other issuers' Ed25519 keys the verifier accepts
        'did:web:partner.example#key-1': z6Mk…
    status_lists:
        service: Phil\OpenBadgesBundle\Application\Contract\StatusLists
        size: 131072                                   # bits per list, from 131072
        max_age: 300                                   # seconds a list is valid and may be cached
    verification:
        enabled: true                                  # also needs TwigBundle
        layout: '@PhilOpenBadges/layout.html.twig'
        max_upload: 2097152                            # the largest image or text checked, in bytes
        rate_limit: null                               # a limiter of phil/rate-limit-bundle the checks count against
    remote_issuers:
        enabled: false                                 # read the keys of other issuers from their profiles
        trusted: []                                    # the only issuers read, by identifier; any when empty
        cache: cache.app                               # the cache pool the keys read are kept in
        ttl: 3600                                      # seconds the keys of a profile are kept, 60 to 86400
        failure_ttl: 60                                # seconds a refused profile is not asked again, 1 to 3600
        timeout: 3.0                                   # seconds the issuer may keep silent, 0.1 to 60
        max_duration: 5.0                              # seconds the whole read may take, 0.1 to 120
        max_bytes: 65536                               # the largest profile read, 1024 to 1048576
        user_agent: 'PhilOpenBadges/0.1'               # one line: a name and a way to reach whoever runs the site
```

| Option | Checked when the container is built |
|---|---|
| `issuer`, `key` | `issuer.id`, `issuer.name` and `key.secret` are set; without them, the build stops and names those missing. |
| `issuer` | The identifier, the web site and the image are read by `phil/url` as a browser reads them: `http` or `https`, no user name or password, no IP address, no port 0, and no `localhost` unless `allow_localhost` is true; the name is not empty, the address is an address. |
| `verification.rate_limit` | A name of 1 to 64 letters, digits, `_`, `.` and `-`; PhilRateLimitBundle is registered; that bundle declares the limiter. |
| `securing: data_integrity` | The key is an EdDSA key. |
| `status_lists.size`, `max_age`, `verification.max_upload` | Their minimums. |
| `remote_issuers.trusted` | Each an HTTPS address without a fragment, read by `phil/url`, or a `did:web` that `DidWeb` of `phil/open-badges` reads; `localhost` refused for an address unless `allow_localhost` is true, and always for a `did:web`. |
| `remote_issuers.ttl`, `failure_ttl`, `timeout`, `max_duration`, `max_bytes`, `user_agent` | Their bounds; the User-Agent is one line without control characters. |

The issuer's verification method is `<issuer id>#key-1`: the `kid` of its tokens, the `verificationMethod` of its
proofs, the Multikey of its profile, the key of its JSON Web Key Set.

`contexts` maps a context's URL to the file that holds its document, a JSON object with an `@context`, of at most
1 MiB. The files are read when the Data Integrity suite is first used, and a file that is missing or not such a
document stops it there with the URL and the path. The contexts the application gives are read beside those
`phil/open-badges` bundles, and before them; no other URL is ever fetched.

`status_lists.service` names the application's `StatusLists`; when no such service exists, no list is served and the
page does not read statuses. With a single implementation of the interface loaded by resource, Symfony aliases the
interface to it, and the default finds it.

`remote_issuers` is described in [Other issuers](remote-issuers.md).
