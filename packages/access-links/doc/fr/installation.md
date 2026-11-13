<!-- phil-dev source-sha256=f20e78bb9f169c05d5921b4badd40d86fec6c3437c7442a470962b1b5ea274d9 -->
# Installation

```sh
composer require phil/access-links
```

Le paquet exige PHP 8.5 ou une version plus récente avec `ext-mbstring` et `ext-sodium`, `phil/url` et `psr/clock`. Les
rappels sous forme de messages exigent `symfony/messenger` 8.2 ou une version plus récente ; le magasin en base de
données exige `doctrine/dbal` 4.5 ou une version plus récente :

```sh
composer require symfony/messenger doctrine/dbal
```

## La clé

Les jetons des liens sont stockés sous la forme de leur HMAC-SHA-256 calculé avec une clé d'au moins 32 octets.
Générez-la une seule fois et gardez-la dans le coffre de secrets, jamais dans le dépôt :

```sh
php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'
php bin/console secrets:set ACCESS_LINKS_KEY
```

Une clé perdue, c'est tous les liens envoyés perdus. Voir [Liens](links.md) pour sa rotation.

## Les services dans Symfony

La bibliothèque ne déclare aucun service. Dans `config/services.yaml` :

```yaml
services:
    Phil\AccessLinks\Dbal\DbalInvitationStore:
        arguments: ['@doctrine.dbal.default_connection']

    Phil\AccessLinks\TokenHasher:
        arguments: ['%env(base64:ACCESS_LINKS_KEY)%']

    app.access_links.page:
        class: Phil\Url\Url
        factory: ['Phil\Url\Url', 'fromString']
        arguments: ['https://events.example/rsvp']

    Phil\AccessLinks\LinkFormat:
        arguments: ['@app.access_links.page']

    Phil\AccessLinks\ReminderSchedule:
        arguments: [['P3D', 'P7D']]

    Phil\AccessLinks\AccessLinks:
        arguments:
            $store: '@Phil\AccessLinks\Dbal\DbalInvitationStore'

    Phil\AccessLinks\Messenger\ReminderDispatcher: ~
    Phil\AccessLinks\Messenger\SendInvitationReminderHandler: ~
```

L'horloge est le service `clock` de Symfony, câblé automatiquement par son interface `Psr\Clock\ClockInterface`. Le
*handler* porte `#[AsMessageHandler]` ; il a besoin du `ReminderSender` de l'application, déclaré comme alias de
`Phil\AccessLinks\Contract\ReminderSender`. Acheminez `Phil\AccessLinks\Messenger\SendInvitationReminder` vers un
transport asynchrone, et lancez le répartiteur depuis une tâche planifiée ; voir [Rappels](reminders.md).

Créez ensuite les tables, depuis une migration qui appelle `DbalInvitationStore::configureSchema()` ; voir
[Stockage](storage.md).
