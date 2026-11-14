# Integrations

Each integration turns on when its bundle is registered (`integrations.<name>: auto`), and can be required (`on`) or
turned off (`off`).

## The media library

With `phil/media-bundle`, `ScanningMediaStorage` decorates its `MediaStorage`: every original written — an upload of
the file explorer, a replacement, an image of an imported document — is inspected under the context `media` before it
is stored. A file the policy does not keep is refused with `InvalidMedia`, which the explorer shows beside the file
name, and nothing is written. What the library renders from an original — resized, converted — is not scanned again.

## The document importer

`phil/document-import-bundle` needs no integration of this bundle: when both are registered, it sends each document
through `UploadScanner`, under the context `document_import`, before it reads it, and its own `scan` option turns
that on or off. clamd looks inside archives, so the images and macros of a DOCX or an ODT are scanned with it. When the
images go to the media library, they pass the media integration too.

## Events for the packages that keep files

A package that keeps files and scans them later — a media library that serves nothing before it is cleared, an
importer that reads documents on a worker — needs no code of this bundle beyond its events: `FileInfected` and
`FileFlagged` carry the context of the scan (`media`, `document_import`, `rescan.<target>`), and `FileRescanned` the
target, the identifier and the new inspection of a file scanned again on a worker. See
[Quarantine and events](quarantine-and-events.md) and [Rescans](rescans.md#filerescanned).

## The health check

With `phil/monitoring-bundle`, and the `clamd` or `fake` scanner, `ScannerHealthCheck` runs in every round under the
name `upload_scan`:

| clamd | Status | Summary |
|---|---|---|
| answers `VERSION` | `up` | The version clamd states, such as `ClamAV 1.4.1/27450/Sun Nov  8 09:30:02 2026` |
| does not answer | `degraded` | The antivirus does not answer: uploads are refused or flagged. |

The site works without its antivirus, so the check never answers `down`: a monitor does not take the site out of
service, but an operator is told. The date of the signatures in the summary shows when `freshclam` stopped updating
them.
