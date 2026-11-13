# Changelog

All notable changes to `phil/paged-media-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-13

### Added

- `PagedMedia`, the native writer and Gotenberg behind one service: `render()` and `renderTemplate()`, sent on to
  Gotenberg when stages are given; `process()` and `convert()`; `signature()` and `timestamp()` from the
  configuration's ports; `finish()`, which ends a list with them; `gotenberg()`; `printHtml()`, HTML and CSS through
  the `PrintEngine` of the application, for which `phil/paged-media-weasyprint` is suggested; `alreadyAnnounced()`,
  the same service announcing nothing, for the worker that makes a queued print.
- `PrintSubject` and the `PrintStarting` event, dispatched before a print that names its work starts or is queued;
  `UnnamedPrintStarting`, dispatched instead before a print that names no work.
- `PrintableHtml`, a page for the engine; `PrintedNotes` and `NotesPage`, a UJO (Universal JSON Output) document with its
  notes resolved and placed by `phil/footnotes-sidenotes`.
- `DocumentTemplate`, `#[AsDocumentTemplate]` and `DocumentTemplates`: documents of the native writer drawn from
  parameters, registered by name.
- `PdfStorage`, `DirectoryStorage`, which refuses a path that leaves its directory, and the `PdfStored` event, which
  gives the job's identifier.
- `JobStore`, the claim check of the messages, `DirectoryJobStore`, one JSON file per job under a random identifier,
  readable by its owner only, and `JobCodec`, which writes a job as JSON and refuses one it did not write;
  `RenderJob`, `PipelineJob`, `ConversionJob` and `StoredStage`.
- `PhilPagedMediaBundle` and the `phil_paged_media` configuration: `gotenberg` (URL, basic authentication, timeout,
  HTTP client service), `signing` (the certificate source `file`, `inline` or `vault`, checked for what each needs, the
  timestamp authority, and the reason, location, contact, name and PAdES of every signature), `storage` and `jobs`
  (its directory, `remove_failed`, `cache_pool` and `lifetime`), and `templates` (`enabled`, `font`, `bold_font`).
- `PagedMediaQueue`, which keeps a job and dispatches the Messenger message `RenderPdf`, `RunPipeline` or
  `ConvertDocuments`, carrying the job's identifier only; signed and timestamped by the configuration when asked; and
  `PagedMediaHandler`, which keeps their files, forgets their jobs and retries a failure that may pass; and
  `FailedJobPurger`, which forgets the job of a message that failed for good, in a worker or on the `sync://`
  transport, unless `jobs.remove_failed` is false.
- The printed templates of `phil/paged-media` registered as document templates, unless `templates.enabled` is false:
  `paged_media.name_badges`, `address_labels`, `product_labels`, `shelf_labels`, `gift_cards`, `download_cards`,
  `calendar` and `large_print`, drawn from JSON-like parameters read by path, in the TrueType fonts of
  `templates.font` and `templates.bold_font` or in Helvetica.
- `CacheJobStore`, chosen by `jobs.cache_pool`: the jobs in a PSR-6 cache pool shared by every worker, kept for
  `jobs.lifetime` seconds or the pool's default; the transport's `claim_check` of Messenger 8.2 documented as a guard.
- `NotConfigured`, `UnknownTemplate`, `InvalidTemplateParameters`, `StorageFailed` (with the exception that caused it)
  and `JobUnavailable`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
