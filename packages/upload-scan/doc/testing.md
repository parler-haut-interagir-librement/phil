# Testing

## The library's tests

The suite `tests/Unit` tests the results and the bounds of their texts; the policy's decisions; the null and fake
scanners; every answer of clamd the reader knows and many it does not; the connector on a real Unix socket and TCP
port; and the clamd scanner against a scripted clamd that records every byte sent — the framing of the chunks, the
size limit to the byte, a connection closed before, during and after the file, writes accepted in parts — and against
a real socket that never answers, for the timeouts.

From the monorepo root:

```sh
vendor/bin/phpunit packages/upload-scan/tests
```

The library is held to 100 % covered mutation score by Infection.

`tests/Integration/ClamdTest.php` runs against a real clamd at the address of `PHIL_UPLOAD_SCAN_CLAMD`, and is
skipped without it:

```sh
docker run -d --name clamav -p 3310:3310 clamav/clamav:stable
PHIL_UPLOAD_SCAN_CLAMD=tcp://127.0.0.1:3310 vendor/bin/phpunit packages/upload-scan/tests/Integration
```

## The fuzz target

`tests/Fuzz/clamd-response.php` gives the reader of clamd's answers bytes nobody wrote, from the seeds of
`tests/Fuzz/corpus/clamd-response`: it must answer a result whose text is printable ASCII, and never throw nor warn.
`composer fuzz` runs it.

## The benchmark

`tests/Benchmark/ClamdScannerBench.php` measures what the library adds to every upload: a 1 MiB file framed and sent
to a scripted clamd that answers at once, the fake scanner over the same file, and an answer read. `composer bench`
runs it with the other benchmarks.

## Testing code that scans

Give the code a `FakeScanner` rather than a clamd:

```php
use Phil\UploadScan\FakeScanner;

$scanner = new FakeScanner(['MALICIOUS' => 'Test.Malicious']);

$scanner->scanPath($upload);                  // infected when the file holds the EICAR string or MALICIOUS
new FakeScanner(error: 'down')->scanPath($upload);     // an error result, for the policy
new FakeScanner(reachable: false)->version();          // null, for a health check
```

`FakeScanner::eicar()` is the EICAR test string, which every antivirus, clamd included, reports as a test signature:
write it to a file to test a refusal end to end. `NullScanner` finds every file clean, for tests that do not care.
