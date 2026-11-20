# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

## Services to replace

| Service | Contract | The bundle's |
|---|---|---|
| `Phil\NewsletterBundle\Application\Contract\CurrentSite` | `site(): Site`, the site of the request | `ConfiguredSite`, the configured one |
| `Phil\NewsletterBundle\Application\Contract\SegmentResolver` | `recipients(Campaign, DateTimeImmutable): iterable<string>`, the subscriber ids, each once | `ListRecipients`, the whole list |
| `Phil\NewsletterBundle\Application\Contract\ConfirmationThrottle` | `allowsConfirmation(string $subscriberId): bool`, a place taken for one confirmation, or `false` within the delay | `SubscriptionRateLimiter`, with `subscription.rate_limit` |
| `Phil\NewsletterBundle\Application\Contract\SendThrottle` | `wait(SenderProfile, string $domain): int`, 0 and a place taken, or the seconds to wait | `RateLimiterSendThrottle` |
| `Phil\NewsletterBundle\Application\Contract\BounceMailbox` | `unread(int $limit)`, raw messages by id; `acknowledge(string $id)` | `ImapBounceMailbox`, with `bounces.imap` |
| `Phil\NewsletterBundle\Application\Contract\AudienceWebhookSender` | `send(string $url, string $body, string $id)`, throwing `WebhookNotDelivered` | `StandardWebhooksAudienceSender`, with `webhooks` |
| `Phil\EmailAddress\EmailValidator` | The validator of addresses during the request; the application's when it defines one | Syntax, top-level domains and disposable domains, no DNS |
| `phil_newsletter.mail_domains` | The `Phil\EmailAddress\Contract\MailDomainResolver` the confirmation worker asks, with `check_mail_domain` | `DnsGetRecordResolver`; the application's by decorating the service |
| `Phil\Markup\Markup` | The compiler of Markdown; the application's when it defines one | `phil/markup`'s default compiler and purifier |

The repositories are services too, by their interfaces under `Phil\NewsletterBundle\Domain\Contract`:
`MailingListRepository`, `SubscriberRepository`, `CampaignRepository`, `DeliveryRepository`, `TemplateRepository`,
`EngagementRepository`, `SuppressionRepository` and `SendRunRepository`. A `SendRunRepository` adds to a count in the
store, never writes it over, and changes a run's state only from the state it was read in, so that the workers of one
run lose nothing to each other.

## Events

| Event | Dispatched |
|---|---|
| `Phil\NewsletterBundle\Application\Event\AudienceChanged` | After each change of a subscriber is saved: its type, the subscriber's id, the site, the list and the external id |
| `Phil\NewsletterBundle\Application\Event\LinkClicked` | After a click is counted per recipient |
| `Phil\NewsletterBundle\Application\Event\CampaignApproved` | After an approval is saved: the campaign's id and the instant |
| `Phil\NewsletterBundle\Application\Event\SendRunPaused` | After a send run is paused, by its circuit breaker or by an operator: the run's and the campaign's ids, the `PauseReason` and the instant |
| `Phil\NewsletterBundle\Application\Event\SendRunResumed` | After an operator resumed a send run: the run's and the campaign's ids and the instant |
| `Phil\NewsletterBundle\Application\Event\SendRunCompleted` | After every message of a send run was sent or skipped: the run's and the campaign's ids and the instant |

## Templates

| Template | What it is |
|---|---|
| `@PhilNewsletter/pages/layout.html.twig` | The page the public pages extend |
| `@PhilNewsletter/pages/*.html.twig` | The subscription, confirmation, unsubscription, preference and missing pages |
| `@PhilNewsletter/email/layout.html.twig` | The house layout of campaigns |
| `@PhilNewsletter/email/confirmation.html.twig` | The confirmation of a double opt-in, blocks `subject` and `html` |
| `@PhilNewsletter/editor/markdown.html.twig` | The Markdown editor |

Override them under `templates/bundles/PhilNewsletterBundle/`. The texts are in the domain `phil_newsletter`.

## Messages

`SendCampaign`, `SendDelivery`, `SendConfirmation`, `DispatchDueCampaigns`, `FetchBounces` and
`DeliverAudienceWebhook`, under `Phil\NewsletterBundle\Application\Message`, carry identifiers only; route them to the
transports of the application's choice.
