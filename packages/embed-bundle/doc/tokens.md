# Tokens

An embed token is the capability a partner's page carries: this embed, for this origin, in this theme, about this
subject, until then.

```text
pe1.hs256.2026-11.eyJlIjoiY2FsZW5kYXIiLCJvIjoiaHR0cHM6Ly9wYXJ0bmVyLmV4YW1wbGUiLCJpIjoxNzk0NTUwNDAwfQ.<signature>
```

## The format

| Part | Holds |
|---|---|
| `pe1` | The format and its version. |
| `hs256` | The algorithm: HMAC-SHA-256. |
| `2026-11` | The identifier of the key that signed it. |
| claims | JSON in base64url without padding. |
| signature | HMAC-SHA-256 of everything before it, in base64url without padding. |

The format's version and the algorithm lead the token and are signed with it: neither can be changed, nor downgraded
to another. A token of another format or algorithm is refused before anything else is read; the signature is compared
in constant time; a token is 2,048 bytes at most, and its claims a JSON object of scalar values.

| Claim | Is |
|---|---|
| `e` | The embed. |
| `o` | The origin, as `phil/url` writes it. |
| `i` | When it was issued, in seconds since 1970. |
| `t` | The theme, when one was named. |
| `s` | The subject, an identifier of the application, 1 to 200 bytes. |
| `x` | When it expires, when the embed has a `ttl`. |

## The keys

The keys are an ordered list: the first signs every new token, every key verifies the tokens that name it. To rotate:

1. put a new key first; new tokens are signed with it, the old ones still read;
2. give the partners new codes;
3. remove the old key: the tokens it signed stop reading.

```yaml
phil_embed:
    keys:
        - { id: '2027-01', secret: '%env(EMBED_KEY)%' }
        - { id: '2026-11', secret: '%env(EMBED_KEY_PREVIOUS)%' }
```

## What a copied token can do

A token sits in the partner's HTML, where anyone can read it. The origin it names is enforced by the browser through
`frame-ancestors`: on another site, the frame shows nothing, and the element keeps its link. Opened directly, outside a
frame, the page is the site's own page. A token grants nothing else: it does not sign anybody in, and the page decides
what it shows.

A token is checked again on every request: an origin the embed no longer allows, an embed removed from the
configuration, an expired token and a removed key are refused.
