<!-- phil-dev source-sha256=9d10f7ac2dd0b18ce6e3f0aefa3a9a72ae5440a9539174f0d2cdcdd24218ff7a -->
# Configuration

Chaque option, avec sa valeur par défaut.

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

## Replis

| Absent | Ce qui se passe |
|---|---|
| Un traducteur | Les gabarits sont écrits sous forme de leurs clés, paramètres remplacés |
| `current_recipient`, sans Symfony Security | Le centre ne montre les notifications de personne |
| `current_recipient`, avec Symfony Security et les routes du centre | Le conteneur s'arrête avec un message : aucune valeur par défaut ne lit l'identifiant d'utilisateur, qui peut être une adresse courriel |
| `mailer.transports` de Symfony Mailer et `digest.sender` | Aucun résumé n'est envoyé ; le calendrier n'en envoie aucun |
| Le service `notifier` de Symfony Notifier | En `fan_out`, les canaux autres que le centre et Web Push sont sautés comme `unavailable` |
| Un expéditeur pour un canal d'un type `first_success` | Le canal est sauté comme `unavailable` |
| phil/circuit-breaker-bundle, ou un circuit `notifications.*` | Aucun canal n'a de disjoncteur |
| phil/idempotency, avec `delivery.dedupe` activé | La configuration est refusée avec un message |
| Un `store` ou un `cache_pool`, avec `delivery.dedupe` activé | La configuration est refusée avec un message |
| symfony/rate-limiter, avec `delivery.rate_limiter` | La configuration est refusée avec un message |
| Un `cache_pool` ou un stockage de limiteur partagé, en production | Le conteneur s'arrête avec un message, sauf si `delivery.single_server` vaut true |
| Messenger | Les notifications et les résumés sont livrés sur-le-champ, un destinataire après l'autre |
| `framework.csrf_protection` | Le conteneur s'arrête avec un message |
| `web_push.subject`, avec `web_push` activé | La configuration est refusée avec un message |
| minishlink/web-push ou Symfony HttpClient, avec `web_push` activé et sans `web_push_sender` | Le conteneur s'arrête avec un message qui nomme les paquets à installer |
| Le composant Asset et `web_push.worker` | Le bouton Web Push n'est pas affiché |
