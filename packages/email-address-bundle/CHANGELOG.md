# Changelog

All notable changes to `phil/email-address-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.1-alpha - 2026-11-25

The address fingerprints change at the update; a fingerprint stored before it no
longer matches its address, so compute again those an application keeps.

### Security

- The address fingerprints were the HMAC under `fingerprint_key` itself, `%kernel.secret%` by default, which other
  bundles keyed their own hashes of the same address with, so that two pseudonymous records joined on it.
  `EmailAddresses` derives the key of `AddressFingerprint` by HKDF-SHA256 under `phil_email_address.fingerprint`
  (`EmailAddresses::FINGERPRINT_USAGE`).

## 0.1.0-alpha - 2026-11-24

### Added

- The bundle `PhilEmailAddressBundle` and its configuration `phil_email_address`: the policy, the sites and lists,
  the DNS, the suggestions and the bounces.
- The constraint `DeliverableEmailAddress` of `phil/email-address` on the container: the library's lists, the
  configured disposable domains, the configured resolver behind a cache, and the configured suggester.
- `EmailPolicy` and `EmailPolicies`: the policy of the bundle, of the current site (`CurrentSite`) and of a list, laid
  over each other; `UnknownEmailList` for a list the configuration does not hold.
- The constraint `AcceptedEmailAddress`, checked through `DeliverableEmailAddress` with the policy in force.
- `EmailAddresses`: the normalized form, the comparison of two addresses, the keyed fingerprint `AddressFingerprint`,
  and the role addresses, warned about and accepted.
- `EmailAddressType`: the constraint, the Stimulus controller, and the suggestion or the role warning as the field's
  help once the form is submitted.
- The route `phil_email_address_domains` and `EmailDomainsController`: the known domains, the top-level slips and the
  role addresses in JSON, public, kept a day, with an ETag.
- The Stimulus controller `phil--email-address-bundle--email-address`: the suggestion with a button that takes it, the
  role warning, in a live region; the lists read once per page.
- `CachedMailDomainResolver`, which keeps the answers of a resolver in a cache pool.
- `BounceRules`: a hard bounce suspends, soft bounces count up to a threshold within a sliding window, an
  authenticated complaint unsubscribes, a complaint no authenticator vouches for goes to review, the same notice is
  decided once; each decision dispatched as `BounceDecided`.
- `BounceNotice`, `NoticeKind`, `NoticeSource`, `BounceDecision`, and `BounceNotices`, which reads the reports of the
  bounce parser, the signed return path and the events of a webhook.
- `BounceHistoryRepository`, with `CacheBounceHistoryRepository` and `InMemoryBounceHistoryRepository`.
- `MailerEventConsumer`, which consumes the mailer events of a provider's webhook through RemoteEvent.
- `BounceIngestor` and the command `phil:email-address:read-bounce`, for a raw message from a file or the standard
  input.
- The port `BounceMailbox`, `MaildirBounceMailbox`, `MailboxPoller` and the command `phil:email-address:poll-mailbox`.
- `BounceDecidedMessage` and `ForwardBounceDecision`, which send each decision on through Messenger without the
  address.
- The validator messages of `DeliverableEmailAddress` in French, and the field's messages in English and French.
- The Flex recipe, in `recipe/0.1`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
