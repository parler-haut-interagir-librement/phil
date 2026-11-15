<!-- phil-dev source-sha256=a34d190ae33ed43e5c892e671b9ee467a9907bc9f3ba87418bd37872f20e25e4 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

## Services à remplacer

| Service | Contrat | Celui du bundle |
|---|---|---|
| `Phil\NewsletterBundle\Application\Contract\CurrentSite` | `site(): Site`, le site de la requête | `ConfiguredSite`, le site configuré |
| `Phil\NewsletterBundle\Application\Contract\SegmentResolver` | `recipients(Campaign, DateTimeImmutable): iterable<string>`, les identifiants des abonnés, chacun une fois | `ListRecipients`, la liste entière |
| `Phil\NewsletterBundle\Application\Contract\SendThrottle` | `wait(SenderProfile, string $domain): int`, 0 et une place prise, ou les secondes à attendre | `RateLimiterSendThrottle` |
| `Phil\NewsletterBundle\Application\Contract\BounceMailbox` | `unread(int $limit)`, les messages bruts par identifiant ; `acknowledge(string $id)` | `ImapBounceMailbox`, avec `bounces.imap` |
| `Phil\NewsletterBundle\Application\Contract\AudienceWebhookSender` | `send(string $url, string $body, string $id)`, qui lève `WebhookNotDelivered` | `StandardWebhooksAudienceSender`, avec `webhooks` |
| `Phil\EmailAddress\EmailValidator` | Le validateur d'adresses pendant la requête ; celui de l'application quand elle en définit un | La syntaxe, les domaines de premier niveau et les domaines jetables, pas de DNS |
| `phil_newsletter.mail_domains` | Le `Phil\EmailAddress\Contract\MailDomainResolver` que le worker de confirmation interroge, avec `check_mail_domain` | `DnsGetRecordResolver` ; celui de l'application en décorant le service |
| `Phil\Markup\Markup` | Le compilateur de Markdown ; celui de l'application quand elle en définit un | Le compilateur et l'assainisseur par défaut de `phil/markup` |

Les dépôts sont aussi des services, par leurs interfaces sous `Phil\NewsletterBundle\Domain\Contract` :
`MailingListRepository`, `SubscriberRepository`, `CampaignRepository`, `DeliveryRepository`, `TemplateRepository`,
`EngagementRepository` et `SuppressionRepository`.

## Les événements

| Événement | Distribué |
|---|---|
| `Phil\NewsletterBundle\Application\Event\AudienceChanged` | Après la sauvegarde de chaque changement d'un abonné : son type, l'identifiant de l'abonné, le site, la liste et l'identifiant externe |
| `Phil\NewsletterBundle\Application\Event\LinkClicked` | Après le comptage d'un clic par destinataire |

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
