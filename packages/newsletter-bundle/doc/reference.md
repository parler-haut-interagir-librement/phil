# Reference

Every class of the bundle, with its role.

| Type | Kind | Role |
|---|---|---|
| `Phil\NewsletterBundle\Application\Bounces\BounceHandler` | `final readonly class` | What a bounce does to the subscriber it is about, whether it came back to the bounce mailbox — read by `phil/bounce-parser` |
| `Phil\NewsletterBundle\Application\Bounces\BounceOutcome` | enum | What a bounce did to a subscriber |
| `Phil\NewsletterBundle\Application\Campaigns` | `final readonly class` | Sending a campaign now or later, cancelling it, and what it gave |
| `Phil\NewsletterBundle\Application\Content\ContentRenderer` | `final readonly class` | Turns what an editor wrote into a message |
| `Phil\NewsletterBundle\Application\Content\LinkRewriter` | `final readonly class` | Rewrites the links of a message's body for click tracking |
| `Phil\NewsletterBundle\Application\Content\RangeRefusal` | `final readonly class` | Refuses, when a sandboxed template compiles, the range operator `1..1000000` |
| `Phil\NewsletterBundle\Application\Content\RenderedMessage` | `final readonly class` | A message ready to send |
| `Phil\NewsletterBundle\Application\Content\SandboxedTemplates` | `final class` | Renders the templates administrators and members write, never with the application's Twig |
| `Phil\NewsletterBundle\Application\Contract\AudienceWebhookSender` | interface | Sends one signed webhook of an audience event to a receiver |
| `Phil\NewsletterBundle\Application\Contract\BounceMailbox` | interface | The mailbox the bounces come back to |
| `Phil\NewsletterBundle\Application\Contract\CurrentSite` | interface | The site a request is for |
| `Phil\NewsletterBundle\Application\Contract\SegmentResolver` | interface | Chooses the recipients of a campaign |
| `Phil\NewsletterBundle\Application\Contract\SendThrottle` | interface | The pace of sending |
| `Phil\NewsletterBundle\Application\Event\AudienceChanged` | `final readonly class` | Something happened to a subscriber |
| `Phil\NewsletterBundle\Application\Event\LinkClicked` | `final readonly class` | A subscriber clicked a link of a campaign that counts clicks per recipient |
| `Phil\NewsletterBundle\Application\Exception\LinkRefused` | `final class` | A link of the newsletter that is not signed by it, is for something else, or expired |
| `Phil\NewsletterBundle\Application\Exception\NotFound` | `final class` | A list, a subscriber, a campaign or a template that is not there |
| `Phil\NewsletterBundle\Application\Exception\SubscriptionRefused` | `final class` | An address the newsletter refuses |
| `Phil\NewsletterBundle\Application\Exception\WebhookNotDelivered` | `final class` | An audience webhook the receiver did not accept |
| `Phil\NewsletterBundle\Application\Handler\DeliverAudienceWebhookHandler` | `final readonly class` | Sends an audience event to a configured receiver, as a Standard Webhooks delivery |
| `Phil\NewsletterBundle\Application\Handler\DispatchDueCampaignsHandler` | `final readonly class` | Starts the scheduled campaigns whose time has come |
| `Phil\NewsletterBundle\Application\Handler\FetchBouncesHandler` | `final readonly class` | Reads the bounce mailbox |
| `Phil\NewsletterBundle\Application\Handler\NewsletterSchedule` | `final readonly class` | The schedule `newsletter` of Symfony Scheduler |
| `Phil\NewsletterBundle\Application\Handler\SendCampaignHandler` | `final readonly class` | Queues the messages of a campaign |
| `Phil\NewsletterBundle\Application\Handler\SendConfirmationHandler` | `final readonly class` | Sends the confirmation of a double opt-in, with its signed link, valid seven days, after the domain check when a resolver is given |
| `Phil\NewsletterBundle\Application\Handler\SendDeliveryHandler` | `final readonly class` | Sends one queued message, at its time |
| `Phil\NewsletterBundle\Application\ListRecipients` | `final readonly class` | The bundle's own recipients |
| `Phil\NewsletterBundle\Application\Message\DeliverAudienceWebhook` | `final readonly class` | Asks a worker to send one audience event to one configured receiver |
| `Phil\NewsletterBundle\Application\Message\DispatchDueCampaigns` | `final readonly class` | Asks a worker to start the scheduled campaigns whose time has come |
| `Phil\NewsletterBundle\Application\Message\FetchBounces` | `final readonly class` | Asks a worker to read the bounce mailbox |
| `Phil\NewsletterBundle\Application\Message\SendCampaign` | `final readonly class` | Asks a worker to queue the messages of a campaign |
| `Phil\NewsletterBundle\Application\Message\SendConfirmation` | `final readonly class` | Asks a worker to send the confirmation of a double opt-in |
| `Phil\NewsletterBundle\Application\Message\SendDelivery` | `final readonly class` | Asks a worker to send one queued message |
| `Phil\NewsletterBundle\Application\NewsletterUrls` | `final readonly class` | The absolute addresses of the newsletter's pages, written into messages a worker sends without a request |
| `Phil\NewsletterBundle\Application\Sending\MessageComposer` | `final readonly class` | Builds the email of one delivery |
| `Phil\NewsletterBundle\Application\Sending\SenderProfile` | `final readonly class` | Who a message comes from and how it goes out |
| `Phil\NewsletterBundle\Application\Sending\SenderProfiles` | `final readonly class` | The sender profiles of the configuration, by name, and the one a list or a campaign without its own uses |
| `Phil\NewsletterBundle\Application\SignedLink` | `final readonly class` | What a signed link says, once its signature is checked |
| `Phil\NewsletterBundle\Application\SignedLinks` | `final readonly class` | The links of the newsletter's messages and pages — confirm, unsubscribe, preferences, click —, signed so that nobody can make one for another subscriber |
| `Phil\NewsletterBundle\Application\SubscribeOutcome` | enum | What a subscription did |
| `Phil\NewsletterBundle\Application\Subscriptions` | `final readonly class` | Subscribing, confirming, leaving, pausing and forgetting |
| `Phil\NewsletterBundle\Application\Tracking\ClickTracker` | `final readonly class` | Follows the tracked link of a message |
| `Phil\NewsletterBundle\Domain\Contract\CampaignRepository` | interface | Where the campaigns, and the links their messages track, are kept |
| `Phil\NewsletterBundle\Domain\Contract\DeliveryRepository` | interface | Where the deliveries are kept |
| `Phil\NewsletterBundle\Domain\Contract\EngagementRepository` | interface | Where clicks and the reasons people leave are counted |
| `Phil\NewsletterBundle\Domain\Contract\MailingListRepository` | interface | Where the lists are kept |
| `Phil\NewsletterBundle\Domain\Contract\SubscriberRepository` | interface | Where the subscribers and their memberships are kept |
| `Phil\NewsletterBundle\Domain\Contract\SuppressionRepository` | interface | The addresses never to mail again — bounced, complained —, kept as a keyed hash, so that the list outlives an erasure without keeping the address |
| `Phil\NewsletterBundle\Domain\Contract\TemplateRepository` | interface | Where the templates administrators and members write are kept |
| `Phil\NewsletterBundle\Domain\Enum\AudienceEventType` | enum | What happened to a subscriber, for listeners, automations and outgoing webhooks |
| `Phil\NewsletterBundle\Domain\Enum\CampaignStatus` | enum | Where a campaign stands |
| `Phil\NewsletterBundle\Domain\Enum\ClickTracking` | enum | How the clicks of a campaign are counted |
| `Phil\NewsletterBundle\Domain\Enum\DeliveryStatus` | enum | What became of one message to one subscriber |
| `Phil\NewsletterBundle\Domain\Enum\LinkPurpose` | enum | What a signed link of the newsletter is for |
| `Phil\NewsletterBundle\Domain\Enum\MembershipStatus` | enum | A subscriber's place on one list: `pending`, `active`, `unsubscribed`, or `refused` when the domain receives no mail |
| `Phil\NewsletterBundle\Domain\Enum\SubscriberStatus` | enum | What a subscriber is to the site's newsletter as a whole |
| `Phil\NewsletterBundle\Domain\Enum\UnsubscribeReason` | enum | Why someone left, as they chose it, kept without anything that names them |
| `Phil\NewsletterBundle\Domain\Exception\InvalidNewsletterValue` | `final class` | A value the newsletter refuses |
| `Phil\NewsletterBundle\Domain\Exception\NewsletterException` | interface | Every exception of the bundle |
| `Phil\NewsletterBundle\Domain\Model\Campaign` | `final class` | One message sent to a list, or to a segment of it |
| `Phil\NewsletterBundle\Domain\Model\Delivery` | `final class` | One message to one subscriber |
| `Phil\NewsletterBundle\Domain\Model\MailingList` | `final class` | A list people subscribe to, on one site |
| `Phil\NewsletterBundle\Domain\Model\Membership` | `final class` | A subscriber's place on one list |
| `Phil\NewsletterBundle\Domain\Model\Subscriber` | `final class` | A person who subscribed on one site |
| `Phil\NewsletterBundle\Domain\Model\Template` | `final class` | A template an administrator or a member wrote |
| `Phil\NewsletterBundle\Domain\ValueObject\SendWindow` | `final readonly class` | The hours a sender profile sends in |
| `Phil\NewsletterBundle\Domain\ValueObject\Site` | `final readonly class` | The site a list, a subscriber, a campaign or a template belongs to |
| `Phil\NewsletterBundle\Infrastructure\Automations\AudienceTrigger` | `final readonly class` | The triggers `newsletter.subscribed`, `newsletter.confirmed`, `newsletter.unsubscribed`, `newsletter.paused` and `newsletter.resumed` |
| `Phil\NewsletterBundle\Infrastructure\Automations\LinkClickedTrigger` | `final readonly class` | The trigger `newsletter.link_clicked` |
| `Phil\NewsletterBundle\Infrastructure\Automations\Lists` | `final class` | The run's subscriber and the list an action's option names, on the subscriber's site |
| `Phil\NewsletterBundle\Infrastructure\Automations\SendAction` | `final readonly class` | The action `newsletter.send` |
| `Phil\NewsletterBundle\Infrastructure\Automations\SubscribeAction` | `final readonly class` | The action `newsletter.subscribe` |
| `Phil\NewsletterBundle\Infrastructure\Automations\UnsubscribeAction` | `final readonly class` | The action `newsletter.unsubscribe` |
| `Phil\NewsletterBundle\Infrastructure\Console\FetchBouncesCommand` | `final readonly class` | Reads the bounce mailbox now, as the schedule does every five minutes |
| `Phil\NewsletterBundle\Infrastructure\Dbal\DbalCampaignRepository` | `final readonly class` | The campaigns in the table `phil_newsletter_campaign`, the links they track in `phil_newsletter_link` |
| `Phil\NewsletterBundle\Infrastructure\Dbal\DbalDeliveryRepository` | `final readonly class` | The deliveries in the table `phil_newsletter_delivery`, each created once by an insert that leaves a row of the same id alone |
| `Phil\NewsletterBundle\Infrastructure\Dbal\DbalEngagementRepository` | `final readonly class` | The clicks in `phil_newsletter_click` (a total per link) and `phil_newsletter_recipient_click` (per recipient, when a campaign chose it), the reasons to leave in `phil_newsletter_unsubscribe_reason`, a total per site, list, reason and UTC day |
| `Phil\NewsletterBundle\Infrastructure\Dbal\DbalMailingListRepository` | `final readonly class` | The lists in the table `phil_newsletter_list` |
| `Phil\NewsletterBundle\Infrastructure\Dbal\DbalSubscriberRepository` | `final readonly class` | The subscribers in the table `phil_newsletter_subscriber`, their memberships in `phil_newsletter_membership` |
| `Phil\NewsletterBundle\Infrastructure\Dbal\DbalSuppressionRepository` | `final readonly class` | The suppressed addresses in the table `phil_newsletter_suppression`, each as an HMAC-SHA256 of the site and the address under the application's key |
| `Phil\NewsletterBundle\Infrastructure\Dbal\DbalTemplateRepository` | `final readonly class` | The templates in the table `phil_newsletter_template`; a shared template has an empty site |
| `Phil\NewsletterBundle\Infrastructure\Dbal\NewsletterSchema` | `final class` | The tables of the newsletter, added to a schema that lacks them |
| `Phil\NewsletterBundle\Infrastructure\Dbal\NewsletterSchemaListener` | `final readonly class` | Adds the newsletter's tables to the schema the ORM generates for the bundle's connection |
| `Phil\NewsletterBundle\Infrastructure\Dbal\Sql` | `final class` | What the DBAL repositories share |
| `Phil\NewsletterBundle\Infrastructure\DependencyInjection\NewsletterServices` | `final class` | The services of the bundle, from its processed configuration |
| `Phil\NewsletterBundle\Infrastructure\Http\ClickController` | `final readonly class` | Redirects a tracked link to the address its campaign holds, counting the click unless a machine asked; a link that is not the newsletter's gets a `404` |
| `Phil\NewsletterBundle\Infrastructure\Http\PreviewController` | `final readonly class` | The preview of the Markdown editor |
| `Phil\NewsletterBundle\Infrastructure\Http\SubscriptionController` | `final readonly class` | The public pages of the newsletter |
| `Phil\NewsletterBundle\Infrastructure\Imap\Exception\ImapFailed` | `final class` | The IMAP server could not be reached, refused a command, or answered outside the protocol or the limits |
| `Phil\NewsletterBundle\Infrastructure\Imap\ImapBounceMailbox` | `final readonly class` | The bounce mailbox on an IMAP server |
| `Phil\NewsletterBundle\Infrastructure\Imap\ImapClient` | `final class` | The few IMAP4rev1 commands a bounce mailbox needs, over a stream the caller opens |
| `Phil\NewsletterBundle\Infrastructure\Privacy\NewsletterPrivacy` | `final readonly class` | The newsletter's part of an access or erasure request of `phil/privacy-bundle`, the data subject being the external id the application gave its subscribers, on every site |
| `Phil\NewsletterBundle\Infrastructure\RateLimiter\RateLimiterSendThrottle` | `final readonly class` | The pace of sending on Symfony's rate limiter |
| `Phil\NewsletterBundle\Infrastructure\RemoteEvent\MailerEventConsumer` | `final readonly class` | The events a mail provider's webhook sends, as Symfony's Webhook component and the provider's Mailer bridge parse them |
| `Phil\NewsletterBundle\Infrastructure\Site\ConfiguredSite` | `final readonly class` | The site of the configuration, `default` unless it names another |
| `Phil\NewsletterBundle\Infrastructure\Webhook\AudienceWebhookListener` | `final readonly class` | Sends each audience event to every configured receiver that asked for its type, through Messenger, so that a slow receiver never holds the request that changed the subscriber |
| `Phil\NewsletterBundle\Infrastructure\Webhook\StandardWebhooksAudienceSender` | `final readonly class` | The audience webhooks sent by `phil/standard-webhooks-bundle`'s sender |
| `Phil\NewsletterBundle\PhilNewsletterBundle` | `final class` | A site's newsletter |
