<!-- phil-dev source-sha256=23aaf4918b41d8b65deeb0eb6310adf97805900346a582fc82532121116698e1 -->
# Rebonds

Un rebond change l'abonné dont il s'agit :

| Rebond | Effet |
|---|---|
| Dur : boîte inexistante, domaine disparu | L'adresse est supprimée : `bounced`, plus jamais écrite |
| Souple : boîte pleine, serveur occupé | Compté ; à `bounces.soft_threshold`, trois par défaut, l'adresse est supprimée ; un message livré remet le compte à zéro |
| Plainte : un rapport d'abus | L'adresse est supprimée : `complained` |
| Une demande de départ | L'abonné est désinscrit |
| Une réponse automatique, tout le reste | Rien |

Une adresse supprimée est gardée dans `phil_newsletter_suppression` comme un HMAC du site et de l'adresse sous la clé du
bundle : elle survit à l'effacement de l'abonné sans garder l'adresse. Chaque changement est annoncé par un événement
`AudienceChanged`, `bounced` ou `complained`.

## La boîte des rebonds

Avec `bounces.imap`, la planification `newsletter` lit la boîte toutes les cinq minutes, et
`phil:newsletter:fetch-bounces` la lit tout de suite. Le client IMAP du bundle se connecte en TLS implicite, le
certificat vérifié, lit chaque message non lu sans le marquer, le remet à `phil/bounce-parser` — notifications d'état de
livraison, rapports d'abus, rebonds écrits en texte, réponses automatiques —, puis le marque comme lu, ou supprimé avec
`delete: true`. Chaque ligne et chaque message lu est borné.

Le client ne parle que le TLS implicite, sur le port 993 par défaut, comme le recommande la RFC 8314 : la connexion est
chiffrée dès son premier octet, et rien n'est jamais envoyé en clair. Il ne parle pas STARTTLS sur le port 143, qu'un
attaquant sur le chemin peut retirer quand un client ne l'exige pas ; tous les grands fournisseurs de courriel offrent
le port 993. Une boîte qui n'offre que STARTTLS est lue par un `BounceMailbox` de l'application (voir
[Points d'extension](extension-points.md)).

Avec `bounces.verp`, le chemin de retour de chaque message est `bounces+<delivery>.<signature>@example.com` : le rebond
nomme son envoi, signé pour que personne ne puisse faire rebondir un autre abonné, et l'abonné est retrouvé même quand le
rapport nomme une autre adresse. Sans lui, l'abonné est retrouvé par l'adresse en échec sur le site courant.

## Les webhooks des fournisseurs

Un fournisseur qui publie ses événements — par le composant Webhook de Symfony et le pont Mailer du fournisseur —
atteint `MailerEventConsumer` quand `bounces.remote_event_consumer` nomme la route de webhook du fournisseur :

```yaml
framework:
    webhook:
        routing:
            mail_provider:
                service: mailer.webhook.request_parser.<provider>
                secret: '%env(MAIL_PROVIDER_WEBHOOK_SECRET)%'
```

| Événement | Effet |
|---|---|
| `bounce`, `dropped` | Rebond dur |
| `deferred` | Rebond souple |
| `spam` | Plainte |
| `unsubscribe` | Une demande de départ |

L'envoi est retrouvé par la métadonnée que le bundle a posée sur chaque message, sinon l'abonné par l'adresse.
