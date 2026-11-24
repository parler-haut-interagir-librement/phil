# Testing

The tests are PHPUnit tests in `tests/Unit`, run from the root of the monorepo with `composer test`, or on their own:

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

`tests/Integration` runs `DbalReplayGuard` on MySQL 8.4 and PostgreSQL 17, read from `PHIL_TEST_MYSQL_URL` and
`PHIL_TEST_POSTGRESQL_URL` — `composer test:services` at the root starts them —, and is skipped without them: the second
insert refused by the server, one winner of two connections, the purge, and an expiry written in another time zone read
in UTC. `tests/Unit` runs the same guard on SQLite in memory.

- The clock is Symfony's `MockClock`, one minute after the fixtures were issued, 2026-11-19 12:00 UTC.
- The cache pools are Symfony's `ArrayAdapter`, the HTTP client its `MockHttpClient`, with the host resolved to a public
  address, or to a private one to see it refused.

## Test keys

`tests/Fixtures/keys` holds RSA keys and self-signed certificates made for these tests only, with OpenSSL:

```sh
openssl req -x509 -newkey rsa:2048 -nodes -keyout entra.key -out entra.crt -subj "/CN=Test Entra ID signing" \
    -not_before 20260101000000Z -not_after 21260101000000Z -sha256
```

`entra` and `okta` sign as the two identity providers, `sp` signs and decrypts as the service provider, `attacker`
signs what must be refused; `weak` is a 1024-bit key and `expired` expired on 2021-01-01. They protect nothing: never
use them anywhere else.

## Fixtures

`tests/Fixtures/responses` holds responses shaped as Microsoft Entra ID and Okta send them — default namespaces and the
claim URIs for one, the `saml2` prefixes, an `Issuer` format and typed attribute values whose `xs` prefix only an
`InclusiveNamespaces` list keeps for the other —, signed and encrypted, and the attacks:

| Fixture | What it holds |
|---|---|
| `entra-signed-assertion.xml` | Microsoft Entra ID by default: the assertion signed, the response not. |
| `entra-signed-response-and-assertion.xml` | Both signed. |
| `entra-encrypted.xml` | The signed assertion encrypted in AES-256-CBC, the key in RSA-OAEP inside the data. |
| `entra-error-status.xml` | A `Responder` status, unsigned. |
| `okta-signed.xml` | The assertion signed with the `xs` prefix kept, then the response. |
| `okta-encrypted.xml` | AES-256-GCM, the key in `xmlenc11#rsa-oaep` on SHA-256 beside the data, the response signed. |
| `attack-xsw-extensions.xml` | The signed assertion moved into an extension, a forged one in its place. |
| `attack-xsw-duplicate-id.xml` | An element with the ID of the signed response planted beside it. |
| `attack-signature-moved.xml` | The signature of the response moved into the assertion. |
| `attack-modified.xml` | The name identifier changed after signing. |
| `attack-comment.xml` | A comment planted in the name identifier after signing. |
| `attack-attacker-key.xml` | Signed by another key, its certificate in `KeyInfo`. |
| `attack-unsigned.xml` | Nothing signed. |
| `attack-sha1.xml` | RSA-SHA1 with a SHA-1 digest. |
| `weak-key.xml` | Signed by the 1024-bit key. |

They are written by `tests/Fixtures/generate.php` — `php packages/saml-sp/tests/Fixtures/generate.php` from the root of
the monorepo — with the signer and the encrypter of `tests/Support`, written for the tests apart from the library's, so
that the verifier is tried on signatures the library did not write. Expiry, audience, recipient, destination and
replay cases are built by the tests from the same shapes.

## Fuzz target

`saml-xml`, in `tests/Fuzz/xml.php`, run by `composer fuzz`: the input goes to `SafeXml` within a budget of 8 KiB,
16 levels and 512 nodes, to the response processor as a posted field, to the metadata reader and to the reader of a
redirected query. Whatever it holds, each ends in a result or a `SamlException`. The seeds are in
`tests/Fuzz/corpus/saml-xml`.

## Benchmark

`tests/Benchmark/ResponseBench.php`, run by `composer bench`: a signed request on the redirect, a response whose
assertion is signed, one whose response and assertion are signed, and an encrypted assertion.
