<!-- phil-dev source-sha256=0c335ee956551a0b806b2c8964edb49a45c04d2042584e3c521d08f769a3bce4 -->
# Référence

Chaque classe du bundle, avec son rôle.

| Type | Sorte | Rôle |
|---|---|---|
| `Phil\NewsletterBundle\Application\Bounces\BounceHandler` | `final readonly class` | Ce qu'un rebond fait à l'abonné dont il s'agit, qu'il soit revenu dans la boîte des rebonds — lue par `phil/bounce-parser` |
| `Phil\NewsletterBundle\Application\Bounces\BounceOutcome` | enum | Ce qu'un rebond a fait à un abonné |
| `Phil\NewsletterBundle\Application\Campaigns` | `final readonly class` | Envoyer une campagne maintenant ou plus tard, l'annuler, et ce qu'elle a donné |
| `Phil\NewsletterBundle\Application\Content\ContentRenderer` | `final readonly class` | Transforme ce qu'un éditeur a écrit en message |
| `Phil\NewsletterBundle\Application\Content\LinkRewriter` | `final readonly class` | Réécrit les liens du corps d'un message pour le suivi des clics |
| `Phil\NewsletterBundle\Application\Content\RangeRefusal` | `final readonly class` | Refuse, quand un gabarit en bac à sable se compile, l'opérateur d'intervalle `1..1000000` |
| `Phil\NewsletterBundle\Application\Content\RenderedMessage` | `final readonly class` | Un message prêt à envoyer |
| `Phil\NewsletterBundle\Application\Content\SandboxedTemplates` | `final class` | Produit les gabarits qu'écrivent les administrateurs et les membres, jamais avec le Twig de l'application |
| `Phil\NewsletterBundle\Application\Contract\AudienceWebhookSender` | interface | Envoie un webhook signé d'un événement d'audience à un récepteur |
| `Phil\NewsletterBundle\Application\Contract\BounceMailbox` | interface | La boîte où reviennent les rebonds |
| `Phil\NewsletterBundle\Application\Contract\CurrentSite` | interface | Le site auquel une requête s'adresse |
| `Phil\NewsletterBundle\Application\Contract\SegmentResolver` | interface | Choisit les destinataires d'une campagne |
| `Phil\NewsletterBundle\Application\Contract\SendThrottle` | interface | Le rythme d'envoi |
| `Phil\NewsletterBundle\Application\Event\AudienceChanged` | `final readonly class` | Quelque chose est arrivé à un abonné |
| `Phil\NewsletterBundle\Application\Event\LinkClicked` | `final readonly class` | Un abonné a cliqué sur un lien d'une campagne qui compte les clics par destinataire |
| `Phil\NewsletterBundle\Application\Exception\LinkRefused` | `final class` | Un lien de l'infolettre qu'elle n'a pas signé, qui est pour autre chose, ou qui a expiré |
| `Phil\NewsletterBundle\Application\Exception\NotFound` | `final class` | Une liste, un abonné, une campagne ou un gabarit qui n'existe pas |
| `Phil\NewsletterBundle\Application\Exception\SubscriptionRefused` | `final class` | Une adresse que l'infolettre refuse |
| `Phil\NewsletterBundle\Application\Exception\WebhookNotDelivered` | `final class` | Un webhook d'audience que le récepteur n'a pas accepté |
| `Phil\NewsletterBundle\Application\Handler\DeliverAudienceWebhookHandler` | `final readonly class` | Envoie un événement d'audience à un récepteur configuré, comme une livraison Standard Webhooks |
| `Phil\NewsletterBundle\Application\Handler\DispatchDueCampaignsHandler` | `final readonly class` | Démarre les campagnes planifiées dont l'heure est venue |
| `Phil\NewsletterBundle\Application\Handler\FetchBouncesHandler` | `final readonly class` | Lit la boîte des rebonds |
| `Phil\NewsletterBundle\Application\Handler\NewsletterSchedule` | `final readonly class` | La planification `newsletter` de Symfony Scheduler |
| `Phil\NewsletterBundle\Application\Handler\SendCampaignHandler` | `final readonly class` | Met en file les messages d'une campagne |
| `Phil\NewsletterBundle\Application\Handler\SendConfirmationHandler` | `final readonly class` | Envoie la confirmation d'une double confirmation, avec son lien signé, valide sept jours, après la vérification du domaine quand un résolveur est fourni |
| `Phil\NewsletterBundle\Application\Handler\SendDeliveryHandler` | `final readonly class` | Envoie un message mis en file, à son heure |
| `Phil\NewsletterBundle\Application\ListRecipients` | `final readonly class` | Les destinataires propres au bundle |
| `Phil\NewsletterBundle\Application\Message\DeliverAudienceWebhook` | `final readonly class` | Demande à un *worker* d'envoyer un événement d'audience à un récepteur configuré |
| `Phil\NewsletterBundle\Application\Message\DispatchDueCampaigns` | `final readonly class` | Demande à un *worker* de démarrer les campagnes planifiées dont l'heure est venue |
| `Phil\NewsletterBundle\Application\Message\FetchBounces` | `final readonly class` | Demande à un *worker* de lire la boîte des rebonds |
| `Phil\NewsletterBundle\Application\Message\SendCampaign` | `final readonly class` | Demande à un *worker* de mettre en file les messages d'une campagne |
| `Phil\NewsletterBundle\Application\Message\SendConfirmation` | `final readonly class` | Demande à un *worker* d'envoyer la confirmation d'une double confirmation |
| `Phil\NewsletterBundle\Application\Message\SendDelivery` | `final readonly class` | Demande à un *worker* d'envoyer un message mis en file |
| `Phil\NewsletterBundle\Application\NewsletterUrls` | `final readonly class` | Les adresses absolues des pages de l'infolettre, écrites dans les messages qu'un *worker* envoie sans requête |
| `Phil\NewsletterBundle\Application\Sending\MessageComposer` | `final readonly class` | Construit le courriel d'un envoi |
| `Phil\NewsletterBundle\Application\Sending\SenderProfile` | `final readonly class` | Qui envoie un message et comment il part |
| `Phil\NewsletterBundle\Application\Sending\SenderProfiles` | `final readonly class` | Les profils d'expéditeur de la configuration, par nom, et celui qu'utilise une liste ou une campagne sans profil propre |
| `Phil\NewsletterBundle\Application\SignedLink` | `final readonly class` | Ce que dit un lien signé, une fois sa signature vérifiée |
| `Phil\NewsletterBundle\Application\SignedLinks` | `final readonly class` | Les liens des messages et des pages de l'infolettre — confirmation, désinscription, préférences, clic —, signés pour que personne ne puisse en faire un pour un autre abonné |
| `Phil\NewsletterBundle\Application\SubscribeOutcome` | enum | Ce qu'a fait un abonnement |
| `Phil\NewsletterBundle\Application\Subscriptions` | `final readonly class` | S'abonner, confirmer, partir, se mettre en pause et oublier |
| `Phil\NewsletterBundle\Application\Tracking\ClickTracker` | `final readonly class` | Suit le lien suivi d'un message |
| `Phil\NewsletterBundle\Domain\Contract\CampaignRepository` | interface | Où sont gardées les campagnes, et les liens que suivent leurs messages |
| `Phil\NewsletterBundle\Domain\Contract\DeliveryRepository` | interface | Où sont gardés les envois |
| `Phil\NewsletterBundle\Domain\Contract\EngagementRepository` | interface | Où sont comptés les clics et les raisons de partir |
| `Phil\NewsletterBundle\Domain\Contract\MailingListRepository` | interface | Où sont gardées les listes |
| `Phil\NewsletterBundle\Domain\Contract\SubscriberRepository` | interface | Où sont gardés les abonnés et leurs appartenances |
| `Phil\NewsletterBundle\Domain\Contract\SuppressionRepository` | interface | Les adresses à ne plus jamais écrire — rebondies, plaintes —, gardées sous forme d'empreinte à clé, de sorte que la liste survit à un effacement sans garder l'adresse |
| `Phil\NewsletterBundle\Domain\Contract\TemplateRepository` | interface | Où sont gardés les gabarits qu'écrivent les administrateurs et les membres |
| `Phil\NewsletterBundle\Domain\Enum\AudienceEventType` | enum | Ce qui est arrivé à un abonné, pour les écouteurs, les automatisations et les webhooks sortants |
| `Phil\NewsletterBundle\Domain\Enum\CampaignStatus` | enum | Où en est une campagne |
| `Phil\NewsletterBundle\Domain\Enum\ClickTracking` | enum | Comment sont comptés les clics d'une campagne |
| `Phil\NewsletterBundle\Domain\Enum\DeliveryStatus` | enum | Ce qu'il est advenu d'un message à un abonné |
| `Phil\NewsletterBundle\Domain\Enum\LinkPurpose` | enum | À quoi sert un lien signé de l'infolettre |
| `Phil\NewsletterBundle\Domain\Enum\MembershipStatus` | enum | La place d'un abonné sur une liste : `pending`, `active`, `unsubscribed`, ou `refused` quand le domaine ne reçoit aucun courriel |
| `Phil\NewsletterBundle\Domain\Enum\SubscriberStatus` | enum | Ce qu'est un abonné pour l'infolettre du site dans son ensemble |
| `Phil\NewsletterBundle\Domain\Enum\UnsubscribeReason` | enum | Pourquoi quelqu'un est parti, comme il l'a choisi, gardé sans rien qui le nomme |
| `Phil\NewsletterBundle\Domain\Exception\InvalidNewsletterValue` | `final class` | Une valeur que l'infolettre refuse |
| `Phil\NewsletterBundle\Domain\Exception\NewsletterException` | interface | Toute exception du bundle |
| `Phil\NewsletterBundle\Domain\Model\Campaign` | `final class` | Un message envoyé à une liste, ou à un segment de celle-ci |
| `Phil\NewsletterBundle\Domain\Model\Delivery` | `final class` | Un message à un abonné |
| `Phil\NewsletterBundle\Domain\Model\MailingList` | `final class` | Une liste à laquelle les gens s'abonnent, sur un site |
| `Phil\NewsletterBundle\Domain\Model\Membership` | `final class` | La place d'un abonné sur une liste |
| `Phil\NewsletterBundle\Domain\Model\Subscriber` | `final class` | Une personne qui s'est abonnée sur un site |
| `Phil\NewsletterBundle\Domain\Model\Template` | `final class` | Un gabarit qu'un administrateur ou un membre a écrit |
| `Phil\NewsletterBundle\Domain\ValueObject\SendWindow` | `final readonly class` | Les heures d'envoi d'un profil d'expéditeur |
| `Phil\NewsletterBundle\Domain\ValueObject\Site` | `final readonly class` | Le site auquel appartient une liste, un abonné, une campagne ou un gabarit |
| `Phil\NewsletterBundle\Infrastructure\Automations\AudienceTrigger` | `final readonly class` | Les déclencheurs `newsletter.subscribed`, `newsletter.confirmed`, `newsletter.unsubscribed`, `newsletter.paused` et `newsletter.resumed` |
| `Phil\NewsletterBundle\Infrastructure\Automations\LinkClickedTrigger` | `final readonly class` | Le déclencheur `newsletter.link_clicked` |
| `Phil\NewsletterBundle\Infrastructure\Automations\Lists` | `final class` | L'abonné de l'exécution et la liste que nomme l'option d'une action, sur le site de l'abonné |
| `Phil\NewsletterBundle\Infrastructure\Automations\SendAction` | `final readonly class` | L'action `newsletter.send` |
| `Phil\NewsletterBundle\Infrastructure\Automations\SubscribeAction` | `final readonly class` | L'action `newsletter.subscribe` |
| `Phil\NewsletterBundle\Infrastructure\Automations\UnsubscribeAction` | `final readonly class` | L'action `newsletter.unsubscribe` |
| `Phil\NewsletterBundle\Infrastructure\Console\FetchBouncesCommand` | `final readonly class` | Lit la boîte des rebonds tout de suite, comme le fait la planification toutes les cinq minutes |
| `Phil\NewsletterBundle\Infrastructure\Dbal\DbalCampaignRepository` | `final readonly class` | Les campagnes dans la table `phil_newsletter_campaign`, les liens qu'elles suivent dans `phil_newsletter_link` |
| `Phil\NewsletterBundle\Infrastructure\Dbal\DbalDeliveryRepository` | `final readonly class` | Les envois dans la table `phil_newsletter_delivery`, chacun créé une fois par une insertion qui laisse en paix une ligne du même identifiant |
| `Phil\NewsletterBundle\Infrastructure\Dbal\DbalEngagementRepository` | `final readonly class` | Les clics dans `phil_newsletter_click` (un total par lien) et `phil_newsletter_recipient_click` (par destinataire, quand une campagne l'a choisi), les raisons de partir dans `phil_newsletter_unsubscribe_reason`, un total par site, liste, raison et jour UTC |
| `Phil\NewsletterBundle\Infrastructure\Dbal\DbalMailingListRepository` | `final readonly class` | Les listes dans la table `phil_newsletter_list` |
| `Phil\NewsletterBundle\Infrastructure\Dbal\DbalSubscriberRepository` | `final readonly class` | Les abonnés dans la table `phil_newsletter_subscriber`, leurs appartenances dans `phil_newsletter_membership` |
| `Phil\NewsletterBundle\Infrastructure\Dbal\DbalSuppressionRepository` | `final readonly class` | Les adresses supprimées dans la table `phil_newsletter_suppression`, chacune sous forme de HMAC-SHA256 du site et de l'adresse sous la clé de l'application |
| `Phil\NewsletterBundle\Infrastructure\Dbal\DbalTemplateRepository` | `final readonly class` | Les gabarits dans la table `phil_newsletter_template` ; un gabarit partagé a un site vide |
| `Phil\NewsletterBundle\Infrastructure\Dbal\NewsletterSchema` | `final class` | Les tables de l'infolettre, ajoutées à un schéma qui ne les a pas |
| `Phil\NewsletterBundle\Infrastructure\Dbal\NewsletterSchemaListener` | `final readonly class` | Ajoute les tables de l'infolettre au schéma que l'ORM génère pour la connexion du bundle |
| `Phil\NewsletterBundle\Infrastructure\Dbal\Sql` | `final class` | Ce que les dépôts DBAL partagent |
| `Phil\NewsletterBundle\Infrastructure\DependencyInjection\NewsletterServices` | `final class` | Les services du bundle, à partir de sa configuration traitée |
| `Phil\NewsletterBundle\Infrastructure\Http\ClickController` | `final readonly class` | Redirige un lien suivi vers l'adresse que garde sa campagne, en comptant le clic sauf si une machine l'a demandé ; un lien qui n'est pas celui de l'infolettre reçoit un `404` |
| `Phil\NewsletterBundle\Infrastructure\Http\PreviewController` | `final readonly class` | L'aperçu de l'éditeur Markdown |
| `Phil\NewsletterBundle\Infrastructure\Http\SubscriptionController` | `final readonly class` | Les pages publiques de l'infolettre |
| `Phil\NewsletterBundle\Infrastructure\Imap\Exception\ImapFailed` | `final class` | Le serveur IMAP n'a pas pu être joint, a refusé une commande, ou a répondu hors du protocole ou des limites |
| `Phil\NewsletterBundle\Infrastructure\Imap\ImapBounceMailbox` | `final readonly class` | La boîte des rebonds sur un serveur IMAP |
| `Phil\NewsletterBundle\Infrastructure\Imap\ImapClient` | `final class` | Les quelques commandes IMAP4rev1 qu'exige une boîte de rebonds, sur un flux que l'appelant ouvre |
| `Phil\NewsletterBundle\Infrastructure\Privacy\NewsletterPrivacy` | `final readonly class` | La part de l'infolettre dans une demande d'accès ou d'effacement de `phil/privacy-bundle`, la personne concernée étant l'identifiant externe que l'application a donné à ses abonnés, sur chaque site |
| `Phil\NewsletterBundle\Infrastructure\RateLimiter\RateLimiterSendThrottle` | `final readonly class` | Le rythme d'envoi sur le limiteur de débit de Symfony |
| `Phil\NewsletterBundle\Infrastructure\RemoteEvent\MailerEventConsumer` | `final readonly class` | Les événements qu'envoie le webhook d'un fournisseur de courriel, tels que les analysent le composant Webhook de Symfony et le pont Mailer du fournisseur |
| `Phil\NewsletterBundle\Infrastructure\Site\ConfiguredSite` | `final readonly class` | Le site de la configuration, `default` sauf s'il en nomme un autre |
| `Phil\NewsletterBundle\Infrastructure\Webhook\AudienceWebhookListener` | `final readonly class` | Envoie chaque événement d'audience à chaque récepteur configuré qui a demandé son type, par Messenger, pour qu'un récepteur lent ne retienne jamais la requête qui a changé l'abonné |
| `Phil\NewsletterBundle\Infrastructure\Webhook\StandardWebhooksAudienceSender` | `final readonly class` | Les webhooks d'audience envoyés par l'expéditeur de `phil/standard-webhooks-bundle` |
| `Phil\NewsletterBundle\PhilNewsletterBundle` | `final class` | L'infolettre d'un site |
