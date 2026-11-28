<!-- phil-dev source-sha256=b509114cb26577b112f0c59fd3de2f576affb987078f3537d4f1a8919ced335e -->
# Installation

```sh
composer require phil/newsletter-bundle
```

Le bundle exige PHP 8.5 ou une version plus récente, Symfony 8.2 ou une version plus récente avec FrameworkBundle,
TwigBundle, le mailer, Messenger, le planificateur, le limiteur de débit, la protection CSRF et SecurityBundle, Twig
3.30 ou une version plus récente et une connexion Doctrine DBAL 4.5.

Avec Symfony Flex, la recette enregistre le bundle, écrit `config/packages/phil_newsletter.yaml` avec l'adresse de base
du site et un profil d'expéditeur, importe les routes sous `/newsletter` dans `config/routes/phil_newsletter.yaml` et
ajoute `NEWSLETTER_SECRET` à `.env`, vide : donnez-lui une valeur aléatoire de 32 octets ou plus, hors du dépôt — un
secret Symfony, `.env.local` ou l'environnement du serveur :

```sh
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
php bin/console secrets:set NEWSLETTER_SECRET
```

Le bundle refuse une clé de moins de 16 octets — une clé vide comprise — et une clé qui commence par `change-me` ou
`changeme`, les substituts qu'emploient les exemples : le premier service qui signe un lien, étiquette une adresse VERP
ou hache une adresse supprimée échoue avec `LinkRefused`. Sans Flex, enregistrez le bundle :

```php
// config/bundles.php
Phil\NewsletterBundle\PhilNewsletterBundle::class => ['all' => true],
```

puis écrivez sa configuration (voir [Configuration](configuration.md)) et importez ses routes.

## Les routes

```yaml
# config/routes/phil_newsletter.yaml
phil_newsletter:
    resource: '@PhilNewsletterBundle/config/routes.php'
    prefix: /newsletter
```

| Route | Chemin | Ce que c'est |
|---|---|---|
| `phil_newsletter_subscribe` | `/subscribe/{list}` | Le formulaire d'abonnement d'une liste, par sa clé |
| `phil_newsletter_confirm` | `/confirm/{token}` | La confirmation d'une double confirmation |
| `phil_newsletter_unsubscribe` | `/unsubscribe/{token}` | La désinscription, et le `POST` en un clic du client de courriel |
| `phil_newsletter_preferences` | `/preferences/{token}` | Le centre de préférences |
| `phil_newsletter_click` | `/c/{token}` | Un lien suivi |
| `phil_newsletter_preview` | `/preview` | L'aperçu de l'éditeur Markdown, pour le rôle configuré |

## Les tables

`NewsletterSchema::configure()` ajoute les tables à un schéma. Avec l'ORM, `NewsletterSchemaListener`, enregistré sur
son événement `postGenerateSchema` quand DoctrineBundle est là, les ajoute au schéma qu'il génère, et
`doctrine:migrations:diff` écrit la migration, et celle de chaque version suivante. Voir [Stockage](storage.md).

## Les approbateurs

Une campagne part une fois qu'un utilisateur de `ROLE_NEWSLETTER_APPROVER` l'a approuvée. Donnez le rôle aux personnes
qui approuvent, dans la sécurité de l'application :

```yaml
# config/packages/security.yaml
security:
    role_hierarchy:
        ROLE_EDITOR_IN_CHIEF: [ROLE_NEWSLETTER_APPROVER]
```

`approval.role` nomme un autre rôle, `approval.four_eyes` exige un approbateur autre que l'auteur, et
`approval.required: false` envoie sans approbation. Voir [Campagnes](campaigns.md#approbation).

## Les *workers*

Acheminez les messages de l'infolettre vers un transport asynchrone, et consommez-le avec la planification
`newsletter` :

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            newsletter: '%env(MESSENGER_TRANSPORT_DSN)%'
        routing:
            'Phil\NewsletterBundle\Application\Message\SendCampaign': newsletter
            'Phil\NewsletterBundle\Application\Message\SendDelivery': newsletter
            'Phil\NewsletterBundle\Application\Message\SendConfirmation': newsletter
            'Phil\NewsletterBundle\Application\Message\FetchBounces': newsletter
            'Phil\NewsletterBundle\Application\Message\DeliverAudienceWebhook': newsletter
```

```sh
php bin/console messenger:consume newsletter scheduler_newsletter
```

Un transport dont les délais sont courts convient — un message qui attend la prochaine ouverture d'une fenêtre d'envoi
peut attendre des jours : l'envoi reste en file, et un message ultérieur le reprend.

Les gestionnaires de `SendDelivery` et de `SendConfirmation` envoient par les transports de Symfony Mailer
(`mailer.transports`), jamais par `MailerInterface` : ils s'exécutent déjà dans un *worker*, et avec `SendEmailMessage`
acheminé vers un transport, chaque courriel attendrait dans une seconde file avec l'adresse de l'abonné et son lien
signé de confirmation ou de désabonnement. Par les transports, ces messages ne portent que des identifiants, et un refus
du transport fait échouer le gestionnaire : Messenger le voit et retente le message, une livraison étant entre-temps
marquée en échec. Le `X-Transport` du profil d'expéditeur, le rythme de `SendThrottle`, `List-Unsubscribe` et
`List-Unsubscribe-Post` s'appliquent comme avant, tout comme la configuration du mailer — `envelope`, `headers`.

Acheminer `SendEmailMessage` reste le choix de l'application pour ses propres courriels. Celle qui le fait garde des
renseignements personnels dans son transport Messenger et dans sa file des échecs : il lui faut une règle de
conservation pour la file des échecs — `messenger:failed:show` pour regarder, `messenger:failed:remove` pour purger — et
un mot dans sa politique de confidentialité.

### Exécuter les *workers*

Une campagne met en file un `SendDelivery` par destinataire, et un abonné qui vient de s'inscrire attend
`SendConfirmation`. Donnez aux confirmations un transport à part, pour qu'une campagne de cinquante mille livraisons ne
retarde jamais le courriel qu'un abonné attend :

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            newsletter: '%env(MESSENGER_TRANSPORT_DSN)%'
            newsletter_confirmation: '%env(NEWSLETTER_CONFIRMATION_TRANSPORT_DSN)%'
        routing:
            'Phil\NewsletterBundle\Application\Message\SendConfirmation': newsletter_confirmation
            # the other messages stay on newsletter
```

```sh
php bin/console messenger:consume newsletter_confirmation --time-limit=3600
php bin/console messenger:consume newsletter scheduler_newsletter --time-limit=3600
```

`NEWSLETTER_CONFIRMATION_TRANSPORT_DSN` nomme une autre file du même serveur, comme
`doctrine://default?queue_name=newsletter_confirmation`, ou un serveur à part.

Pour envoyer une campagne plus vite, lancez plusieurs *workers* sur `newsletter` plutôt que
`messenger:consume --concurrency` : une livraison est un travail court, un courriel remis au transport du mailer, et
avec des messages aussi courts, le processus parent de `--concurrency`, qui prend chaque message du transport et le
confie à un enfant, devient l'étape la plus lente. Le rythme de `SendThrottle` tient d'un *worker* à l'autre, puisqu'il
compte dans le bassin de `rate_limiter_storage`, que tous les *workers* partagent.
Les *workers* ajoutés consomment `newsletter` seul ; un seul *worker* garde `scheduler_newsletter`, pour que chaque
tic de l'horaire soit traité une fois.

Un *worker* vérifie `--time-limit` entre les messages et n'arrête jamais un message en cours : fixez-le plus long que le
gestionnaire le plus lent, pour qu'un *worker* ne redémarre pas après presque chaque message.

## L'éditeur Markdown

Avec AssetMapper, le bundle ajoute son contrôleur Stimulus sous `@phil/newsletter-bundle` ; activez-le dans
`assets/controllers.json`, puis incluez l'éditeur dans un formulaire. Voir [Campagnes](campaigns.md#léditeur-markdown).
