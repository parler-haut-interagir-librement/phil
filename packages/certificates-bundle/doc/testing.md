# Testing

## The bundle's tests

`tests/Unit` tests the value objects and entities at their bounds, the courses, the awarding — whose credentials are
verified with the issuer's key —, the revocation, the status lists, the drawing of the PDF against a reference of its
operators and the embedding of the credential through a Gotenberg played by `MockHttpClient`, and the wiring on the
`ContainerBuilder`. A reader of the QR code in `tests/Unit/Support/QrReader.php` rebuilds the modules from the PDF's
rectangles and decodes them as a camera would — format, mask, codewords, segments —, so the tests read the address the
printed code opens. The end of courses and certificates, the reminders at each threshold, the recertification by credits
or attainment and its refusals, and the sums of credits over periods are tested over memory and over Doctrine on SQLite.
The exported credentials are checked offline for the shape Open Badges 3.0 describes, by
`tests/Unit/Support/Ob3Shape.php` ([Validation](validation.md#what-is-checked-offline)). The console commands are tested
with `CommandTester`, the lock with an in-memory store that tells whether it was held while the index was taken and the
certificate stored, each attempt of an award whose index was taken included.

`tests/Functional` boots a small application with FrameworkBundle, TwigBundle and DoctrineBundle on SQLite, awards
certificates, requests the register's pages and downloads, revokes, reads the Doctrine repositories, and runs the
commands with the framework's lock factory and event dispatcher. It plays two awards at once without a lock: another
award stores its certificate at the index this one was given, between the moment it was given and the save, and the
award is seen starting again on a reset entity manager with the next index, or giving up after its attempts.

`tests/Integration` renders a certificate through a real Gotenberg 9, named by `PHIL_GOTENBERG_URL`, and checks that the
credential is the PDF's associated file of relationship `Data`, byte for byte, and that the file passes PDF/A-3b
validation; it is skipped without that variable.

From the monorepo root:

```sh
vendor/bin/phpunit packages/certificates-bundle/tests
composer test:services    # the Integration suite against the Gotenberg of phil-dev services
```

The bundle is held to 100 % covered mutation score by Infection.

## Fuzzing and benchmarks

The fuzz target `verification-code` (`tests/Fuzz/verification-code.php`) feeds the readers of verification codes and
of the names of status lists with text nobody wrote: a code read writes back to itself, a list placed holds the
indexes it gives, and nothing but `InvalidValue` is thrown. Its seeds are in `tests/Fuzz/corpus/verification-code`.

```sh
composer fuzz -- verification-code
```

`tests/Benchmark/CertificatesBench.php` measures what the register pays on each visit and each publication of a status
list: a code read as typed, an index and a list placed in the layout, and the revoked indexes of a list of ten
thousand certificates.

```sh
vendor/bin/phil-dev bench packages/certificates-bundle/tests/Benchmark
```

## Testing an application that awards certificates

Give the test environment a fixed issuer key and a frozen clock — `Symfony\Component\Clock\MockClock` as the `clock`
service —: the dates on the certificates are then known. The codes and the credentials' salts are random; read them
from the returned `Certificate`. Verify the credentials with the `CredentialVerifier` service of
`phil/open-badges-bundle`, and play Gotenberg with the HTTP client's `mock_response_factory`, or set `pdf.bake: false`
to render the PDF without it.
