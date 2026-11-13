# Changelog

All notable changes to `phil/paged-media` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-13

### Added

- `Document`, the native PDF writer: pages of any size, `text()`, `textRight()`, `line()`, `rectangle()`, `image()`,
  `wrap()`, compressed streams, and an information dictionary from `Metadata`; the same calls give the same bytes.
- Form fields in the native writer, placed by `Document::field()`: `TextField` (one line or multiline, a maximum
  length), `CheckBox`, `RadioGroup` of `RadioButton`s and `ChoiceField` (combo box, editable or not, or list box),
  each required or read-only, with a tooltip and its colours; each appearance drawn when it is placed, so that no
  reader generates one; `render(flattenForm: true)` draws them into their pages instead of keeping them fillable; a
  TrueType font a field types in is embedded whole while the form is interactive, and
  `TrueTypeFont::characters()` lists what a font prints; in PDF/A-2u, fields typing in a standard font are refused.
- Printed templates under `Template`, drawn from data on the native writer: `NameBadges` of `Badge`s,
  `AddressLabels`, `ProductLabels`, `ShelfLabels`, `GiftCards`, `DownloadCards` (one card per code), `Calendar` of
  `CalendarMonth`s (a month per page, weeks from the first day asked) and `LargePrint` of `LargePrintBlock`s (18
  points or more, headings kept with their text, page numbers); `LabelSheet`, with `letter30()`, `letter10()`,
  `letter6()`, `a4x21()`, `a4x8()`, `single()` and `of()`, and `TemplateFonts`; text made smaller to fit, then cut
  with an ellipsis.
- `PageSize`: `of()`, `ofMillimetres()`, `letter()`, `a4()`, `a5()`, `landscape()`.
- `Css\PageRule`, the `@page` rule of a paged-media engine from a `PageSize`, one to four margins, a bleed up to one
  inch and `PrinterMarks` (crop, cross or both), in points; the same rule gives the same text.
- `Color`: `gray()`, `rgb()`, `hex()`, `black()`.
- PDF/A-2u: `Conformance::PdfA2u` given to `Document` adds the XMP metadata, an sRGB output intent built by the library
  and the file identifier, and refuses the standard fonts, a character the font lacks and CMYK images.
- `StandardFont`: Helvetica and Helvetica Bold in Windows-1252, each with its own widths.
- `TrueTypeFont`: reading TrueType outlines, measuring, and embedding reduced to the glyphs used with their composite
  components, or whole when the font's licence forbids subsetting; a font whose licence forbids embedding is refused.
- `Image`: JPEG kept as it is, CMYK and inverted Adobe CMYK included; PNG kept compressed, or decoded into colour and
  alpha when it is transparent, at most `Image::MOST_TRANSPARENT_PIXELS` (36 million) pixels, inflated no further
  than its declared size.
- `Gotenberg\Gotenberg`, a Gotenberg 9 client on Symfony HttpClient: `pipe()` runs a list of stages on
  `/forms/pdf/pipe`, `run()` one stage on its own route, `convert()` the LibreOffice conversion, then the stages;
  `version()`; basic authentication and a timeout.
- `Files`, `InputFile` (from a path read in chunks, from memory, from a `Document`; `path()` tells which) and
  `Download`: uploads, ZIP archives and `downloadFrom`; the same file named twice is sent once, two files of the same
  name are refused. A download's address and a timestamp authority's are public ones, read by `phil/url`; Gotenberg's
  URL and a webhook's are service addresses of the application's network, read by the WHATWG URL parser, without
  credentials.
- `RequestOptions` (output file name, trace), `Webhook` and `WebhookEvent`, and `Result`, read whole or streamed to a
  file, with `Result::$residual` and `$rolledBack` from the remediation headers.
- The 28 stages under `Gotenberg\Stage`, each checking its options when it is built: merge, split, select, delete,
  insert, rotate, stamp-text, stamp-image, stamp-pdf, set-metadata, set-bookmarks, set-form, flatten-form,
  flatten-transparency, convert-colors, redact, autotag, attach, detach, optimize, encrypt, decrypt, remediate, sign,
  timestamp, render, extract-text and extract-attachments; `Pages` for page selections.
- `Pipeline`, which refuses before sending a list in an order Gotenberg refuses: `decrypt` first, the terminal stages
  last, `remediate`, `encrypt`, `sign` and `timestamp` once, the stages that may follow each, and the combinations
  Gotenberg forbids.
- `Profile` and `Remediate`: PDF/A-1a to PDF/A-4f, PDF/UA-1 and PDF/UA-2, Factur-X with its invoice XML and level, and
  the pairs of a PDF/A and a PDF/UA profile.
- Signing through ports: `CertificateSource` with `FileCertificateSource`, `InlineCertificateSource` and
  `VaultCertificateSource` (HashiCorp Vault or OpenBao, key/value version 2), and `TimestampAuthority` with
  `TimestampAuthorityUrl` for RFC 3161 timestamps; `Sign` with PAdES.
- `LibreOfficeOptions` with the conversion's native options, `nativePdfa`, `nativePdfua` and the watermarks among them,
  and the combinations it refuses.
- The read routes: `info()`, `metadata()`, `bookmarks()`, `attachments()`, `form()` and `validate()`, into `PdfInfo`,
  `DocumentInformation`, `Bookmark`, `Attachment`, `FormField` and `ValidationReport` with its `Violation`s.
- `PagedMediaException`, implemented by `InvalidDocument`, `InvalidFont` and `InvalidImage`; the client's exceptions
  under `GotenbergException`: `InvalidStage`, `InvalidPipeline` and `InvalidRequest` before sending; `RequestRefused`,
  `AccessDenied`, `RouteUnavailable`, `ServiceUnavailable`, `ConversionFailed` and `UnexpectedResponse` by status,
  retryable or not; `CertificateUnavailable`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
