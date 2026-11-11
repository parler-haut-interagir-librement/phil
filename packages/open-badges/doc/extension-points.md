# Extension points

The library has four ports, the interfaces of `Phil\OpenBadges\Contract`. Each has an implementation in the library;
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
it. `verify()` answers false for a signature of the wrong length rather than raising.

## `VerificationMethods`

Finds the key a proof or a token names:

```php
interface VerificationMethods
{
    public function key(string $verificationMethod): VerificationKey;   // InvalidCredential when unknown
}
```

`DidKeyMethods` knows the application's keys and every Ed25519 `did:key`. To verify credentials from issuers it does
not know in advance, an application implements this port: it may fetch the issuer's profile document, read its
`verificationMethod` — `Ed25519Key::fromMultibase()` decodes a Multikey —, check that the profile asserts with it, and
keep what it fetched. Fetching is the application's decision, with its own limits on hosts, sizes and time. Whatever
the port returns, `CredentialVerifier` accepts a key only for its own issuer (see [Verifying](verifying.md)).

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
