# Changelog

All notable changes to `phil/legal-notices` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-12

### Added

- `DocumentType` — the legal notice and the privacy policy — with the fields of the identity each one requires, and
  `Field`, the fields a site gives: publisher, form, address and registration, director of publication, contact,
  host and privacy officer.
- `SiteIdentity`: the fields of a site, trimmed, checked — one line but for the addresses, 1,000 bytes at most, an
  e-mail address for the contact — and the fields a document lacks.
- `LegalDocumentVersion`: a version with its number, its body in each locale and its format, its effective date, its
  `PublicationState` — draft, published, withdrawn — and its publication date, a summary of the changes; published and
  withdrawn as new values; a body found for a locale, its language, a fallback.
- `DocumentHistory`: the versions of a document by number, the one in force at a moment, the next one to take effect,
  the versions the public sees and a public version by number.
- `RetentionDuration` and `RetentionKind`: a retention period — fixed as an ISO 8601 duration, until an event, with no
  set end, not set yet — and what it keeps, in each locale; `DurationWords`, the periods in words in English and French.
- `ConsentCategory`: a category of cookies, necessary or not, its name and description in each locale, its cookies.
- `Placeholders`: `${field}`, `${version}`, `${effective_date}`, `${retention.<id>}` and `${consent.<key>}` filled
  into the HTML of a body, escaped, and the placeholders without a value; `${…}` passes through every format of
  phil/markup, the wiki formats included.
- `Placeholders::fill()` fills a value into the text nodes of the HTML only, read by the HTML5 parser of the DOM
  extension: a placeholder in a tag — an `href`, a `src` —, a comment, a template or a `<script>` or `<style>` is
  left as written, so that no value makes a `javascript:` link; `misplaced()` lists them and `LegalNotices::check()`
  reports each one as `misplaced_placeholder`.
- `PlainTextBody`, the renderer of the `text` format.
- `LegalNotices`: the history and the version in force of a document, its body rendered with its placeholders, the
  retention periods and consent categories, the versions published in a span of time, and `check()`, the `Problem`s of
  the public documents.
- The ports `DocumentSource`, `BodyRenderer`, `RetentionDurations` and `ConsentCategories`; the event
  `DocumentPublished`; `InvalidLegalNotice`.
- The fuzz targets of the placeholders and of the durations.
- The documentation in `doc/`, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
