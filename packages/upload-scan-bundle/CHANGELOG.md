# Changelog

All notable changes to `phil/upload-scan-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-14

### Added

- `PhilUploadScanBundle` and the `phil_upload_scan` configuration: the scanner (`clamd`, `fake`, `null` or a service
  id), the address, timeouts and chunks of clamd, the size limit, `on_error`, the quarantine, its administration
  (`admin.role`, `admin.layout`, `admin.page_size`), its retention (`quarantine.retention_days`), the transport of
  rescans (`rescan.transport`, added to the routing of Messenger) and the integrations; a bad clamd address, or a
  quarantine under `public/`, fails the container build.
- `UploadScanner` and `Inspection`: a path, contents or a stream scanned, decided by the policy, an infected file
  quarantined from the start of its stream, read whole whatever was read of it before, `FileInfected` and
  `FileFlagged` dispatched.
- The `#[CleanFile]` constraint and `CleanFileValidator`, for an `UploadedFile`, a `File` or a path, with the codes
  `INFECTED_ERROR` and `NOT_SCANNED_ERROR` and messages of the application's choice.
- `Quarantine`, its port, and `FilesystemQuarantine`: each infected file kept under a random identifier, read and
  written by its owner only, beside a record of its name, its signature and its date; a quarantine that fails is
  logged and the file still refused.
- `QuarantineInventory` and `QuarantinedFile`, implemented by `FilesystemQuarantine`: the records listed the most
  recent first, read by identifier, deleted with their file. `QuarantineAdminController`, `config/admin_routes.php`
  and its templates: the quarantine a page at a time, a file deleted for good behind a CSRF token and logged, every
  page behind `admin.role`, what a file holds never shown; `QuarantineInventoryPass`, which gives the inventory the
  quarantine when it can be listed and removes the page without one; `QuarantineAdmin::menuItem()`, the link of an
  EasyAdmin menu to the page.
- `QuarantinePurger` and the `phil:upload-scan:purge-quarantine` command: the quarantined files kept longer than the
  retention deleted for good with their records, a page of the inventory at a time, at most `--max` in a run, each
  deletion logged; `--days` for another retention, `--dry-run` to count only.
- `RescanFile`, a message of two identifiers, `RescanTarget`, autoconfigured with the tag
  `phil_upload_scan.rescan_target`, and `RescanFileHandler`, registered when Messenger is installed; `FileRescanned`,
  dispatched once the target resolved the new inspection, for the packages that keep files.
- `ScanningMediaStorage`: the originals of `phil/media-bundle` scanned before they are stored, refused with
  `InvalidMedia`.
- `ScannerHealthCheck`: the antivirus up with its version, or degraded, for `phil/monitoring-bundle`.
- The documentation in `doc/`, one file per topic, indexed by the README, with a clamd service for Docker Compose.
- A functional suite that follows an upload to the quarantine and its page in a booted application, on the fake scanner
  or a real clamd, and the fuzz target `quarantine-record`.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
