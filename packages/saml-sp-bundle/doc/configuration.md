# Configuration

```yaml
phil_saml_sp:
    enabled: true
    entity_id: ~                       # the address of the metadata of each identity provider by default
    signing:                           # required unless sign_requests is false
        certificate: '%env(file:resolve:SAML_SP_CERTIFICATE)%'
        private_key: '%env(file:resolve:SAML_SP_PRIVATE_KEY)%'
        passphrase: ~
    encryption: []                     # key pairs that decrypt assertions, the current one first
    name_id_format: ~                  # e.g. urn:oasis:names:tc:SAML:2.0:nameid-format:persistent
    sign_requests: true
    clock_skew: 120                    # seconds, 0 to 600
    max_age: 600                       # seconds a response may be old, 30 to 86400
    allow_sha1: false
    min_rsa_bits: 2048
    allow_localhost: false             # development only
    bind_to_browser: true
    secure_cookie: true
    default_target_path: /
    logout_target_path: /
    cache_pool: cache.app
    replay_guard:
        store: auto                    # auto, doctrine or cache
        connection: default            # the Doctrine DBAL connection
        table: phil_saml_used_assertion
    single_server: false               # true accepts a cache pool local to the server in prod
    metadata_lifetime: 86400           # seconds, 60 to 2592000
    request_lifetime: 600              # seconds, 60 to 3600
    clock: clock
    http_client: ~
    accounts: ~                        # a SamlAccounts service
    identity_providers:
        acme:
            metadata_url: ~            # one of metadata_url, metadata_file, metadata_xml
            metadata_file: ~
            metadata_xml: ~
            metadata_certificates: []  # PEM certificates the metadata must be signed with
            entity_id: ~
            preset: none               # none, entra_id, okta, generic
            attributes: {}             # field: [attribute names], over the preset
            group_roles: {}            # group or role sent: role of the application
            default_roles: [ROLE_USER]
            want_assertions_signed: true
            want_assertions_encrypted: false
            allow_unsolicited: false
            binding: redirect          # redirect or post
            single_logout: true
            create_accounts: true
            force_authn: false
```

## The service provider

- `entity_id`: the entity ID the identity providers know the application by. By default each identity provider gets
  the address of its metadata route, `https://app.example.org/saml/acme/metadata`, so that each organisation registers
  its own application; set one value to share it.
- `signing`: the key pair of the signatures, in PEM. `%env(file:resolve:…)%` reads it from the file an environment
  variable names. A key with a passphrase gives it in `passphrase`.
- `encryption`: the key pairs that decrypt encrypted assertions; their certificates are published in the metadata. The
  signing pair may be given again here.
- `name_id_format`: the format the requests ask for. Microsoft Entra ID and Okta send their own default when none is
  asked.
- `sign_requests`: whether the requests are signed. Logout messages are always signed.

### Key rollover

1. Make the next key pair, and add it to `encryption` after the current one; the metadata then publishes both.
2. Ask each identity provider to import the metadata again.
3. Put the next pair in `signing` and first in `encryption`; remove the old one once no assertion encrypted for it can
   arrive.

## Security options

- `clock_skew`, `max_age`: see [Security](security.md#time).
- `allow_sha1`: accepts signatures with SHA-1, for an identity provider that cannot do better. Refused by default.
- `min_rsa_bits`: the smallest RSA key a signature may come from.
- `allow_localhost`: accepts addresses on `localhost`, and plain HTTP services in metadata, for an identity provider run
  on the developer's machine.
- `bind_to_browser`, `secure_cookie`: see [Security](security.md#the-browser).

## Where people go

- `default_target_path`: after a sign-in that named no `target`.
- `logout_target_path`: after the identity provider confirms a logout.

## Services

- `cache_pool`: the PSR-6 pool of the pending requests, the fetched metadata and, without a database, the accepted
  assertions. It is shared by every server and not cleared on deploy; a pool of its own, on Redis or a database, is the
  safe choice.
- `replay_guard`: where the accepted assertions are remembered, so that one posted again is refused.
  - `store`: `auto`, the default, takes the table of `DbalReplayGuard` on the Doctrine DBAL connection when the
    application has it — the service `doctrine.dbal.<connection>_connection` that DoctrineBundle defines — and
    `CacheReplayGuard` on `cache_pool` otherwise; `doctrine` requires the connection, and the container fails to build
    without it; `cache` takes the cache pool even with a connection.
  - `connection`: the name of the Doctrine DBAL connection, `default` by default.
  - `table`: the table of the accepted assertions, up to 63 lowercase letters, digits or `_`; a migration of the
    application creates it, see [Installation](installation.md#the-table-of-the-accepted-assertions).
- `single_server`: in the `prod` environment, the container fails to build when the accepted assertions are kept in a
  cache pool local to each server — the APCu, array, filesystem or system adapter, as `cache.app` is by default —; set
  it to `true` when the application runs on a single server. See [Security](security.md#replay).
- `metadata_lifetime`, `request_lifetime`: how long fetched metadata and a pending request are kept.
- `clock`: the PSR-20 clock.
- `http_client`: the client metadata is fetched with, wrapped by phil/safe-http.
- `accounts`: the service implementing `SamlAccounts`.

## Identity providers

The key — 1 to 64 lowercase letters, digits, `_` or `-` — is part of the routes of the identity provider.

- `metadata_url`, `metadata_file`, `metadata_xml`: where the metadata comes from, exactly one. An address is HTTPS,
  fetched through phil/safe-http and kept `metadata_lifetime` seconds; a file is read at each request.
- `metadata_certificates`: when set, the metadata must be signed with one of them.
- `entity_id`: the entity ID to read in metadata describing several, and checked otherwise.
- `preset`: the claim names of [Microsoft Entra ID](entra-id.md#claims), [Okta](okta.md#attribute-statements) or the
  LDAP and eduPerson schemas.
- `attributes`: for any of the fields `identifier`, `email`, `given_name`, `family_name`, `display_name`, `groups`,
  `roles`, the attribute names to read, in order; it replaces the names of the preset for that field.
- `group_roles`, `default_roles`: the roles of a `SamlUser` (see [Accounts and roles](accounts.md#roles)).
- `want_assertions_signed`: false accepts a signed response around an unsigned assertion.
- `want_assertions_encrypted`: true refuses an assertion that is not encrypted.
- `allow_unsolicited`: accepts a response the identity provider sends unasked, when a person opens the application
  from its portal; see [Security](security.md#unsolicited-responses).
- `binding`: the binding of the requests; HTTP-Redirect, or HTTP-POST for an identity provider that asks for it.
- `single_logout`: whether logout is carried to and from this identity provider.
- `create_accounts`: whether `SamlAccounts::create()` is called at a first sign-in.
- `force_authn`: asks the person to authenticate again at each sign-in.
