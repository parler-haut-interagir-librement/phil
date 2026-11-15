# Keys

Each side signs with its own RSA key and publishes the public half in a key set; the other side verifies with it.

## The keys of this side

`KeyStore` gives the signing key and the key set to publish. `InMemoryKeyStore` holds a current key and the public keys
of earlier rotations:

```php
use Phil\Lti\Key\InMemoryKeyStore;
use Phil\Lti\Key\RsaPrivateKey;
use Phil\Lti\Key\RsaPublicKey;

$keys = new InMemoryKeyStore(
    RsaPrivateKey::fromPem($pemFromTheEnvironment, keyId: '2026-11'),
    [RsaPublicKey::fromPem($previousPublicPem, '2026-08')],
);

$keys->signingKey();               // signs every token of this side
$keys->publicKeys()->toJson();     // the key set to publish
```

A key has 2048 to 8192 bits. Without a key identifier, it is named by its RFC 7638 thumbprint. A key is a JSON Web Key
of `web-token/jwt-library`: `RsaPrivateKey::jwk()` and `RsaPublicKey::$jwk` give it to code that uses the library
directly. `RsaPrivateKey::fromJwk()` reads a private JSON Web Key, `fromPem()` a key in PEM, encrypted or not;
`RsaPrivateKey::generate()` makes a new one; `toJwk()` and `toJson()` write it for a store that seals it, and
`fromJson()` reads the JSON back, 64 KiB and four levels deep at most.

## Rotation

To rotate: make a new key and sign with it; keep publishing the previous public key for an overlap longer than the life
of a token — five minutes for a launch, more for a cached key set of the other side, an hour by default —; then remove
it. `phil/lti-bundle` does this in a table, the private keys sealed by a key management service.

## The keys of the other side

`KeySetSource` reads the other side's key set by its address. `SafeHttpKeySetSource` reads it through `phil/safe-http`:
HTTPS only, private networks refused, redirects checked, 256 KiB and ten seconds at most. `CachedKeySetSource` keeps it
an hour by default. When a token names a key the cached set does not hold — the other side rotated —, `JwtVerifier`
asks for the set again once; the cache reads it at most once a minute per address, so that tokens naming unknown keys
cannot make the side read the set at every request.

A key set keeps the RSA keys for `RS256` and skips the others; it holds 32 keys at most.
