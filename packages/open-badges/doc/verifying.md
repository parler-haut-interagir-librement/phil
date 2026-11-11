# Verifying

## A credential

```php
use Phil\OpenBadges\DataIntegrity\DidKeyMethods;
use Phil\OpenBadges\Verification\CredentialVerifier;

$verifier = new CredentialVerifier(new DidKeyMethods(['https://example.org/issuer#key-1' => $publicKey]));
$verified = $verifier->verify($tokenOrJson, $clock->now());

$verified->credential;   // the credential, without its proof or registered claims
$verified->securedAs;    // 'jwt' or 'data-integrity'
$verified->issuer();     // the issuer's identifier
$verified->status();     // its StatusListEntry, or null
```

`verify()` takes a compact JSON Web Token or the JSON of a document with a Data Integrity proof:

1. A token: the key is the one its `kid` names, or the verifier's default method when it names none — never the JSON
   Web Key the token carries, which anyone can put there. The algorithm is the key's, the signature is checked, then
   `nbf` and `exp` at the moment given.
2. A document: the proof is a `DataIntegrityProof` of `eddsa-rdfc-2022` for `assertionMethod`, its verification method
   is found, its signature is checked over the canonical forms, then `validFrom` and `validUntil` at the moment given.
3. Either way, the key must be one of the credential's issuer: the verification method is the issuer's identifier, a
   fragment of it (`https://example.org/issuer#key-1` for the issuer `https://example.org/issuer`), or a method the
   verifier lists for that issuer. A token's `iss` claim must be the issuer too.

Without a moment, the period is checked at the current time. A credential that does not verify raises
`InvalidCredential`, with the reason. A token or a document longer than 1 MiB is refused before it is read.

`JwtVerifier` and `EddsaRdfc2022::verify()` are public for an application that knows how its credentials are secured.

## Verification methods

`DidKeyMethods` knows the keys the application gives it, by verification method identifier — its own issuer's, a
partner's — and every Ed25519 `did:key`, whose key is its identifier. It fetches nothing: a credential signed by a key
the application does not know is refused.

A known key signs only in its own issuer's name. A key is the issuer's when its verification method is under the
issuer's identifier, so a partner's key cannot issue in another issuer's name, and a self-made `did:key` signs only
for the issuer that is that `did:key`. A key published elsewhere, such as `https://keys.example.org/1` for the issuer
`https://example.org/issuer`, is listed for its issuer:

```php
$verifier = new CredentialVerifier($methods, controllers: [
    'https://example.org/issuer' => ['https://keys.example.org/1'],
]);
```
 To verify foreign issuers, the application implements
`VerificationMethods` (see [Extension points](extension-points.md)), fetching and caching their profiles as it decides.

## The recipient

A verified credential proves who issued it, not who presents it. `Recipient::identifies()` checks that the subject is
the person in front of the verifier: their DID, or the e-mail address or URL whose salted hash the credential holds,
compared in constant time.

```php
use Phil\OpenBadges\Recipient;

Recipient::identifies($verified->credential['credentialSubject'], $emailTheyGive);
```

## The status

A credential with a status entry can be revoked. `verify()` does not read the status, so a revoked credential still
verifies: see [Revocation](revocation.md) to read its status list.

## The JSON-LD subset

The processor reads the JSON-LD 1.1 that verifiable credentials use: contexts by URL from the loader, embedded
contexts, `@vocab`, `@propagate`, terms with `@id`, `@type`, `@container` (`@set`, `@list`, `@graph`), `@context` and
`@prefix`, type-scoped and property-scoped contexts, keyword aliases and compact IRIs, value objects with a type or a
language. It refuses rather than reads differently: `@import`, `@base`, a default `@language` or `@direction`,
reverse properties, nesting, index and language maps, JSON literals, lists of lists. `@protected` is accepted and not
enforced.

The contexts come from a `ContextLoader`; `BundledContexts` serves Verifiable Credentials 2.0, Open Badges 3.0.3 and
CLR 2.0.1, and those the application adds, and refuses every other URL.

Each document is bounded: nodes nest at most 32 deep, contexts load each other at most 8 deep, a document yields at
most 10,000 quads, and the canonicalization refuses a dataset that takes more than 2,000 n-degree hashes, or
permutes more than 5 indistinguishable blank nodes at once.
