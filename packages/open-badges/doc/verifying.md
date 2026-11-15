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
   Web Key the token carries, which anyone can put there. The algorithm is the key's: a token whose `alg` is another
   one — `none`, `HS256` over a public key, `RS256` for an Ed25519 key — is refused before its signature is read, and
   `web-token/jwt-library` checks the signature with that one algorithm only. A token that marks a header parameter
   critical (`crit`) or leaves its claims unencoded (`b64`) is refused. Before the library parses it, the token is
   bounded: `JwtVerifier::MAX_LENGTH` bytes (1 MiB), three parts, a header and claims nested `JwtVerifier::MAX_DEPTH`
   levels (64) at most. Then `nbf` and `exp` are checked at the moment given: the token is valid from `nbf` included
   to `exp` excluded.
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

To verify issuers of other kinds, the application implements `VerificationMethods` (see
[Extension points](extension-points.md)), fetching and caching their profiles as it decides.

## Issuers identified by `did:web`

An issuer identified by a `did:web` publishes its keys in its DID document, on its own site. `DidWebMethods` reads it
when a credential names one of its methods, and passes every other method to the methods it is given — the
application's keys and every `did:key` by default:

```php
use Phil\OpenBadges\Adapter\SafeHttpFetcher;
use Phil\OpenBadges\DataIntegrity\DidKeyMethods;
use Phil\OpenBadges\Did\DidWebMethods;
use Phil\OpenBadges\Verification\CredentialVerifier;

$methods = new DidWebMethods(new SafeHttpFetcher(), new DidKeyMethods($knownKeys));
$verified = new CredentialVerifier($methods)->verify($tokenOrJson);
```

The document of `did:web:example.org` is at `https://example.org/.well-known/did.json`; the one of
`did:web:example.org:issuers:42` at `https://example.org/issuers/42/did.json`; a port is written `%3A`
(`did:web:example.org%3A8443`). A host that is an IP address, `localhost` or a single name is refused, and so is a
segment `.`, `..`, or one holding an escaped slash.

The key of `did:web:example.org#key-1` is accepted when the document:

| Rule | Why |
|---|---|
| Has the `id` `did:web:example.org` | A document served for another identifier says nothing of this one. |
| Lists the method in `verificationMethod`, or embeds it in `assertionMethod`, as `#key-1` or whole | The method is the issuer's. |
| Names it in `assertionMethod` | The issuer signs credentials with it, not only signs in. |
| Gives `controller` as `did:web:example.org` | Nobody else controls the key. |
| Holds the key as a `publicKeyMultibase`, an Ed25519 Multikey, or a `publicKeyJwk`: Ed25519 (`OKP`) or RSA of 2048 to 8192 bits | The algorithms of Open Badges 3.0; a document that publishes a private key is refused. |
| Is a JSON object of 256 KiB and 16 levels at most | A document is read within bounds. |

`SafeHttpFetcher` reads the document through `phil/safe-http`: over HTTPS only, a host on a private network refused,
each redirect checked and kept on HTTPS, within 5 seconds of silence and 10 in all, 256 KiB at most, and only when it
answers 200 whole. It identifies itself as `PhilOpenBadges/0.1`; a client of `phil/safe-http` with other limits is given
to its constructor.

Each verification reads the document again: `DidWebMethods` keeps nothing between two calls, so it is safe in a
long-running worker. An application that verifies often decorates its `DocumentFetcher` with a cache, the documents
kept a few minutes, and calls `reset()` of `SafeHttpFetcher` between the messages of a worker. A document that cannot
be read, or that does not give the key, raises `InvalidCredential` with the reason; the credential does not verify.

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
