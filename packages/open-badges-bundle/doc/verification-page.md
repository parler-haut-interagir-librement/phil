# Verification page

The route `phil_open_badges_verify` of `config/verification_routes.php`, at `/verify` under its prefix, is a page where
anyone checks a credential:

- pasted: a compact token, or the JSON of a document with its proof;
- or baked into a badge image, PNG or SVG, given as a file;
- with, optionally, the e-mail address or URL of whom the visitor expects it to be issued to.

The page answers whether the credential holds and shows the achievement's name and description, the issuer, the
period of validity, how it is secured, its status, and whether the identity given is the recipient's. It is a `200`
when the credential holds, a `422` when it does not — not verified, revoked or suspended, or issued to someone else —,
with the reason.

| Check | How |
|---|---|
| The proof | The issuer's key, or a key of `trusted_keys`, or an Ed25519 `did:key`, each only for the issuer it is under; with `remote_issuers` enabled, the key of another issuer's profile or `did:web` document, read through `phil/safe-http` and cached ([Other issuers](remote-issuers.md)). Nothing else is fetched. |
| The period | `validFrom` and `validUntil` at the clock's moment. |
| The status | Read from the application's `StatusLists` when the entry's list is one of its own; otherwise shown as not published by the site. |
| The recipient | The DID, or the salted hash of the e-mail address or URL, compared in constant time. |

The page stores nothing, keeps no session and sets no cookie; its answers are `Cache-Control: no-store` and
`Referrer-Policy: no-referrer`. An image or a text longer than `verification.max_upload` bytes is refused before it
is read.

## Limiting the checks

Each check canonicalizes a document within the bounds of `phil/open-badges`, which costs more than a page, and may
read the profile of another issuer when `remote_issuers` is enabled. With
`phil/rate-limit-bundle` registered, `verification.rate_limit` names one of its limiters, and every check — a `POST` to
the page — counts against it; showing the page does not.

```yaml
# config/packages/phil_rate_limit.yaml
phil_rate_limit:
    cache_pool: cache.rate_limits
    limiters:
        badge_checks: { policy: sliding_window, limit: 20, interval: '1 minute', key: ip }

# config/packages/phil_open_badges.yaml
phil_open_badges:
    verification:
        rate_limit: badge_checks
```

A check past the limit is answered `429 Too Many Requests` by `phil/rate-limit-bundle`, with `Retry-After` and its own
page. A limiter that bundle does not declare stops the build, as does a limit without that bundle. A rule the route
already holds in its `_rate_limit` default is kept as it is, and the configuration is then left aside.

## Layout and translations

The page extends `verification.layout`, which defines the blocks `title` and `content`; the default is a minimal page
of its own. Its texts are in the translation domain `phil_open_badges`, in English and French; the reasons a
credential is refused come from the library, in English.

To change the page itself, override `@PhilOpenBadges/verify.html.twig` in `templates/bundles/PhilOpenBadgesBundle/`;
it receives `layout` and `result`, a `CheckResult` or null before a check.
