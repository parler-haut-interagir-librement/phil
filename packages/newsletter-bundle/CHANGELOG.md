# Changelog

All notable changes to `phil/newsletter-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

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
