# Reference

## Types

| Type | Kind | Role |
|---|---|---|
| `Phil\UploadScanBundle\PhilUploadScanBundle` | bundle | The configuration and the wiring: `RESCAN_TARGET_TAG` |
| `Phil\UploadScanBundle\Application\UploadScanner` | `final readonly class` | A file inspected: `inspectPath()`, `inspectContents()`, `inspectStream()` |
| `Phil\UploadScanBundle\Application\Inspection` | `final readonly class` | The result, the decision and the quarantine identifier: `accepted()`, `flagged()`, `infected()` |
| `Phil\UploadScanBundle\Application\Contract\Quarantine` | interface | Where infected files are kept: `isolate()` |
| `Phil\UploadScanBundle\Application\Contract\QuarantineInventory` | interface | What an operator reads and deletes: `files()`, `file()`, `delete()` |
| `Phil\UploadScanBundle\Application\QuarantinedFile` | `final readonly class` | The record of a quarantined file: identifier, name, signature, date, size |
| `Phil\UploadScanBundle\Application\Contract\RescanTarget` | interface | Files scanned again by identifier: `name()`, `open()`, `resolve()` |
| `Phil\UploadScanBundle\Application\Event\FileInfected` | `final readonly class` | An infected file refused |
| `Phil\UploadScanBundle\Application\Event\FileFlagged` | `final readonly class` | A file accepted unscanned |
| `Phil\UploadScanBundle\Application\Event\FileRescanned` | `final readonly class` | A file scanned again on a worker: `cleared()` |
| `Phil\UploadScanBundle\Application\QuarantinePurger` | `final readonly class` | The retention of the quarantine: `purge()`, `PAGE_SIZE`, `DEFAULT_MAX` |
| `Phil\UploadScanBundle\Infrastructure\Console\PurgeQuarantineCommand` | console command | `phil:upload-scan:purge-quarantine`: `NAME` |
| `Phil\UploadScanBundle\Application\Message\RescanFile` | `final readonly class` | A target and a file identifier |
| `Phil\UploadScanBundle\Application\RescanFileHandler` | `final readonly class` | Handles `RescanFile` |
| `Phil\UploadScanBundle\Application\Exception\QuarantineFailed` | `final class` | A file the quarantine could not keep |
| `Phil\UploadScanBundle\Application\Exception\UnknownRescanTarget` | `final class` | A message naming no target |
| `Phil\UploadScanBundle\Infrastructure\Quarantine\FilesystemQuarantine` | `final readonly class` | The quarantine in a local directory, and its inventory |
| `Phil\UploadScanBundle\Infrastructure\Admin\QuarantineAdminController` | controller | The quarantine's page: `LIST_ROUTE`, `DELETE_ROUTE`, `CSRF_TOKEN_ID` |
| `Phil\UploadScanBundle\Infrastructure\EasyAdmin\QuarantineAdmin` | `final class` | The link of an EasyAdmin menu to the quarantine's page |
| `Phil\UploadScanBundle\Infrastructure\DependencyInjection\QuarantineInventoryPass` | compiler pass | `QuarantineInventory` given the quarantine when it can be listed; the page and the purge removed without an inventory |
| `Phil\UploadScanBundle\Infrastructure\Validator\CleanFile` | constraint | A file free of known threats: `INFECTED_ERROR`, `NOT_SCANNED_ERROR` |
| `Phil\UploadScanBundle\Infrastructure\Validator\CleanFileValidator` | validator | Checks `CleanFile` |
| `Phil\UploadScanBundle\Infrastructure\Media\ScanningMediaStorage` | `final readonly class` | The media storage behind the antivirus |
| `Phil\UploadScanBundle\Infrastructure\Monitoring\ScannerHealthCheck` | `final readonly class` | The health check `upload_scan` |

## Services

| Id | Registered |
|---|---|
| `Phil\UploadScan\Contract\Scanner` | Always: an alias of the scanner chosen. |
| `Phil\UploadScan\Clamd\ClamdScanner`, `FakeScanner`, `NullScanner` | The one `scanner` names. |
| `Phil\UploadScan\ScanPolicy` | Always, with `on_error`. |
| `Phil\UploadScanBundle\Application\Contract\Quarantine` | Unless the quarantine is disabled: an alias of `FilesystemQuarantine` or of `quarantine.service`. |
| `Phil\UploadScanBundle\Application\Contract\QuarantineInventory` | When the quarantine implements it, unless the application aliases it. |
| `Phil\UploadScanBundle\Infrastructure\Admin\QuarantineAdminController` | With a quarantine, Twig and `symfony/security-csrf`, and an inventory; public, tagged `controller.service_arguments`. |
| `Phil\UploadScanBundle\Application\QuarantinePurger`, `Phil\UploadScanBundle\Infrastructure\Console\PurgeQuarantineCommand` | With a quarantine, the Console component and an inventory; the command tagged `console.command`. |
| `Phil\UploadScanBundle\Application\UploadScanner` | Always. |
| `Phil\UploadScanBundle\Infrastructure\Validator\CleanFileValidator` | Always, tagged `validator.constraint_validator`. |
| `Phil\UploadScanBundle\Application\RescanFileHandler` | With Messenger, tagged `messenger.message_handler`; `RescanFile` routed to `rescan.transport` when it names one. |
| `Phil\UploadScanBundle\Infrastructure\Media\ScanningMediaStorage` | With `phil/media-bundle`, decorating `MediaStorage`. |
| `Phil\UploadScanBundle\Infrastructure\Monitoring\ScannerHealthCheck` | With `phil/monitoring-bundle` and the `clamd` or `fake` scanner. |
