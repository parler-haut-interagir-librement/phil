<!-- phil-dev source-sha256=3508654bc7acf87f485a548dda4ef42a281c87fd85697f0fa076983b6f3c5138 -->
# Console et Messenger

## `phil:virtual-classroom:sync`

Demande aux serveurs qui est dans les salles ouvertes et ce qu'elles ont enregistré ; voyez
[Salles](rooms.md#synchroniser).

```sh
bin/console phil:virtual-classroom:sync              # the open rooms, the oldest first, 100 at most
bin/console phil:virtual-classroom:sync --limit=500  # 1 to 1000
bin/console phil:virtual-classroom:sync --room=0f3a…
```

Une salle dont le serveur échoue est signalée, les autres continuent, et la commande échoue. Lancez-la chaque minute
depuis Symfony Scheduler ou cron pour les serveurs qui n'envoient aucune notification.

## `phil:virtual-classroom:webhook-url`

Écrit l'adresse de compte d'un fournisseur, avec sa preuve, à régler dans la console du fournisseur :

```sh
bin/console phil:virtual-classroom:webhook-url hosted
```

L'hôte est celui du routeur : réglez `framework.router.default_uri`.

## `SyncRoom`

Avec Messenger installé, `SyncRoomHandler` traite `SyncRoom`, qui ne porte que l'identifiant d'une salle :

```php
$bus->dispatch(new SyncRoom($room->id->value));
```

Un message d'une salle qui n'existe pas est abandonné ; un serveur hors d'atteinte lève une exception, et le transport
réessaie.
