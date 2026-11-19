# Secret scanning

A token pasted into a public repository is found within minutes, by people as by robots. GitHub's secret scanning
partner program finds it too, by the pattern of the application's tokens, and posts it to an endpoint the application
registers; the bundle's endpoint revokes it at once. The prefix and the checksum of the token format make the pattern
precise: a match is a token of this application, or a mistyped one the checksum already refuses.

## Enabling it

The endpoint is disabled by default. It needs `symfony/http-client`, `symfony/cache` and `phil/url`; the OpenSSL and
Sodium extensions, which verify the signature, are required by the bundle:

```sh
composer require symfony/http-client symfony/cache phil/url
```

```yaml
# config/packages/phil_api_keys.yaml
phil_api_keys:
    secret_scanning:
        github: true
```

```yaml
# config/routes/phil_api_keys.yaml
phil_api_keys_github_secret_scanning:
    resource: '@PhilApiKeysBundle/config/github_secret_scanning.php'
    prefix: /hooks/github/secret-scanning
```

The route answers `POST` only, stateless, outside any firewall that requires a user: GitHub signs its requests, it
does not sign in.

```yaml
# config/packages/security.yaml
security:
    firewalls:
        github_secret_scanning:
            pattern: ^/hooks/github/secret-scanning
            security: false
```

Then register with GitHub, through its secret scanning partner program, the pattern of the tokens — the prefix, an
underscore, 12 characters of `[0-9A-Za-z]`, an underscore and 38 more — and the address of the endpoint.

## What it does

| Request | Answer |
|---|---|
| Signed by GitHub, a list of matches | `200`, a label per match |
| No `Github-Public-Key-Identifier` or `Github-Public-Key-Signature`, a key GitHub does not publish, a signature that does not verify | `403`, the body not read |
| A body longer than 1 MiB | `413` |
| Signed, but not a list of matches of a token and a type | `400` |

The signature is ECDSA with SHA-256 over the raw body, in base64, under the key GitHub publishes with that identifier.
The body is read only once the signature verifies, and within bounds: 1 000 matches at most, a token of 512 bytes at
most, a type of 256.

Each match goes through `ApiTokens::revokeLeaked()`, which checks the text as `verify()` does — its shape, its
checksum, its row, its secret in constant time — without recording a use. A token of this application is revoked,
whatever its state; when this revokes it, `TokenRevoked` and then `TokenLeaked` are dispatched, each with the token's
identifier only, for a listener that tells the owner.

The answer lists each match by the SHA-256 of its token, in hexadecimal, with the type GitHub gave and a label:

```json
[{"token_hash": "3f9c…", "token_type": "acme_api_token", "label": "true_positive"}]
```

`true_positive` for a token of this application, `false_positive` otherwise. The answer never holds a token.

## The keys

`GitHubPublicKeys` fetches the keys from `keys_url`, GitHub's
`https://api.github.com/meta/public_keys/secret_scanning` by default, reads at most 64 KiB and 32 keys, leaves out a
key OpenSSL does not read as a public key, and keeps them in the cache pool named by `cache` for `keys_lifetime`
seconds, shared by every worker. A request naming a key the pool does not hold has the keys fetched again, since
GitHub rotates them, but at most once per `refresh_interval` seconds: requests naming unknown keys cannot make the
application call GitHub on each of them. A failed fetch — an error, a redirection, an answer too long or out of shape —
is kept for one `refresh_interval`, during which every request is answered `403`.

## Testing it

The test suite signs bodies with a key pair it makes and serves its public key through Symfony's `MockHttpClient`; an
application does the same with the service its `http_client` names. The two readers, of the report and of the keys,
are fuzz targets.
