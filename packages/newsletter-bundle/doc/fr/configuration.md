<!-- phil-dev source-sha256=6903121eaaa565ca9fa16cabad163fe5e3b741147cf06bbec8011bda972c1025 -->
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
    webhooks:
        crm: { url: 'https://crm.example.com/hooks/newsletter', events: [confirmed, unsubscribed] }
    preview_role: ROLE_EDITOR
```

| Option | Valeur par défaut | Ce qu'elle fait |
|---|---|---|
| `enabled` | `true` | Enregistre les services du bundle ; `false` n'en enregistre aucun. |
| `secret` | `%kernel.secret%` | La clé des liens signés, des étiquettes VERP et des empreintes de suppression : au moins 16 octets. Une nouvelle clé casse les liens de chaque message envoyé. |
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
| `rate_limiter_storage` | `cache.rate_limiter` | Le groupe de cache où le rythme est compté ; partagé par chaque *worker*. |
| `webhooks.<name>` | aucun | Les récepteurs des événements d'audience ; un `events` vide les envoie tous. Exige `phil/standard-webhooks-bundle`. |
| `preview_role` | `ROLE_ADMIN` | Le rôle auquel répond l'aperçu de l'éditeur. |

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
