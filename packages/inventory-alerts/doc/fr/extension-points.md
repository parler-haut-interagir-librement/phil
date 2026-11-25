<!-- phil-dev source-sha256=2f0757cab6f3f8b6a8570b1ab8f690c0d91476b5ce77f4d4174378fe2a6ce0c3 -->
# Points d'extension

La bibliothèque en est à ses versions `0.x` : les ports, les objets-valeurs et les exceptions peuvent changer dans une
version mineure, ce que dit le journal des changements. Ils sont destinés à être stables à partir de `1.0`.

## Ports

| Port | Implémenté par | Rôle |
|---|---|---|
| `Contract\StockLevelSource` | un adaptateur vers les registres de stock | donne le niveau d'une UGS à un emplacement |
| `Contract\LowStockThresholds` | l'application | donne le seuil de stock bas d'une UGS à un emplacement |
| `Contract\LowStockStateStore` | une table ou un cache de l'application | garde la situation signalée en dernier au personnel |
| `Contract\StaffNotifier` | le courriel ou les notifications de l'application | envoie un sommaire au personnel d'un emplacement |
| `Contract\SubscriptionStore` | une table de l'application | garde les abonnements, en ordre |
| `Contract\SubscriberNotifier` | le courriel de l'application | envoie une vague d'alertes, avec les liens de désabonnement |

## La source de stock

`StockLevelSource::level()` renvoie un `StockLevel` — en main, réservé, en commande — ou null quand l'emplacement n'a
jamais contenu l'UGS (unité de gestion de stock). Un adaptateur vers un bundle de stock qui garde ses niveaux par UGS
et par emplacement, tel que `phil/commerce-logistics-bundle`, convertit un niveau en l'autre ; la bibliothèque ne
dépend jamais de ce bundle.

```php
use Phil\InventoryAlerts\Contract\StockLevelSource;
use Phil\InventoryAlerts\Stock\StockLevel;

final readonly class LogisticsStockSource implements StockLevelSource
{
    public function __construct(
        private StockRepository $stock,          // the stock repository of the logistics bundle
        private PurchaseOrders $purchaseOrders,  // what is ordered and not received yet
    ) {
    }

    public function level(string $sku, string $location): ?StockLevel
    {
        $level = $this->stock->level($sku, $location);

        return null === $level ? null : new StockLevel(
            $sku,
            $location,
            max(0, $level->onHand()),
            max(0, $level->reserved()),
            $this->purchaseOrders->openQuantity($sku, $location),
        );
    }
}
```

Les mouvements remis à `LowStockMonitor::record()` viennent des mêmes registres : chaque mouvement écrit devient un
`StockMovement` avec son identifiant, son UGS, son emplacement, sa variation et sa date, après la transaction qui l'a
écrit.

## L'état du stock bas

`LowStockStateStore` garde une ligne par UGS et par emplacement signalés : `situation()` la lit, `remember()` l'écrit
une fois le sommaire envoyé, `forget()` la supprime quand le stock remonte au-dessus de son seuil. Une table dont la
clé primaire est l'UGS et l'emplacement, ou un cache sans expiration, conviennent tous deux.

## Les notificateurs

`StaffNotifier::notify()` envoie un `LowStockDigest` ; `SubscriberNotifier::notify()` envoie une `Wave`. L'un et
l'autre peuvent envoyer sur-le-champ ou confier le travail à une file d'attente. Un message mis dans une file ne porte
que des identifiants : l'emplacement et les identifiants de mouvement d'un sommaire, les identifiants d'abonnement
d'une vague, relus par le *handler*. Une adresse ne voyage jamais dans un message.

Un notificateur d'abonnés construit chaque message à partir de l'abonnement : l'UGS, le type, le nouveau prix d'une
baisse de prix, et le lien de désabonnement de `Subscriptions::unsubscribeToken()`, dans le corps et dans les
en-têtes `List-Unsubscribe` et `List-Unsubscribe-Post`. Il n'ajoute rien d'autre à l'historique de l'adresse.

## Le magasin d'abonnements

`SubscriptionStore` garde ce que contient un `Subscription` et rien de plus : identifiant, type, UGS, adresse
(`EmailAddress::toString()`), date d'abonnement, montant et devise du seuil, date de notification.

- `save()` insère ou remplace selon l'identifiant ; `find()` et `findActive()` en lisent un.
- `active()` renvoie les abonnements actifs d'un type et d'une UGS **ordonnés par date d'abonnement puis par
  identifiant**, au plus `$limit` ; pour une baisse de prix, seulement ceux dont le seuil est au prix ou au-dessus, dans
  la même devise. Un index sur le type, l'UGS, la date de notification et la date d'abonnement le sert.
- `markNotified()` fixe la date de notification des identifiants qu'on lui remet.
- `delete()` et `deleteNotifiedBefore()` suppriment des lignes, adresse comprise.

## Options

- `WavePolicy` : abonnés par unité (2), minimum d'une vague (10), maximum d'une vague (500, au plus 10 000).
- `UnsubscribeTokens` : la clé de signature, de 32 octets ou plus.
- `ReplenishmentRule` : min/max ou quantité fixe, taille du lot.
