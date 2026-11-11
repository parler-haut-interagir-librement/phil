# Testing

## The library's tests

The suite `tests/Unit` covers the encodings, the keys, the JSON Web Tokens, the parts of a credential and their
checks, the JSON-LD processor feature by feature and each of its refusals, the canonicalization of datasets that only
their neighbourhood tells apart — and its independence from labels and order —, the Data Integrity proofs, the status
lists, the endorsements, the learner records, the baking into PNG and SVG and the verifier.

Two published vectors anchor the cryptography: the `eddsa-rdfc-2022` example of the W3C specification of the EdDSA
cryptosuites — its canonical form and its proof value are reproduced exactly — and the key pair of its examples as a
Multikey. The canonicalization also passes every evaluation test of the W3C RDFC-1.0 suite.

From the monorepo root:

```sh
vendor/bin/phpunit packages/open-badges/tests
```

The library is held to 100 % covered mutation score by Infection.

## Fuzzing

Three fuzz targets read inputs nobody wrote — a PNG, an SVG, a token or a document to verify — and must only ever
return or raise an exception of the library. `composer fuzz`, from the monorepo root, runs each for 30 seconds from
the seeds of `tests/Fuzz/corpus`.

## The benchmark

`tests/Benchmark/OpenBadgesBench.php` measures what a verification page and an issuer do: verifying a token and a
document, signing a document — where the canonicalization dominates —, and reading a baked PNG.

## Testing code that issues credentials

Sign with a fixed seed, `Ed25519Signer::fromSeed(str_repeat("\1", 32))`, and verify with its `verificationKey()` in a
`DidKeyMethods`: Ed25519 signatures are deterministic, so a credential signed twice with the same inputs is the same
bytes, and a test may compare it whole.
