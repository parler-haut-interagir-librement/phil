# Changelog

All notable changes to `phil/upload-scan-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.2-alpha - 2026-11-20

A quarantine that reaches `public/` through a symbolic link is refused like one
written under it.

### Added

- The [ASVS 5.0 mapping](doc/asvs.md), and the order of validation and vote for `#[MapUploadedFile]` in
  [Validation](doc/validation.md#check-access-before-the-scan).

### Security

- The quarantine directory was compared with `public/` as written: a `var/quarantine` linked into `public/`, or a
  `public/` linked to the directory's parent, passed, and the web server would have served the infected files. Both
  paths are compared once their symbolic links are followed too.

## 0.1.1-alpha - 2026-11-18

A quarantined file and its record are readable by their owner alone from the moment
they exist, and an open quarantine directory is narrowed to its owner. See
[Quarantine and events](doc/quarantine-and-events.md).

### Added

- `FilesystemQuarantine`'s argument `tightenOpenDirectory`, and the option `quarantine.tighten_open_directory`, true by
  default: false refuses a directory open to its group or to other users instead of narrowing it.
- The option `admin.enabled`, true by default: false leaves the administration page of the quarantine, its controller
  and its CSRF check out, for an application that installed `symfony/security-csrf` for its own forms.
- `CsrfProtectionPass`: with the administration page registered and `framework.csrf_protection` off, the container
  build stops with a message that names both ways out.
- The documentation page [CSRF](doc/csrf.md): what carries a token, why it is required by default, sending it from a
  form or with `fetch`, and an application that installed the component for another reason.

### Changed

- `phil/media-bundle` 0.2 is accepted beside 0.1.
- The bundle adds the CSRF token `phil_upload_scan_quarantine` to FrameworkBundle's stateless ones: the deletions of
  the administration page are checked by the request's origin, without a session.
- `FilesystemQuarantine::isolate()` narrows an existing directory open to its group or to other users to `0700`
  before it keeps a file, and fails with `QuarantineFailed`, naming the directory and its mode, when it cannot.
- The copy and the record are written as temporary files of the directory created `0600`, then given their names in
  one step, the record last; a failure leaves neither of them, nor a temporary file. A directory reached through a
  symbolic link is used where the link points.

### Security

- The record of a quarantined file, which holds the name it was sent under and the signature found, was created with
  the default mode, and the copy was narrowed to `0600` only after it was created: in a directory open to them,
  other users of the machine could read both. Neither is readable by another user at any moment.

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
