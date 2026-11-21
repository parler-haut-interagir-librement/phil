<!-- phil-dev source-sha256=4cccd83ae8fb53337a6bb064c9f397473b96aeb966e62de302eea78c0aad43ac -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

## Services à remplacer

| Service | Contrat | Celui du bundle |
|---|---|---|
| `Phil\NewsletterBundle\Application\Contract\CurrentSite` | `site(): Site`, le site de la requête | `ConfiguredSite`, le site configuré |
| `Phil\NewsletterBundle\Application\Contract\SegmentResolver` | `recipients(Campaign, DateTimeImmutable): iterable<string>`, les identifiants des abonnés, chacun une fois | `ListRecipients`, la liste entière |
| `Phil\NewsletterBundle\Application\Contract\ConfirmationThrottle` | `allowsConfirmation(string $subscriberId): bool`, une place prise pour une confirmation, ou `false` dans le délai | `SubscriptionRateLimiter`, avec `subscription.rate_limit` |
| `Phil\NewsletterBundle\Application\Contract\SendThrottle` | `wait(SenderProfile, string $domain): int`, 0 et une place prise, ou les secondes à attendre | `RateLimiterSendThrottle` |
| `Phil\NewsletterBundle\Application\Contract\BounceMailbox` | `unread(int $limit)`, les messages bruts par identifiant ; `acknowledge(string $id)` | `ImapBounceMailbox`, avec `bounces.imap` |
| `Phil\NewsletterBundle\Application\Contract\AudienceWebhookSender` | `send(string $url, string $body, string $id)`, qui lève `WebhookNotDelivered` | `StandardWebhooksAudienceSender`, avec `webhooks` |
| `Phil\EmailAddress\EmailValidator` | Le validateur d'adresses pendant la requête ; celui de l'application quand elle en définit un | La syntaxe, les domaines de premier niveau et les domaines jetables, pas de DNS |
| `phil_newsletter.mail_domains` | Le `Phil\EmailAddress\Contract\MailDomainResolver` que le worker de confirmation interroge, avec `check_mail_domain` | `DnsGetRecordResolver` ; celui de l'application en décorant le service |
| `Phil\Markup\Markup` | Le compilateur de Markdown ; celui de l'application quand elle en définit un | Le compilateur et l'assainisseur par défaut de `phil/markup` |

Les dépôts sont aussi des services, par leurs interfaces sous `Phil\NewsletterBundle\Domain\Contract` :
`MailingListRepository`, `SubscriberRepository`, `CampaignRepository`, `DeliveryRepository`, `TemplateRepository`,
`EngagementRepository`, `SuppressionRepository` et `SendRunRepository`. Un `SendRunRepository` ajoute à un compte dans le
stockage, ne l'écrase jamais, et ne change l'état d'une exécution qu'à partir de l'état dans lequel il a été lu, pour que
les *workers* d'une même exécution ne se fassent rien perdre les uns aux autres.

## Les événements

| Événement | Distribué |
|---|---|
| `Phil\NewsletterBundle\Application\Event\AudienceChanged` | Après la sauvegarde de chaque changement d'un abonné : son type, l'identifiant de l'abonné, le site, la liste et l'identifiant externe |
| `Phil\NewsletterBundle\Application\Event\LinkClicked` | Après le comptage d'un clic par destinataire |
| `Phil\NewsletterBundle\Application\Event\CampaignApproved` | Après la sauvegarde d'une approbation : l'identifiant de la campagne et l'instant |
| `Phil\NewsletterBundle\Application\Event\SendRunPaused` | Après la mise en pause d'une exécution d'envoi, par son disjoncteur ou par un opérateur : les identifiants de l'exécution et de la campagne, le `PauseReason` et l'instant |
| `Phil\NewsletterBundle\Application\Event\SendRunResumed` | Après la reprise d'une exécution d'envoi par un opérateur : les identifiants de l'exécution et de la campagne et l'instant |
| `Phil\NewsletterBundle\Application\Event\SendRunCompleted` | Après que chaque message d'une exécution d'envoi a été envoyé ou ignoré : les identifiants de l'exécution et de la campagne et l'instant |

## Les gabarits

| Gabarit | Ce que c'est |
|---|---|
| `@PhilNewsletter/pages/layout.html.twig` | La page que les pages publiques étendent |
| `@PhilNewsletter/pages/*.html.twig` | Les pages d'abonnement, de confirmation, de désinscription, de préférences et de page manquante |
| `@PhilNewsletter/email/layout.html.twig` | La mise en page de la maison des campagnes |
| `@PhilNewsletter/email/confirmation.html.twig` | La confirmation d'une double confirmation, blocs `subject` et `html` |
| `@PhilNewsletter/editor/markdown.html.twig` | L'éditeur Markdown |

Remplacez-les sous `templates/bundles/PhilNewsletterBundle/`. Les textes sont dans le domaine `phil_newsletter`.

## Les messages

`SendCampaign`, `SendDelivery`, `SendConfirmation`, `DispatchDueCampaigns`, `FetchBounces` et
`DeliverAudienceWebhook`, sous `Phil\NewsletterBundle\Application\Message`, ne portent que des identifiants ;
acheminez-les vers les transports que choisit l'application.
