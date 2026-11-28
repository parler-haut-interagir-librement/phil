<!-- phil-dev source-sha256=fc4abbfedb6d2ef7f9fea12b27e34d5861182750e4bd91a05cbd5919141a328d -->
# Installation

```sh
composer require phil/local-pickup
```

Le paquet exige PHP 8.5 ou une version plus récente. Il requiert phil/reservation, pour les plages, et phil/qr-code,
pour le code QR d'un code de ramassage ; ni l'un ni l'autre n'exige d'extension PHP au-delà de ce qu'ils déclarent.

## Ce que fournit l'application

| Quoi | Pourquoi |
|---|---|
| Un stockage pour les ramassages, qui implémente `Contract\PickupRepository` | Conserve ce que donne `Pickup::toArray()` : une table avec une colonne JSON, ou des colonnes propres. |
| Un journal, qui implémente `Contract\HandOverLog` | Conserve chaque remise, et qui a ramassé. |
| Un secret d'au moins 32 octets | Sert de clé à l'empreinte des codes : `PickupCodes` refuse un secret plus court. Gardez-le dans les secrets de l'application. |
| Un bus, qui implémente `Contract\ReadyNoticeDispatcher` | Envoie `Message\NotifyPickupReady` à un *worker*, qui appelle `Message\NotifyPickupReadyHandler`. |
| Un notificateur, qui implémente `Contract\ReadyNotifier` | Envoie l'avis de disponibilité avec son code ; `Contract\ArrivalNotifier` avise le personnel d'une arrivée. |
| Un port d'expiration | `Contract\ExpiredPickupCanceller` ou `Contract\ExpiredPickupRestocker`, selon le choix de `ExpiryOutcome`. |
| Une planification | Appelle `Expiry::run()` toutes les quelques minutes. |
| Un verrou par point de ramassage | Place l'un après l'autre deux clients qui prennent la dernière place d'une plage, autour de `Pickups::place()`. |

`Memory\InMemoryPickups` et `Memory\InMemoryHandOverLog` servent aux tests et à un processus unique.
