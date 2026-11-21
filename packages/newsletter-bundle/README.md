# phil/newsletter-bundle

**A site's newsletter in Symfony: people subscribe, confirm, choose their lists, pause or leave; editors write in
Markdown; messages go out through Symfony Mailer at a pace mail providers accept.**

phil/newsletter-bundle keeps lists and their subscribers, with a double opt-in when a list asks for one, and sends
campaigns written in Markdown, laid out by the bundle's own layout or by a template an administrator or a member wrote,
which is rendered in Twig's sandbox. Each message carries the one-click unsubscription mail clients show as a button,
and links to a preference center where a subscriber chooses their lists, pauses for a few weeks, or leaves, saying why
if they wish — a reason counted without anything that names them. Messages go out from sender profiles: an address, one
Mailer transport, the hours it sends in, a daily ceiling and a pace per recipient domain. A campaign goes out once a
user of the approving role approved it — someone other than its author, under the four-eyes rule —, under a send run
that counts what became of each message and pauses, until an operator resumes it, when transport failures, bounces or
complaints climb past their thresholds. Bounces come back to an IMAP mailbox read by `phil/bounce-parser`, or from a
provider's webhook through Symfony's RemoteEvent, and an address that bounced or complained is never mailed again.
Clicks are counted in a total per link by default; counting them per recipient is a campaign's explicit choice, and
machines that open links before people are left out without reading an IP address or a user agent. Nothing tracks the
opening of a message.

It is for any Symfony site that writes to its members or readers: a club's news, a shop's announcements, a game's
updates. One application may serve several sites, each with its own audience, sharing templates. With
`phil/automations`, the bundle offers its triggers and its send and list actions, for welcome sequences and
follow-ups; with `phil/privacy-bundle`, its part of access and erasure requests; with
`phil/standard-webhooks-bundle`, signed webhooks of its audience events to other services.

The bundle stores its data in tables of a Doctrine DBAL connection, on PostgreSQL, MySQL, MariaDB or SQLite.
Addresses are validated by `phil/email-address`, web addresses read by `phil/url`, Markdown compiled and purified by
`phil/markup`. Messages, Messenger jobs and webhooks carry identifiers only; the signed links of messages are checked
in constant time. The services keep nothing between requests, so the bundle runs on long-lived workers.

## Status

| Part | State |
|---|---|
| Lists, subscribers, memberships, double opt-in, multi-site, external ids | Implemented and tested |
| The subscription form, the confirmation, the one-click unsubscription (RFC 8058), the preference center with pause and an anonymous reason | Implemented and tested |
| Campaigns in Markdown, the house layout, templates rendered in `Twig\Sandbox\Sandbox`, the Markdown editor and its preview | Implemented and tested |
| Sending through Messenger and Symfony Mailer, sender profiles with one transport each, send windows, daily ceilings, pace per recipient domain | Implemented and tested |
| Approval before sending, by a configurable role, with an optional four-eyes rule; an edit withdraws it | Implemented and tested |
| Send runs, one per attempt, counted by every worker; a circuit breaker that pauses a run when transport failures, bounces or complaints climb, until an operator resumes it | Implemented and tested |
| A ramp of the pace after a pause, an automatic resumption, tests of a part of the audience before the rest | Not part of the bundle: the application builds them on the send runs |
| Click tracking in a total by default or per recipient, machines left out | Implemented and tested |
| Bounces from an IMAP mailbox and `phil/bounce-parser`, VERP, provider webhooks through RemoteEvent, suppression | Implemented and tested |
| The triggers and actions of `phil/automations`, the privacy module of `phil/privacy-bundle`, audience webhooks | Implemented and tested |
| Doctrine DBAL storage, tested on MySQL 8.4 and PostgreSQL 17; a fuzz target for the signed links | Implemented and tested |
| Segments chosen by an audience package | A port, `SegmentResolver`; the bundle's own reads whole lists |
| An administration of lists, campaigns and templates | Not part of the bundle: the application builds it on the services |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, the recipe, registering, the routes, the tables, the workers. |
| [Configuration](doc/configuration.md) | Every option, with an example. |
| [Subscriptions](doc/subscriptions.md) | Lists, the double opt-in, the limits of the form, the pages, leaving, pausing, the reasons, sites. |
| [Campaigns](doc/campaigns.md) | Writing, approval, the Markdown editor, the layouts, sandboxed templates, sending, send runs and the circuit breaker, sender profiles, upgrading. |
| [Tracking](doc/tracking.md) | Clicks in a total or per recipient, the machines left out, what is never tracked. |
| [Bounces](doc/bounces.md) | The IMAP mailbox, VERP, provider webhooks, soft and hard bounces, suppression. |
| [Integrations](doc/integrations.md) | Automations, privacy requests, audience webhooks, segments. |
| [Storage](doc/storage.md) | The tables, what they hold, what they never hold, and their migrations. |
| [Extension points](doc/extension-points.md) | The services to replace, the ports to implement, and when they are stable. |
| [Testing](doc/testing.md) | The bundle's tests, and the servers its integration tests read. |
| [Reference](doc/reference.md) | Every class and service of the bundle. |

## Quick start

```sh
composer require phil/newsletter-bundle
```

With Symfony Flex, the recipe writes what follows. Without it:

```php
// config/bundles.php
Phil\NewsletterBundle\PhilNewsletterBundle::class => ['all' => true],
```

```yaml
# config/packages/phil_newsletter.yaml
phil_newsletter:
    secret: '%env(NEWSLETTER_SECRET)%'
    sites:
        default: { base_url: 'https://example.com' }
    profiles:
        default: { from: 'news@example.com', from_name: 'Example' }
```

```yaml
# config/routes/phil_newsletter.yaml
phil_newsletter:
    resource: '@PhilNewsletterBundle/config/routes.php'
    prefix: /newsletter
```

## Requirements

PHP 8.5 or later, Symfony 8.2 or later, Twig 3.30 or later and Doctrine DBAL 4.5 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
