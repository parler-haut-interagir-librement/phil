# Configuration

Every option, with its default.

```yaml
# config/packages/phil_notifications.yaml
phil_notifications:
    store: dbal                      # dbal, or memory for tests
    connection: default              # the Doctrine connection of the tables
    preferences: config              # config, dbal, or the id of a NotificationPreferences service
    recipient_directory: ~           # a RecipientDirectory service; none skips email and SMS
    current_recipient: ~             # a CurrentRecipient service naming members by a stable identifier; none by default
    web_push_sender: ~               # a WebPushSender service; the bundle's with web_push enabled, none otherwise
    message_bus: messenger.default_bus   # null delivers at once

    delivery:                        # see doc/delivery.md
        single_server: false         # true accepts a pool or a limiter storage local to the server in prod
        from: ~                      # the sender of the emails of first_success; the mailer's headers when null
        dedupe:
            enabled: false
            ttl: 300                 # the window of duplicates, in seconds, of a type that names none
            store: ~                 # an IdempotencyStore service of phil/idempotency
            cache_pool: ~            # or a pool shared by every server, for a CacheIdempotencyStore
            lock_factory: lock.factory
        rate_limiter: ~              # the name of a limiter of framework.rate_limiter, spent once per recipient

    defaults:                        # what a recipient has until they choose
        digest: never                # never, daily or weekly
        quiet_hours: { start: '22:00', end: '07:00', timezone: America/Toronto }   # none by default
        opt_outs: []                 # "type:channel" or "*:channel"
        locale: ~

    types:
        comment.replied:
            channels: [site, email]  # each recipient's channels; [site] by default
            broadcast: []            # posted to once, such as [chat/discord]
            collapse: false          # a later one about the same subject joins the unread one
            digest: true             # email waits for the digest of those who chose one
            toast: true
            importance: medium       # urgent, high, medium or low
            title: ~                 # the translation key; "comment.replied.title" when null
            body: ~                  # "comment.replied.body" when null
            delivery: fan_out        # fan_out, or first_success: the channels in turn until one accepts
            dedupe_ttl: ~            # null for delivery.dedupe.ttl, 0 for no window

    toasts:
        transport: flash             # mercure (by default with MercureBundle), flash or none
        hub: Symfony\Component\Mercure\HubInterface   # a hub reached by its URL to send from a command or a consumer
        topic_prefix: 'urn:phil:notifications:'
        private: true

    digest:
        enabled: true
        sender: ~                    # a DigestSender service; the transports of Symfony Mailer by default
        from: ~                      # the sender of the mailer's headers when null
        template: '@PhilNotifications/email/digest.html.twig'
        text_template: '@PhilNotifications/email/digest.txt.twig'
        preferences_route: ~         # the route of the application's page of preferences
        unsubscribe: true            # a signed link that stops the digest, in its footer and in List-Unsubscribe
        unsubscriber: ~              # a DigestUnsubscriber service; the "dbal" preferences by default
        tracking_consent: ~          # an EmailTrackingConsent service; none follows no one's opens nor clicks
        at: '07:00'
        weekday: monday
        limit: 50                    # notifications per digest, 1 to 500

    retention:
        read_days: 90                # read and archived notifications
        unread_days: 365
        purge_at: '03:30'

    schedule:
        enabled: true                # with Symfony Scheduler
        timezone: UTC

    remote:
        enabled: false
        receiver: ~                  # a RemoteNotificationReceiver; by default delivered as this site's own
        origins: []                  # the origins taken; any other is refused

    web_push:
        enabled: false
        subject: ~                   # required when enabled: mailto:ops@example.com or an https:// page
        public_key: '%env(PHIL_NOTIFICATIONS_VAPID_PUBLIC_KEY)%'
        private_key: '%env(PHIL_NOTIFICATIONS_VAPID_PRIVATE_KEY)%'
        ttl: 86400                   # seconds a push service keeps a message, 0 to 2419200
        push_services:               # the hosts an endpoint may be on, or under; [] takes any public host
            - fcm.googleapis.com
            - push.services.mozilla.com
            - notify.windows.com
            - push.apple.com
        max_subscriptions: 10        # browsers a recipient keeps subscribed, the latest, 1 to 100
        worker: ~                    # the service worker's URL; the bundle's, through AssetMapper, when null
        http_client: ~               # an HttpClient service; one of the bundle's own when null
        timeout: 10                  # seconds a push service has to answer, 1 to 60
```

## Fallbacks

| Missing | What happens |
|---|---|
| A translator | Templates are written as their keys, parameters replaced |
| `current_recipient`, without Symfony Security | The centre shows no one's notifications |
| `current_recipient`, with Symfony Security and the centre's routes | The container stops with a message: no default reads the user identifier, which can be an email address |
| Symfony Mailer's `mailer.transports` and `digest.sender` | No digest is sent; the schedule sends none |
| Symfony Notifier's `notifier` service | In `fan_out`, the channels other than the centre and Web Push are skipped as `unavailable` |
| A sender for a channel of a `first_success` type | The channel is skipped as `unavailable` |
| phil/circuit-breaker-bundle, or a circuit `notifications.*` | No channel has a breaker |
| phil/idempotency, with `delivery.dedupe` enabled | The configuration is refused with a message |
| A `store` or a `cache_pool`, with `delivery.dedupe` enabled | The configuration is refused with a message |
| symfony/rate-limiter, with `delivery.rate_limiter` | The configuration is refused with a message |
| A shared `cache_pool` or limiter storage, in the prod environment | The container stops with a message, unless `delivery.single_server` is true |
| Messenger | Notifications and digests are delivered at once, one recipient after the other |
| `framework.csrf_protection` | The container stops with a message |
| `web_push.subject`, with `web_push` enabled | The configuration is refused with a message |
| minishlink/web-push or Symfony HttpClient, with `web_push` enabled and no `web_push_sender` | The container stops with a message naming the packages to install |
| The Asset component and `web_push.worker` | The Web Push button is not shown |
