# Changelog

All notable changes to `phil/paged-media-weasyprint` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-13

### Added

- `WeasyPrintEngine`: the `PrintEngine` of `phil/paged-media-bundle` on the `weasyprint` command, the document on its
  standard input and the PDF on its standard output, only `data:` addresses fetched, so that no remote image is
  read, a configurable timeout; the version of the command read and checked against WeasyPrint 70; registered under
  the port by one line of `services.yaml`.
- `PdfOptions` and `PdfVariant`: PDF/A, PDF/UA and PDF/X, the output intent (`srgb`, `device-cmyk` or the dashed
  name of a `@color-profile`, required for PDF/X), tags, form fields, the resolution and JPEG quality of images and
  their optimization, each a checked argument of the command.
- `LocalAssets`: the images, fonts and ICC profiles of one directory written into a document as `data:` addresses,
  references resolved inside the directory, extensions listed, a limit per file and per document.
- `WeasyPrintFailed`, with the exit code and the error output of the command.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
