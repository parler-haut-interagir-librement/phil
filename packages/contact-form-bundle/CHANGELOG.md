# Changelog

All notable changes to `phil/contact-form-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.3-alpha - 2026-11-25

### Security

- Requires `twig/twig` 3.31.1 or later, which fixes the advisories of its sandbox published on 2026-11-24.

## 0.1.2-alpha - 2026-11-24

### Changed

- `ContactMailer` takes a Mailer `TransportInterface`, the service `mailer.transports`, instead of `MailerInterface`.
- `symfony/messenger` is in `require-dev`, for the test that routes `SendEmailMessage` to an asynchronous transport.
- Requires the latest minor of each dependency (`twig/twig` 3.31, `phil/spam-protection-bundle` 0.3).

### Fixed

- `phil/spam-protection-bundle` 0.3 is accepted beside 0.1 and 0.2: the package did not install beside the version
  published on 2026-11-20.

### Security

- A contact message no longer goes through `MailerInterface`, which put a `SendEmailMessage` on the bus when the
  application routed it: the unrendered email, with the visitor's name, address and message in its context, kept by
  the Messenger transport and its failure transport. The email is rendered and sent in the request, through the Mailer
  transports, and a refusal of the transport reaches the page at once.

## 0.1.1-alpha - 2026-11-17

### Changed

- It accepts `phil/spam-protection-bundle` 0.2 beside 0.1.

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
