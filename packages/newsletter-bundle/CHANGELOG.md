# Changelog

All notable changes to `phil/newsletter-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.4.1-alpha - 2026-11-25

### Security

- Requires `twig/twig` 3.31.1 or later. Before it, the sandbox in which `SandboxedTemplates` renders the templates of
  administrators and members let `join`, `first` and `last` read the public properties of an object of the context, and
  an assignment, a test or a spread argument call `__toString()` without asking the policy (advisories of 2026-11-24).
  The allowlist is unchanged; a test renders each of these against an object of the context and checks that nothing of
  it comes out.

## 0.4.0-alpha - 2026-11-24

A campaign is sent once approved, by default, and the tables gain a table, columns and
an index. See [Upgrading to 0.3](doc/campaigns.md#upgrading-to-03).

### Added

- Approval before sending: `approval.required`, on by default, `approval.role` (`ROLE_NEWSLETTER_APPROVER`) and
  `approval.four_eyes`; `Campaigns::approve()` and `withdrawApproval()`, `Campaign::approve()`, `withdrawApproval()`
  and `isApproved()`, the author of a campaign, `CampaignApproval` with who approved it and when, `ApprovalPolicy`,
  `ApprovalRefused` and the event `CampaignApproved`. An edit of a campaign withdraws its approval; a scheduled
  campaign whose time has come waits for its approval.
- Send runs: `SendRun`, one per attempt at sending a campaign, its number, state, counts — queued, sent, failed,
  skipped, bounced, complained — and dates, `SendRunStatus`, `SendRunCounter`, the port `SendRunRepository` and
  `DbalSendRunRepository` on the table `phil_newsletter_send_run`, and `SendRuns`, whose counts each worker adds to in
  one statement; each delivery of a campaign names its run, and no run sends twice to one recipient.
- The circuit breaker of a send run, `circuit_breaker`, on by default: `BreakerPolicy` pauses a run once, of at least
  `minimum` messages tried since it started or was resumed, the share of complaints, bounces or transport failures
  passes its threshold, and dispatches `SendRunPaused` with its `PauseReason`; `SendRuns::resume()` by an operator
  queues again what the run holds and dispatches `SendRunResumed`; `pause()` and `cancel()` for an operator, and
  `SendRunCompleted` once every message of a run was sent or skipped. A paused run holds its messages, queued.
- `DeliveryRepository::countOfRun()`, `pendingOfRun()` and `skipPendingOfRun()`.
- The migration from the tables of 0.2, tested on MySQL 8.4 and PostgreSQL 17 from tables holding rows.
- `TextPart`, the Mime `HtmlToTextConverterInterface` that writes the text part of a message from its HTML, the
  address of every web link between angle brackets after its words, the head, the styles and what is hidden left out,
  with a fuzz target.

### Changed

- `NewsletterSchema::configure()` adds the columns and the regular indexes the tables of the newsletter lack, besides
  the tables a schema lacks: the comparison of the schema introspected with the schema configured is the migration.
- `Campaign::start()` refuses a campaign that awaits its approval, unless it is told approval is not required;
  `Campaigns::sendNow()` starts a send run and names it in `SendCampaign`.
- `Campaigns` takes the `SendRuns`, then an optional `ApprovalPolicy`, authorization checker, token storage and event
  dispatcher; `SendCampaignHandler` takes the `SendRuns` and whether approval is required; `SendDeliveryHandler` and
  `BounceHandler` take the `SendRuns` as their last, optional argument.
- The development requirements accept `phil/privacy-bundle` 0.3 and `phil/standard-webhooks-bundle` 0.2, the minor
  versions of their main branches and no earlier one, so that the package installs on its own beside those bundles.
- `SendDeliveryHandler` and `SendConfirmationHandler` take a Mailer `TransportInterface`, the service
  `mailer.transports`, instead of `MailerInterface`.
- `ContentRenderer` draws the text part with `TextPart` instead of Mime's `DefaultHtmlToTextConverter`, and no longer
  decodes the entities of what its converter returns.
- Requires the latest minor of each dependency (`doctrine/dbal` 4.6, `twig/twig` 3.31, `phil/markup` 0.3,
  `symfony/event-dispatcher-contracts` 3.8, …), and of each development requirement (`doctrine/orm` 3.8,
  `doctrine/doctrine-bundle` 3.4).

### Security

- A campaign message or a confirmation no longer goes through `MailerInterface`, which put a second message on the bus
  when the application routed `SendEmailMessage`: the subscriber's address and the signed confirmation or
  unsubscription link waited in the Messenger transport and its failure transport. The handlers send through the
  Mailer transports, and a refusal of the transport fails the handler, for Messenger to retry its message, which
  carries identifiers only.
- The text part of a campaign, a confirmation or a message of a template keeps the address of each link: the
  unsubscription, the preference center and the confirmation of a double opt-in were only in the `href` of the HTML,
  so that a reader of the text part had no way to unsubscribe or confirm.
- Every message carries a `TrackingHeader` that turns off the mail provider's open and click tracking: a provider that
  tracks by default no longer adds a pixel per recipient nor rewrites the links, the signed ones included, through its
  own domain; `ClickTracker` alone counts the clicks of a campaign.
- The keys of the VERP tags and of the suppression hashes were derived from `secret` when the container was built,
  before an environment variable or `%kernel.secret%` had its value: they were an HMAC of the placeholder the
  configuration held, a value anyone could compute, which changed with the parameters of the application. A forged
  bounce could pass the VERP check, and the hashes of the forgotten addresses stopped matching after a change of
  parameters. `NewsletterKey::derive()` builds them from the value of `secret` at run time. Bounces of messages sent
  before the update no longer pass the VERP check, and a forgotten address that bounced or complained before it may
  subscribe again; a site whose `secret` was a literal value keeps its keys.
- The Flex recipe wrote into `.env` an example value of `NEWSLETTER_SECRET`: a site that kept it signed its links, its
  VERP tags and its suppression hashes with a key known to everyone. The recipe writes the variable empty, with a
  comment on how to fill it, and `NewsletterKey::check()`, which `SignedLinks` calls, refuses a key that begins with
  `change-me` or `changeme`, as it refuses one shorter than 16 bytes.
- The limiter of the subscriptions keyed its counts by the HMAC of the address, of the client and of the subscriber
  under the secret itself, `%kernel.secret%` by default, the same hash other bundles kept for the same address.
  `SubscriptionRateLimiter` derives its key by HKDF-SHA256 under `phil_newsletter.subscription_limiter`
  (`SubscriptionRateLimiter::USAGE`). The counts start again at the update.

## 0.3.0-alpha - 2026-11-20

The public subscription form is limited by default, so that a post accepted before may
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

### Security

- The public subscription form had neither a limit nor a delay per address: each post of a double opt-in sent a new
  confirmation to the same address, so that anyone could flood an address that never asked for it and spend the
  sender's quota. The counts and the answers are the same whether the address is subscribed or not.

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
