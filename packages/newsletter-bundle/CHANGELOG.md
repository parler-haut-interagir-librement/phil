# Changelog

All notable changes to `phil/newsletter-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.3.0-alpha: the public subscription form is limited by default, so that a post accepted before may
be answered `429`, and a confirmation of a double opt-in goes to the same subscriber once in five minutes at most;
`subscription.rate_limit: false` turns the limits off.

### Added

- `subscription.rate_limit`, on by default: `per_ip` (`limit` 10, `interval` 1 hour), `per_address` (`limit` 5,
  `interval` 1 hour, on each site) and `resend_delay` (5 minutes), counted by Symfony's Rate Limiter in the pool of
  `rate_limiter_storage`, under `lock.factory` when it is defined, with `Infrastructure\RateLimiter\SubscriptionRateLimiter`
  and the service `phil_newsletter.subscription_limiter`. A subscription refused by a count answers `429` with the form
  and `subscribe.error.too_many`, in English and in French. An interval or a delay that is not a positive duration is
  refused when the container is built.
- `Application\Contract\ConfirmationThrottle`, the delay before another confirmation goes to the same subscriber, which
  `Subscriptions` takes as its last argument, and `SubscribeOutcome::ConfirmationDelayed`: a double opt-in whose
  confirmation was sent within the delay keeps its place, pending, and sends and announces nothing.

### Changed

- `SubscriptionController` takes the `SubscriptionRateLimiter` as its last argument.
- `phil/standard-webhooks-bundle` 0.2 is accepted beside 0.1 in `require-dev`.
- `SendDeliveryHandler` and `SendConfirmationHandler` take a Mailer `TransportInterface`, the service
  `mailer.transports`, instead of `MailerInterface`.

### Security

- The public subscription form had neither a limit nor a delay per address: each post of a double opt-in sent a new
  confirmation to the same address, so that anyone could flood an address that never asked for it and spend the
  sender's quota. The counts and the answers are the same whether the address is subscribed or not.
- A campaign message or a confirmation no longer goes through `MailerInterface`, which put a second message on the bus
  when the application routed `SendEmailMessage`: the subscriber's address and the signed confirmation or
  unsubscription link waited in the Messenger transport and its failure transport. The handlers send through the
  Mailer transports, and a refusal of the transport fails the handler, for Messenger to retry its message, which
  carries identifiers only.

## 0.2.0-alpha - 2026-11-18

Identifiers, sites, keys, address keys and hashes compare byte by byte on MySQL and
MariaDB. See [Upgrading from 0.1](doc/storage.md#upgrading-from-01).

### Added

- `NewsletterSchema::BINARY_COLUMNS`, the columns that compare byte by byte on MySQL and MariaDB.

### Changed

- `NewsletterSchema::configure()` takes the platform of the connection as its second argument, and the schema listener
  gives it: on MySQL and MariaDB, the columns of `NewsletterSchema::BINARY_COLUMNS` — identifiers, sites, list and
  template keys, `email_key`, `external_id` and the hashes — take the `utf8mb4` character set and the `utf8mb4_bin`
  collation. PostgreSQL and SQLite keep their default, which compares bytes already.
- It accepts `phil/markup` 0.3 beside 0.2.

### Fixed

- Under the default collation of MySQL and MariaDB, which folds accents, the address `müller@example.com` was refused
  as already subscribed once `muller@example.com` was, and read as that subscriber; the lists, templates and
  subscribers of the site `Acme` were those of `acme`.

## 0.1.0-alpha - 2026-11-15

### Added

- `PhilNewsletterBundle` and its configuration `phil_newsletter`: the key of the signed links, the connection, the
  site and each site's base address, the sender profiles, the clicks, the bounces, the audience webhooks, the role of
  the preview.
- The domain: `MailingList`, `Subscriber`, `Membership`, `Campaign`, `Delivery`, `Template`, `Site`, `SendWindow`, their
  enums, and the repository ports under `Domain\Contract`.
- `Subscriptions`: subscribing with addresses validated by `phil/email-address`, the double opt-in, confirming,
  joining, leaving one list or all with an anonymous reason, pausing, resuming, forgetting, each announced by
  `AudienceChanged`; the request that subscribes never waits on the DNS.
- `SignedLinks`: the confirm, unsubscribe, preferences and click links, signed with HMAC-SHA256, checked in constant
  time, bounded and with an expiry.
- `ContentRenderer`, `SandboxedTemplates`, `RangeRefusal` and `LinkRewriter`: Markdown through `phil/markup`, the house
  layout, templates of administrators and members rendered in `Twig\Sandbox\Sandbox` with a strict policy, the range
  operator refused and the output bounded.
- `Campaigns`, `SendCampaignHandler`, `SendDeliveryHandler`, `SendConfirmationHandler`, `DispatchDueCampaignsHandler`
  and `NewsletterSchedule`: campaigns sent now or at their time, one delivery per recipient, the checks at sending,
  send windows and pace through Messenger delays; with `check_mail_domain`, the confirmation handler asks the DNS
  whether the address's domain receives mail, and a domain without MX, A nor AAAA record, or with a null MX, gets no
  confirmation, its subscription `refused` and a notice logged with identifiers only.
- `SenderProfile`, `SenderProfiles`, `MessageComposer` and `RateLimiterSendThrottle`: one transport per profile through
  `X-Transport`, RFC 8058 one-click unsubscription, `List-Id`, VERP return paths, Mailer metadata, pace per recipient
  domain and daily ceilings on Symfony's rate limiter.
- `ClickTracker`: links redirected only to the addresses their campaign holds, clicks in a total or per recipient,
  machines left out by method, prefetch headers and time.
- `BounceHandler`, `FetchBouncesHandler`, `ImapClient`, `ImapBounceMailbox` and `MailerEventConsumer`: bounces from an
  IMAP mailbox read by `phil/bounce-parser` over implicit TLS on port 993 only, as RFC 8314 recommends, and from
  providers' webhooks, soft bounces to a threshold, suppression.
- `SubscriptionController`, `ClickController`, `PreviewController`, the routes, the pages and their English and French
  texts, and the Stimulus controller of the Markdown editor.
- The triggers `newsletter.subscribed`, `newsletter.confirmed`, `newsletter.unsubscribed`, `newsletter.paused`,
  `newsletter.resumed` and `newsletter.link_clicked`, and the actions `newsletter.send`, `newsletter.subscribe` and
  `newsletter.unsubscribe`, for `phil/automations`.
- `NewsletterPrivacy` for `phil/privacy-bundle`, and the audience webhooks through `phil/standard-webhooks-bundle`.
- The Doctrine DBAL repositories, `NewsletterSchema` and `NewsletterSchemaListener`, tested on MySQL 8.4 and
  PostgreSQL 17; a fuzz target for the signed links; `lint:container` run on an application with the bundle.
- The Flex recipe in `recipe/0.1`: the bundle registered, its configuration with one site and one sender profile, its
  routes under `/newsletter`, and `NEWSLETTER_SECRET` in `.env`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
