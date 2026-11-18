# Installation

## Requirements

| Package | Version | Why |
|---|---|---|
| PHP | 8.5 or later | Readonly classes, typed constants, `new` without parentheses. |
| `ext-sodium` | | The random identifiers of the quarantine, written in constant time. |
| Symfony | 8.2 | The container, the validator, the HTTP foundation's files. |
| `phil/upload-scan` | `^0.1` | The scanners and the policy. |
| clamd | any supported ClamAV | The antivirus, on a Unix socket or a TCP port: see [ClamAV](clamav.md). |

Optional:

| Package | For |
|---|---|
| `symfony/messenger` | Rescans on a worker. |
| `phil/media-bundle` | The originals of the media library scanned before they are stored. |
| `phil/document-import-bundle` | Documents scanned before they are imported, by its own `scan` option. |
| `phil/monitoring-bundle` | The health check of the antivirus. |
| `twig/twig` and `symfony/security-csrf` | The administration page of the quarantine, whose deletions require a CSRF token: see [CSRF](csrf.md). |
| `easycorp/easyadmin-bundle` | `QuarantineAdmin`, the link of an EasyAdmin menu to that page. |

## Registering the bundle

```sh
composer require phil/upload-scan-bundle
```

```php
// config/bundles.php
return [
    // …
    Phil\UploadScanBundle\PhilUploadScanBundle::class => ['all' => true],
];
```

Once Twig and `symfony/security-csrf` are both installed, for whatever reason, the bundle registers the
administration page of the quarantine and checks a stateless CSRF token on its deletions. FrameworkBundle's
`csrf_protection` must then stay on; an application that turns it off sets `phil_upload_scan.admin.enabled` to
`false`. See [CSRF](csrf.md).

## A first scan

With clamd listening on `tcp://127.0.0.1:3310`, the default, nothing else is needed: put `#[CleanFile]` on an upload
([Validation](validation.md)), or inject the `UploadScanner`:

```php
use Phil\UploadScanBundle\Application\UploadScanner;

final readonly class AttachmentController
{
    public function __construct(
        private UploadScanner $scanner,
    ) {
    }

    public function attach(UploadedFile $file): void
    {
        $inspection = $this->scanner->inspectPath($file->getPathname(), $file->getClientOriginalName(), 'attachment');

        if (!$inspection->accepted()) {
            throw new UnprocessableEntityHttpException('This file was refused.');
        }
        // …
    }
}
```

In the test environment, `scanner: fake` scans without clamd: see [Testing](testing.md).
