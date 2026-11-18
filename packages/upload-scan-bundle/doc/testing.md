# Testing

## The bundle's tests

The suite `tests/Unit` tests the `UploadScanner` — every decision, the scan and the quarantine of the whole stream
wherever it stands, a stream that cannot be read again, a quarantine that fails, the events —; the rescan handler and
its message; the filesystem quarantine, its permissions while a file is copied (fixture `WatchedStream`) and once it is
kept, an open directory narrowed or refused, two files kept at the same moment, its record and its failures, none
leaving a file; its inventory — the order of the records, their pages, a record that cannot be read, identifiers that
are not the quarantine's, a deletion with or without its file —; the administration page rendered through Twig, its
pages, its escaping, its CSRF token, its role and its log, and the EasyAdmin menu link; the constraint through Symfony's
validator; the media, document and monitoring adapters; the retention, its pages, its bound, its dry run and its
command; `FileRescanned`; and the wiring of the bundle, on the `ContainerBuilder` and on a compiled container, the
inventory given or the page and the purge removed by its compiler pass, and the route of `RescanFile` added to
Messenger's.

The suite `tests/Functional` boots a small application — FrameworkBundle, TwigBundle, Symfony Security with HTTP
Basic — and follows a file the whole way: sent to an upload page that validates it with `#[CleanFile]`, refused and
quarantined when infected, listed on the administration page for an administrator only, refused a deletion with a
forged CSRF token, deleted for good through the page's own form, and the purge command registered. It runs on the fake
scanner, or on clamd when `PHIL_UPLOAD_SCAN_CLAMD` names one: the test belongs to the group `clamd`.

`tests/Fuzz/quarantine-record.php`, run by `composer fuzz`, feeds the filesystem quarantine records and identifiers
nobody wrote: a record of any contents still reads as a file of the quarantine, and an identifier the quarantine did
not give names nothing and deletes nothing.

`ClamdTest`, in `phil/upload-scan`, runs the clamd scanner against a real clamd when `PHIL_UPLOAD_SCAN_CLAMD` names
one, and is skipped otherwise. From the root of the monorepo that holds `phil/upload-scan`:

```sh
docker run --rm -d --name clamav -p 3310:3310 clamav/clamav:stable   # its signatures load in a minute or two
PHIL_UPLOAD_SCAN_CLAMD=tcp://127.0.0.1:3310 vendor/bin/phpunit packages/upload-scan/tests/Integration
```

The bundle's functional suite runs against the same clamd:

```sh
PHIL_UPLOAD_SCAN_CLAMD=tcp://127.0.0.1:3310 vendor/bin/phpunit --group clamd packages/upload-scan-bundle/tests
```

From the monorepo root:

```sh
vendor/bin/phpunit packages/upload-scan-bundle/tests
```

## Testing an application that scans

In the test environment, use the fake scanner: no clamd, and the EICAR test string is found as clamd would find it.

```yaml
# config/packages/test/phil_upload_scan.yaml
phil_upload_scan:
    scanner: fake
    quarantine:
        directory: '%kernel.cache_dir%/quarantine'
```

```php
use Phil\UploadScan\FakeScanner;
use Symfony\Component\HttpFoundation\File\UploadedFile;

$path = tempnam(sys_get_temp_dir(), 'eicar');
file_put_contents($path, FakeScanner::eicar());

$client->request('POST', '/manuscripts', files: ['manuscript' => ['file' => new UploadedFile($path, 'virus.docx', test: true)]]);

self::assertSelectorTextContains('.form-error', 'This file contains a threat and was refused.');
```

To test the behaviour while the antivirus is down, register `new FakeScanner(error: 'down')` as a service and name it
in `scanner`.
