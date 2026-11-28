# Changelog

All notable changes to `phil/email-preview` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-27

### Added

- `EmailChecker`, which checks an email without sending it against its rules, `DefaultRules::all()` by default, and
  keeps no state between two emails.
- `CheckedEmail`, an email as the rules read it, its HTML parsed once by `Dom\HTMLDocument`; read from a
  `RenderedEmail` of phil/email-templates-bundle or from a `Symfony\Component\Mime\Email`, and refused with
  `EmailTooLarge` above 2 MiB of HTML.
- `Sending`, what the rendering does not say: a bulk sending, its headers, and the values that identify the recipient.
- The `Rule` interface, to add or replace a check.
- The `text-part` rule: a plain-text part that gives every `http:`, `https:` and `mailto:` address of the HTML.
- The `one-click-unsubscribe` rule: on a bulk sending, `List-Unsubscribe` with an `https:` address and
  `List-Unsubscribe-Post: List-Unsubscribe=One-Click`, as RFC 8058 describes them.
- The `tracking-pixel` rule: no remote image of one pixel or less, and no image address that carries a value of the
  recipient.
- The `no-style-element` rule: no `<style>` element left once the styles are inlined.
- The `max-width` rule: no element wider than 600 pixels by default, by attribute or style.
- The `subject-length` rule: a warning above 50 characters by default, counted as graphemes.
- `Report` and `Finding`, with errors and warnings, written as text, as an escaped HTML fragment and as JSON.
- `PreviewChecks` and `CheckedPreview`: each preview of a template, rendered by the `PreviewRenderer` of
  phil/email-templates-bundle with its sample data, and its report.
- A benchmark of the check of a newsletter of some 300 KB.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
