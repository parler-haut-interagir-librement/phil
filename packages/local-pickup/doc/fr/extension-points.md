<!-- phil-dev source-sha256=2b5749f5963794a3f27a50b5c3d370299c9d7e658816c93ee77c56e2f7db9959 -->
# Points d'extension

Le paquet en est à ses versions `0.x` : les ports, les objets-valeurs et les exceptions peuvent changer dans une version
mineure, ce que dit le CHANGELOG. Ils sont destinés à être stables à partir de la version `1.0`.

## Ports

| Port | Implémenté par | Rôle |
|---|---|---|
| `Contract\PickupRepository` | une table, avec ce que donne `Pickup::toArray()` | conserve les ramassages ; donne les réservations d'un point, les commandes en retard et les ramassages d'un client |
| `Contract\HandOverLog` | une table | journalise les remises ; exporte et efface les noms des personnes qui ramassent |
| `Contract\ReadyNoticeDispatcher` | un adaptateur vers le bus | envoie `Message\NotifyPickupReady` avec le numéro de commande |
| `Contract\ReadyNotifier` | un adaptateur vers les notifications de l'application | envoie l'avis de disponibilité avec son code |
| `Contract\ArrivalNotifier` | un adaptateur vers l'écran ou les notifications du personnel, facultatif | avise le personnel d'une arrivée, une seule fois |
| `Contract\ExpiredPickupCanceller` | un adaptateur vers le commerce | annule et rembourse une commande expirée ; idempotent |
| `Contract\ExpiredPickupRestocker` | un adaptateur vers la logistique | remet en stock les marchandises d'une commande expirée ; idempotent |

`Memory\InMemoryPickups` et `Memory\InMemoryHandOverLog` gardent leurs données dans des tableaux, pour les tests et un
processus unique.

## Le port qu'offre la bibliothèque

`Contract\PickupDesk` est ce qu'appelle un écran de comptoir ou une caisse de point de vente pour remettre une commande ;
`Counter` l'implémente. L'adaptateur d'une caisse dépend de l'interface et passe son propre identifiant comme poste.

## Le stockage

Un stockage conserve le tableau que donne `Pickup::toArray()` — des scalaires et des valeurs nulles seulement, les
instants en horodatages Unix — et reconstruit le ramassage avec `Pickup::fromArray()`, qui refuse une ligne mal formée.
Indexez le numéro de commande (unique), le point avec le début et la fin de la plage pour `bookings()`, l'état avec la
fin de la garde pour `overdue()`, et le client.

```php
final readonly class DbalPickups implements PickupRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function find(string $order): ?Pickup
    {
        $row = $this->connection->fetchOne('SELECT data FROM pickup WHERE order_number = ?', [$order]);

        return false === $row ? null : Pickup::fromArray(json_decode($row, true, 8, \JSON_THROW_ON_ERROR));
    }

    // save(), bookings(), overdue() and ofCustomer() read and write the same column.
}
```

## La remise en stock par la logistique

Une application qui utilise phil/commerce-logistics-bundle libère la réservation de la commande, ce qui rend ses unités
au stock d'où elles avaient été réservées :

```php
use Phil\CommerceLogisticsBundle\Application\Stock;
use Phil\LocalPickup\Contract\ExpiredPickupRestocker;
use Phil\LocalPickup\Pickup;

final readonly class ReleaseReservation implements ExpiredPickupRestocker
{
    public function __construct(private Stock $stock, private ClockInterface $clock)
    {
    }

    public function restock(Pickup $pickup): void
    {
        $this->stock->release($pickup->order, $this->clock->now());
    }
}
```

## Réglages

| Réglage | Où | Valeur par défaut |
|---|---|---|
| Le secret des codes | `PickupCodes` | aucune : au moins 32 octets |
| La limite de tentatives | `Counter` | 5, de 1 à 20 |
| Ce que fait l'expiration | `Expiry`, `ExpiryOutcome` | aucune : choisi par l'application |
| Le lot d'une exécution de l'expiration | `Expiry::run()` | 100, de 1 à 1 000 |
| Plages, préparation, garde, capacité, heures | `PickupPoint` et son `Bookable` | 30 minutes, 120 minutes, 168 heures |
