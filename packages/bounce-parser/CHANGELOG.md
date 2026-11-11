# Changelog

All notable changes to `phil/bounce-parser` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## NEXT - YYYY-MM-DD

### Added

- `Mime\MimeParser`, `Mime\Part` and `Mime\Headers`: header fields unfolded, encoded words (RFC 2047) decoded on
  request, media types and their parameters, base64 and quoted-printable bodies, multiparts split at their boundaries,
  `message/rfc822` and `message/global` read as the message they hold, digests; never throws, and bounded by `Limits`
  — octets read, depth, parts, header fields, text.
- `BounceParser`: delivery status notifications, abuse reports, bounces written as text and automatic replies, in that
  order, returning a `BounceReport` with its `ReportFormat`, `BounceKind`, `BounceAction`, `Recipient`s,
  `FeedbackReport`, `OriginalMessage`, VERP tag and deciding rule; never throws and keeps no state between calls.
- Delivery status notifications (RFC 3464, RFC 6533): each recipient's final and original address, `DeliveryAction`,
  status, diagnostic, basic SMTP code, remote server and last attempt; the worst kind among the recipients.
- `StatusCode` (RFC 3463): `parse()`, `find()` in a text, class, subject and detail, `isAboutTheMessage()` for a full
  mailbox, a message too long or a refusal by policy, `describe()`.
- `Recipient::kindOfFailure()`: hard for a permanent failure against the mailbox, soft otherwise, from the status code
  or the SMTP code.
- Abuse reports (RFC 5965, RFC 6591): `FeedbackReport` with every field and `FeedbackType`; a complaint unsubscribes
  only when the `Contract\ReportAuthenticator` vouches for it, and goes to review otherwise; `not-spam` ignored,
  `auth-failure` reviewed.
- `Auth\AuthenticationResults`, which vouches for a report whose `Authentication-Results` (RFC 8601), written under one
  of the application's authserv-ids, records a DKIM pass for a trusted domain, and `Auth\TrustEveryReport`.
- Bounces written as text: from `MAILER-DAEMON` or `postmaster`, with the null return path or a subject such servers
  write; the first address of the text, its first status or SMTP code, the kind unknown without one.
- Automatic replies (RFC 3834): `Auto-Submitted` other than `no`, `X-Autoreply`, `X-Autorespond`,
  `Precedence: auto_reply`; the message answered by its `In-Reply-To`.
- `OriginalMessage`: `messageId()`, `feedbackId()` and any field of the message a report returns, whole or as headers.
- `Verp`: return paths with a tag, `encode()`, `encodeRecipient()`, `decode()` and `decodeRecipient()`, and signed
  tags, `encodeSigned()` and `decodeSigned()` with an HMAC-SHA256 under the application's key; read from the
  `Delivered-To`, `X-Original-To` and `Envelope-To` fields of the bounce, never from `To`, or from the
  `envelopeFields` given to `BounceParser`.
- `Rule\Rule`, `Rule\Rules`, `Rule\Field` and `Rule\RuleScope`: rules on a regular expression or a text, on the
  subject, the sender, the text, the diagnostics or a named field, each with its kind and action, tried in their order
  on unrecognised messages or on every message; placed at the end, before or after a rule by name.
- `BounceAction::for()`, the action of each `BounceKind`.
- `Exception\BounceParserException`, implemented by `InvalidLimits`, `InvalidRule` and `InvalidVerp`.
- The fuzz target `bounce-parser`, with its seeds.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
