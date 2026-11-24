<!-- phil-dev source-sha256=1554695a876d58af488aa6668c95c7ae42bced59d26348c3f23cd23cb90ca19f -->
# Alertes de stock bas

## Seuils et situations

Chaque UGS (unité de gestion de stock) a un seuil à chaque emplacement où quelqu'un la surveille, donné par
`LowStockThresholds` : le personnel le fixe, ou l'application prend le point de commande. Une UGS que personne ne
surveille à un emplacement n'y est pas lue.

Les unités **disponibles** sont celles en main moins celles réservées. Par rapport au seuil, une UGS est :

| Unités disponibles | Situation |
|---|---|
| au-dessus du seuil | aucune |
| au seuil ou en dessous, au-dessus de 0 | `LowStockSituation::Low` |
| 0 ou moins | `LowStockSituation::Out` |

## Enregistrer les mouvements

L'application remet à `LowStockMonitor::record()` les mouvements qu'elle a écrits — une vente, une réservation, un
décompte, un transfert —, au plus 10 000 par appel. Pour chaque UGS et chaque emplacement, le moniteur lit le niveau
après ces mouvements et compare sa situation à la dernière signalée, que garde `LowStockStateStore`.

```php
use Phil\InventoryAlerts\Stock\StockMovement;

$digests = $monitor->record([
    new StockMovement($movement->id, 'MUG-BLUE', 'store', -3, $movedAt),
]);
```

- Une nouvelle situation — basse, épuisée, ou de nouveau basse — déclenche une alerte. Elle nomme les unités
  disponibles, le seuil et le **mouvement qui l'a déclenchée** : parmi plusieurs mouvements d'une UGS à un
  emplacement dans un même appel, le dernier.
- La même situation n'est **jamais signalée deux fois** : une UGS qui reste basse pendant dix ventes déclenche une
  seule alerte. S'épuiser après avoir été basse est une nouvelle situation.
- Une UGS qui remonte au-dessus de son seuil est oubliée, et elle est donc signalée de nouveau quand elle retombe.

## Sommaires par emplacement

Les alertes sont groupées par emplacement dans un `LowStockDigest`, dans l'ordre des mouvements, et chaque sommaire
est envoyé par `StaffNotifier` : le personnel d'un magasin n'entend parler que du magasin. Une situation est retenue
une fois son sommaire envoyé ; si le notificateur lève une exception, les situations de cet emplacement ne sont pas
retenues et le mouvement suivant les signale de nouveau.

```php
foreach ($digests as $digest) {
    // $digest->location, $digest->alerts (sku, situation, available, threshold, movement), $digest->movementIds()
}
```

Un notificateur qui confie le sommaire à une file d'attente met l'emplacement et `movementIds()` dans le message ; le
*handler* relit le stock.
