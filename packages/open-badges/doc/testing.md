# Testing

## The library's tests

The suite `tests/Unit` covers the encodings, the keys, the JSON Web Tokens, the parts of a credential and their
checks, the JSON-LD processor feature by feature and each of its refusals, the canonicalization of datasets that only
their neighbourhood tells apart — and its independence from labels and order —, the Data Integrity proofs, the status
lists, the endorsements, the learner records, the baking into PNG and SVG, the verifier, the RSA keys read from JSON
Web Keys, and the resolution of a `did:web` — its address, each rule its DID document must follow, and the reading of
the document through `phil/safe-http` against a mock HTTP client.

Two published vectors anchor the cryptography: the `eddsa-rdfc-2022` example of the W3C specification of the EdDSA
cryptosuites — its canonical form and its proof value are reproduced exactly — and the key pair of its examples as a
Multikey. The canonicalization also passes every evaluation test of the W3C RDFC-1.0 suite.

Two tokens issued by version 0.1, in `tests/Fixtures`, keep the tokens stable from one version to the next: an
`EdDSA` token signed with that key pair, which still verifies and is signed again to the same bytes, and an `RS256`
token, which verifies with its public key.

## Test keys

The repository holds no private key that the tests could make. The RSA keys are made by OpenSSL while the tests run,
once per size for the run; one of 8192 bits takes seconds to make, so it is kept in the temporary directory, readable
by its owner only, for the other processes of the run.

The keys that must stay the same are written in the tests, each marked « test key, public, never trust it »: the
Ed25519 key pair of the W3C test vectors, which signs the `EdDSA` token of version 0.1, the fixed seeds of 32 bytes
that make issued credentials the same bytes on every run, and the public key of the `RS256` token of version 0.1. The
files of `tests/Fixtures` start with a line saying so. A credential signed by one of these keys proves nothing: never
trust them outside the tests.

From the monorepo root:

```sh
vendor/bin/phpunit packages/open-badges/tests
```

The library is held to 100 % covered mutation score by Infection.

## Fuzzing

Four fuzz targets read inputs nobody wrote — a PNG, an SVG, a token or a document to verify, a DID document — and
must only ever return or raise an exception of the library. `composer fuzz`, from the monorepo root, runs each for 30
seconds from the seeds of `tests/Fuzz/corpus`.

## The benchmark

`tests/Benchmark/OpenBadgesBench.php` measures what a verification page and an issuer do: verifying a token and a
document, signing a document — where the canonicalization dominates —, and reading a baked PNG.

## Testing code that issues credentials

Sign with a fixed seed, `Ed25519Signer::fromSeed(str_repeat("\1", 32))`, and verify with its `verificationKey()` in a
`DidKeyMethods`: Ed25519 signatures are deterministic, so a credential signed twice with the same inputs is the same
bytes, and a test may compare it whole.

To verify credentials of a `did:web` issuer in a test, give `DidWebMethods` a `DocumentFetcher` that serves the DID
document from memory: nothing reaches the network, and the test decides what the issuer's site answers.
