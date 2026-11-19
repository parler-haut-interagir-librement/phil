# Console and Messenger

## `phil:virtual-classroom:sync`

Asks the servers who is in the open rooms and what they recorded; see [Rooms](rooms.md#syncing).

```sh
bin/console phil:virtual-classroom:sync              # the open rooms, the oldest first, 100 at most
bin/console phil:virtual-classroom:sync --limit=500  # 1 to 1000
bin/console phil:virtual-classroom:sync --room=0f3a…
```

A room whose server fails is reported, the others go on, and the command fails. Run it every minute from Symfony
Scheduler or cron for servers that send no notifications.

## `phil:virtual-classroom:webhook-url`

Writes the account address of a provider, with its proof, to set in the console of the provider:

```sh
bin/console phil:virtual-classroom:webhook-url hosted
```

The host is the router's: set `framework.router.default_uri`.

## `SyncRoom`

With Messenger installed, `SyncRoomHandler` handles `SyncRoom`, which carries the identifier of a room only:

```php
$bus->dispatch(new SyncRoom($room->id->value));
```

A message of a room that does not exist is dropped; a server out of reach throws, and the transport retries.
