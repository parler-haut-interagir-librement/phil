<!-- phil-dev source-sha256=8e7d44cb04ea30f34effbd8517f54e71f10b98330977ebc583a2ae9e00f6b976 -->
# Configuration

La configuration du bundle se trouve sous `phil_newsletter` :

```yaml
# config/packages/phil_newsletter.yaml
phil_newsletter:
    secret: '%env(NEWSLETTER_SECRET)%'
    connection: doctrine.dbal.default_connection
    check_mail_domain: true
    site: default
    sites:
        default: { base_url: 'https://example.com' }
    default_profile: news
    profiles:
        news:
            from: 'news@example.com'
            from_name: 'Example'
            reply_to: 'hello@example.com'
            transport: newsletter
            unsubscribe_mailbox: 'leave@example.com'
            window: { days: [1, 2, 3, 4, 5], from: '09:00', until: '17:00', timezone: 'America/Toronto' }
            daily_cap: 5000
            rate: 120
            domain_rates: { gmail.com: 60, outlook.com: 60 }
    clicks:
        minimum_seconds: 10
    bounces:
        soft_threshold: 3
        verp: { local_part: bounces, domain: 'example.com', separator: '+' }
        imap: { host: 'imap.example.com', port: 993, user: 'bounces@example.com', password: '%env(BOUNCES_PASSWORD)%', folder: INBOX, delete: false }
        remote_event_consumer: mail_provider
    rate_limiter_storage: cache.rate_limiter
    subscription:
        rate_limit:             # "rate_limit: false" turns the limits off
            per_ip: { limit: 10, interval: '1 hour' }
            per_address: { limit: 5, interval: '1 hour' }
            resend_delay: '5 minutes'
    webhooks:
        crm: { url: 'https://crm.example.com/hooks/newsletter', events: [confirmed, unsubscribed] }
    preview_role: ROLE_EDITOR
    approval:
        required: true
        role: ROLE_NEWSLETTER_APPROVER
        four_eyes: true
    circuit_breaker:            # "circuit_breaker: false" turns it off
        minimum: 100
        transport_failure_rate: 10
        bounce_rate: 5
        complaint_rate: 0.3
```

| Option | Valeur par défaut | Ce qu'elle fait |
|---|---|---|
| `enabled` | `true` | Enregistre les services du bundle ; `false` n'en enregistre aucun. |
| `secret` | `%kernel.secret%` | La clé des liens signés, des étiquettes VERP et des empreintes de suppression : au moins 16 octets, jamais un substitut qui commence par `change-me` ou `changeme`. Les clés des étiquettes VERP et des empreintes de suppression sont dérivées de sa valeur à l'exécution, une variable d'environnement comprise. Une nouvelle clé casse les liens de chaque message envoyé. |
| `connection` | `doctrine.dbal.default_connection` | La connexion DBAL des tables. |
| `check_mail_domain` | `true` | Si le worker qui envoie la confirmation d'une double confirmation demande d'abord au DNS que le domaine de l'adresse reçoit du courriel, et refuse l'abonnement sinon ; la requête n'attend jamais le DNS. |
| `site` | `default` | Le site d'une application autonome. |
| `sites.<key>.base_url` | — | L'adresse de base de chaque site, avant les chemins des liens dans les messages. Sans elle, le contexte du routeur. |
| `default_profile` | `default` | Le profil d'une liste sans profil propre, et d'un gabarit envoyé seul. |
| `profiles.<name>` | un profil `default` de `newsletter@example.com` | Les profils d'expéditeur. Voir [Campagnes](campaigns.md#les-profils-dexpéditeur). |
| `clicks.minimum_seconds` | `10` | Un clic aussi proche de l'envoi de son message est celui d'une machine, non compté. |
| `bounces.soft_threshold` | `3` | Le nombre de rebonds souples d'affilée après lequel une adresse compte comme ayant rebondi. |
| `bounces.verp` | désactivé | Le chemin de retour signé de chaque message, pour qu'un rebond nomme son envoi. |
| `bounces.imap` | désactivé | La boîte des rebonds, lue en TLS implicite (port 993, RFC 8314 ; pas de STARTTLS) toutes les cinq minutes. |
| `bounces.remote_event_consumer` | `null` | Le nom de route du webhook du fournisseur de courriel dont le bundle consomme les événements. |
| `rate_limiter_storage` | `cache.rate_limiter` | Le groupe de cache où le rythme et les limites du formulaire d'abonnement sont comptés ; partagé par chaque *worker* et chaque serveur Web. |
| `subscription.rate_limit.enabled` | `true` | Limite le formulaire d'abonnement public et les confirmations d'une double confirmation ; `rate_limit: false` désactive les limites. Voir [Les limites du formulaire](subscriptions.md#les-limites-du-formulaire). |
| `subscription.rate_limit.per_ip.limit`, `.interval` | `10`, `1 hour` | Les abonnements par adresse de client, ou par réseau IPv6 /64, dans chaque intervalle. |
| `subscription.rate_limit.per_address.limit`, `.interval` | `5`, `1 hour` | Les abonnements par adresse saisie, sur chaque site, abonnée ou non, dans chaque intervalle. |
| `subscription.rate_limit.resend_delay` | `5 minutes` | Le délai avant qu'une autre confirmation soit envoyée au même abonné. |
| `webhooks.<name>` | aucun | Les récepteurs des événements d'audience ; un `events` vide les envoie tous. Exige `phil/standard-webhooks-bundle`. |
| `preview_role` | `ROLE_ADMIN` | Le rôle auquel répond l'aperçu de l'éditeur. |
| `approval.required` | `true` | Si une campagne n'est envoyée, ou démarrée à son heure, qu'une fois approuvée. Voir [Approbation](campaigns.md#approbation). |
| `approval.role` | `ROLE_NEWSLETTER_APPROVER` | Le rôle qui approuve les campagnes. |
| `approval.four_eyes` | `false` | Si l'approbateur doit être quelqu'un d'autre que l'auteur de la campagne. |
| `circuit_breaker.enabled` | `true` | Met en pause une exécution d'envoi dont les échecs de transport, les rebonds ou les plaintes montent ; `circuit_breaker: false` le désactive. Voir [Les exécutions d'envoi et le disjoncteur](campaigns.md#les-exécutions-denvoi-et-le-disjoncteur). |
| `circuit_breaker.minimum` | `100` | Les messages tentés, depuis le démarrage ou la reprise de l'exécution, avant que les parts soient lues. |
| `circuit_breaker.transport_failure_rate` | `10` | La part des tentatives que le transport de courriel a refusées, en pourcentage, au-delà de laquelle l'exécution se met en pause. |
| `circuit_breaker.bounce_rate` | `5` | La part des messages envoyés qui ont rebondi, en pourcentage, au-delà de laquelle l'exécution se met en pause. |
| `circuit_breaker.complaint_rate` | `0.3` | La part des messages envoyés signalés comme pourriel, en pourcentage, au-delà de laquelle l'exécution se met en pause. |

Chaque profil :

| Clé | Valeur par défaut | Ce qu'elle fait |
|---|---|---|
| `from`, `from_name` | — , `''` | L'expéditeur. |
| `reply_to` | `null` | L'adresse de réponse. |
| `transport` | `null` | Un transport de `framework.mailer.transports`, choisi avec `X-Transport` ; sinon le transport par défaut. |
| `unsubscribe_mailbox` | `null` | Une adresse ajoutée à `List-Unsubscribe` comme `mailto:`. |
| `window` | tous les jours, toute la journée, UTC | Les jours (1 pour lundi à 7) et les heures d'envoi des messages. |
| `daily_cap` | `null` | Le plus grand nombre de messages envoyés en une journée. |
| `rate` | `120` | Messages par minute vers un domaine de destinataire. |
| `domain_rates` | aucun | Messages par minute vers les domaines nommés. |
