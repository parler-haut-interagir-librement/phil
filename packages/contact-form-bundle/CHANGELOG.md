# Changelog

All notable changes to `phil/contact-form-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-11

### Added

- `ContactMessage`, which checks the email address by RFC 5322 without warnings, so an address Symfony Mime refuses
  in `Reply-To`, such as `a..b@example.com`, is a wrong field answered with `422`; `Recipients` and
  `ConfiguredRecipients`; `ContactFormType`, `ContactMailer`, `ContactFormController`; `config/routes.php`.
- `PhilContactFormBundle`, extension alias `phil_contact_form`, with the keys `sender`, `recipients`,
  `subject_prefix` and `template`.
- The templates of the page and of the email; the labels in English and French, in `translations/`.
- The documentation in `doc/`, one file per topic, indexed by the README; `doc/mailing.md` says that with an
  asynchronous Messenger transport a failure surfaces in the worker rather than on the `503` page, and that the
  queued messages hold personal information that needs a retention rule.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
