# phil/upload-scan-bundle

**Uploads scanned by ClamAV before they are kept, infected files quarantined where nobody can download them.**

phil/upload-scan-bundle puts `phil/upload-scan` into a Symfony application. The scanner — clamd, the fake scanner of
the tests, or the application's own — and the policy for a file that could not be scanned come from configuration. A
`#[CleanFile]` constraint scans an `UploadedFile` as a form or a DTO is validated. An infected file is copied to a
quarantine outside the web root, under a random identifier, and announced by a `FileInfected` event; an operator
reads the quarantine and deletes the files dealt with on an administration page, linked from an EasyAdmin menu, which
never shows what a file holds; a file accepted
unscanned under the policy `accept_and_flag` is announced by `FileFlagged`, and a `RescanFile` message, which carries
identifiers only, scans it again on a worker, routed to the transport of the configuration and followed by
`FileRescanned`. A console command deletes the quarantined files kept longer than the retention.

When their bundles are registered, the originals of `phil/media-bundle` are scanned before they are stored and
`phil/monitoring-bundle` reports whether the antivirus answers; `phil/document-import-bundle` scans the documents it
reads through the `UploadScanner` of this bundle.

It is released under the MIT licence.

## Status

| Part | State |
|---|---|
| The scanner, the policy and the quarantine from configuration | Implemented and tested |
| The `#[CleanFile]` constraint | Implemented and tested |
| The quarantine in a directory outside `public/`, `FileInfected` and `FileFlagged` | Implemented and tested |
| `RescanFile` and its handler, over the application's `RescanTarget` | Implemented and tested |
| `phil/media-bundle` and `phil/monitoring-bundle` | Implemented and tested |
| The administration page of the quarantine, `QuarantineInventory`, the EasyAdmin menu link | Implemented and tested |
| The retention of the quarantine and `phil:upload-scan:purge-quarantine` | Implemented and tested |
| The route of `RescanFile` to a transport, `FileRescanned` | Implemented and tested |
| An upload followed to the quarantine and its page in a booted application | Implemented and tested on the fake scanner |
| The clamd scanner against a real clamd | `ClamdTest` of `phil/upload-scan` and the group `clamd` of the bundle, run when `PHIL_UPLOAD_SCAN_CLAMD` names a clamd |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, registering the bundle, a first scan. |
| [Configuration](doc/configuration.md) | Every key of `phil_upload_scan`. |
| [ClamAV](doc/clamav.md) | A clamd service beside the application in Docker Compose. |
| [Validation](doc/validation.md) | The `#[CleanFile]` constraint and the `UploadScanner`. |
| [Quarantine and events](doc/quarantine-and-events.md) | Where infected files go, the administration page, the retention, `FileInfected` and `FileFlagged`. |
| [Rescans](doc/rescans.md) | `RescanFile`, `RescanTarget`, the worker and `FileRescanned`. |
| [Integrations](doc/integrations.md) | The media library, the document importer, the health check. |
| [Extension points](doc/extension-points.md) | The scanner, the quarantine and the rescan targets of the application. |
| [Testing](doc/testing.md) | The bundle's tests, and testing an application that scans. |
| [Reference](doc/reference.md) | Every type and service of the bundle. |

## Quick start

```sh
composer require phil/upload-scan-bundle
```

```yaml
# config/packages/phil_upload_scan.yaml
phil_upload_scan:
    clamd:
        address: tcp://clamav:3310
```

```php
use Phil\UploadScanBundle\Infrastructure\Validator\CleanFile;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

final class ManuscriptUpload
{
    #[Assert\File(maxSize: '20M')]
    #[CleanFile(context: 'manuscript')]
    public ?UploadedFile $file = null;
}
```

## Requirements

PHP 8.5 or later, Symfony 8.2, `ext-sodium`, and a clamd the application can reach.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
