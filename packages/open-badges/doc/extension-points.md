# Extension points

The library has five ports, the interfaces of `Phil\OpenBadges\Contract`. Each has an implementation in the library;
an application implements one for what the library leaves to it.

## `Signer`

The issuer's private key:

```php
interface Signer
{
    public function algorithm(): string;               // the JSON Web Algorithm: EdDSA, RS256
    public function sign(string $data): string;
    public function publicJwk(): array;                // the public key as a JSON Web Key
    public function verificationKey(): VerificationKey;
}
```

`Ed25519Signer` and `RsaSigner` hold the key in memory. A signer over a key management service — the private key never
leaving it — implements the interface and is given to `JwtSigner` and `CredentialIssuer`. Data Integrity proofs take
an `Ed25519Signer`.

## `VerificationKey`

A public key: `algorithm()` and `verify(string $data, string $signature): bool`. `Ed25519Key` and `RsaKey` implement
it. `verify()` answers false for a signature of the wrong length rather than raising. `Ed25519Key::fromJwk()`,
`Ed25519Key::fromMultibase()` and `RsaKey::fromJwk()` read the keys an issuer publishes.

## `VerificationMethods`

Finds the key a proof or a token names:

```php
interface VerificationMethods
{
    public function key(string $verificationMethod): VerificationKey;   // InvalidCredential when unknown
}
```

`DidKeyMethods` knows the application's keys and every Ed25519 `did:key`; `DidWebMethods` reads the keys of `did:web`
issuers from their DID documents, through a `DocumentFetcher`. To verify credentials from issuers of other kinds, an
application implements this port: it may fetch the issuer's profile document, read its `verificationMethod` —
`Ed25519Key::fromMultibase()` decodes a Multikey, `RsaKey::fromJwk()` a JSON Web Key —, check that the profile asserts
with it, and keep what it fetched. Fetching is the application's decision, with its own limits on hosts, sizes and
time. Whatever the port returns, `CredentialVerifier` accepts a key only for its own issuer (see
[Verifying](verifying.md)).

## `DocumentFetcher`

Reads the DID document of a `did:web` at its HTTPS address, for `DidWebMethods`:

```php
interface DocumentFetcher
{
    public function fetch(Url $url): string;           // InvalidCredential when it cannot be read
}
```

`SafeHttpFetcher` reads it through `phil/safe-http`. A decorator keeps the documents a while:

```php
use Phil\OpenBadges\Contract\DocumentFetcher;
use Phil\Url\Url;
use Symfony\Contracts\Cache\CacheInterface;

final readonly class CachedDocuments implements DocumentFetcher
{
    public function __construct(private DocumentFetcher $fetcher, private CacheInterface $cache) {}

    public function fetch(Url $url): string
    {
        return $this->cache->get('did.'.hash('xxh128', $url->toString()), function ($item) use ($url): string {
            $item->expiresAfter(300);

            return $this->fetcher->fetch($url);
        });
    }
}
```

A document that could not be read raises `InvalidCredential`, which the cache does not keep. A fetcher of the tests
serves documents from memory.

## `ContextLoader`

Gives the JSON-LD context document of a URL, with its `@context`:

```php
interface ContextLoader
{
    public function load(string $url): array;          // InvalidCredential when unknown
}
```

`BundledContexts` serves the contexts the library bundles and those given to its constructor:

```php
new EddsaRdfc2022(new BundledContexts(['https://example.org/contexts/v1' => $decodedContext]));
```

A loader that fetches contexts lets a document choose what its own terms mean, and makes every verification depend on
the network: bundle the contexts an application accepts instead.
