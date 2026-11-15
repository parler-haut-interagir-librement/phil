<!-- phil-dev source-sha256=4ca9a394d4da04cffa7c09af6628888479dabf11d938c7debec023d0909169a7 -->
# Installation

```sh
composer require phil/newsletter-bundle
```

Le bundle exige PHP 8.5 ou une version plus récente, Symfony 8.2 ou une version plus récente avec FrameworkBundle,
TwigBundle, le mailer, Messenger, le planificateur, le limiteur de débit, la protection CSRF et SecurityBundle, Twig
3.30 ou une version plus récente et une connexion Doctrine DBAL 4.5.

Avec Symfony Flex, la recette enregistre le bundle, écrit `config/packages/phil_newsletter.yaml` avec l'adresse de base
du site et un profil d'expéditeur, importe les routes sous `/newsletter` dans `config/routes/phil_newsletter.yaml` et
ajoute `NEWSLETTER_SECRET` à `.env` : donnez-lui en production une valeur aléatoire de 32 octets ou plus. Sans Flex,
enregistrez le bundle :

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
`doctrine:migrations:diff` écrit la migration. Voir [Stockage](storage.md).

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

## L'éditeur Markdown

Avec AssetMapper, le bundle ajoute son contrôleur Stimulus sous `@phil/newsletter-bundle` ; activez-le dans
`assets/controllers.json`, puis incluez l'éditeur dans un formulaire. Voir [Campagnes](campaigns.md#léditeur-markdown).
