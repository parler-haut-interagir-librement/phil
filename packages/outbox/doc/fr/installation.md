<!-- phil-dev source-sha256=707a330c6441c12932e1fe7bb317335c4fee8bded1f759501358b422a8cc900a -->
# Installation

```sh
composer require phil/outbox
```

| Prérequis | Version |
|---|---|
| PHP | 8.5 ou plus récent |
| `ext-mbstring` | pour couper les erreurs conservées à une limite de caractère |
| `ext-sodium` | pour écrire les identifiants aléatoires et les jetons de réservation |
| `psr/clock` | 1.0, l'horloge qui date les enregistrements |
| `psr/event-dispatcher` | 1.0, le répartiteur par lequel le relais informe les écouteurs |

La bibliothèque ne déclare aucun service. Une application Symfony installe
[phil/outbox-bundle](https://github.com/parler-haut-interagir-librement/outbox-bundle), qui conserve les
enregistrements dans une table de sa connexion Doctrine DBAL et câble l'outbox, le relais et les commandes. Une autre
application construit elle-même les objets :

```php
$codecs = new Codecs([new MyEventsCodec(), new RecordedEventCodec(['quiz.completed'])]);
$outbox = new StoreOutbox($store, $codecs, $clock);
$relay = new Relay($store, $codecs, $dispatcher, $clock, new Backoff(), lease: 60);
$housekeeping = new Housekeeping($store, $clock);
```

`$store` est un `OutboxStore` qui écrit dans la même base de données que la bibliothèque qui enregistre : voyez
[Entrepôts](stores.md). Pour exécuter les tests de contrat d'un entrepôt, exigez `phpunit/phpunit` en développement.
