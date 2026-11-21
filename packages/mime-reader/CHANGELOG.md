# Changelog

All notable changes to `phil/mime-reader` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-20

### Added

- `MimeReader`, which reads an Internet message (RFC 5322) and its MIME structure (RFC 2045, 2046) from a string or a
  stream: multiparts of every subtype split at their boundaries, `message/rfc822` and `message/global` read as the
  message they hold, the parts of `multipart/digest` read as messages by default, encoded containers decoded first.
  It never throws on a message.
- `Limits`: the octets, depth, parts, header fields and parameters read in one message; a part a limit cuts is marked
  incomplete. Every search stays inside its part.
- `Part`: header fields, media type and parameters, transfer encoding, child parts and held message; `content()`,
  `text()`, `writeTo()` and `stream()`, which decode base64 and quoted-printable on demand and in chunks and convert
  character sets to UTF-8, by mbstring or, for the sets it does not know, iconv; `disposition()`, `filename()`
  without any directory, `isAttachment()`, `textBody()`, `htmlBody()`, `attachments()`, `walk()`, `find()`,
  `fieldGroups()` for delivery status, disposition notification and feedback report fields, and
  `create()` for parts built from values.
- `Headers`: fields unfolded in their order, names compared without case, `decoded()` for encoded words (RFC 2047),
  `parameterized()` and `mailboxes()`.
- `HeaderValue`: a value and its parameters, sections and character sets (RFC 2231) joined and decoded.
- `Mailbox`: an address as written, its decoded display name and its domain.
- `MimeReaderException`, `InvalidLimits` and `InvalidStream`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- The fuzz target of the reader and the benchmarks of messages of ordinary and hostile shape.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
