<!-- phil-dev source-sha256=140705335f7458cc26aceee0ff37eda59b39211763d3c8934900a24cb0c12973 -->
# Avec le module de logistique

Le bundle répartit par propriétaire le stock que tient `phil/commerce-logistics-bundle`, et ne le déplace que par les
services publics de ce module, derrière les ports `StockMovements` et `ShippedUnits`.

## Comment le stock bouge

`LogisticsStockMovements` est l'adaptateur de `StockMovements` :

| Le grand livre | Le module de logistique |
|---|---|
| Une réception | `Stock::receive()` par SKU, sous la référence de la réception ; avec un bon de commande, `Purchasing::receive()` |
| Un transfert | `Stock::transfer()`, sous la référence du transfert |
| L'écart d'un inventaire | `Stock::count()` avec ce que le niveau avait en main plus l'écart |
| Un retour | `Stock::receive()` sous la référence de la vente |
| Des unités rendues à leur consignataire | `Stock::count()` avec ce que le niveau avait en main moins les unités, sous la référence du bordereau |
| Un rachat | Rien ne bouge : seul le propriétaire change |
| Une adoption | Rien ne bouge ; les unités en main sont lues dans le niveau |

`ConsignmentStock` écrit d'abord le grand livre, puis déplace le stock : un refus du grand livre ne déplace rien. Ni
l'un ni l'autre ne valide : appelez `flush` une seule fois, pour que le grand livre et le stock soient écrits en une
seule transaction.

## Commandes expédiées

Le module de logistique réserve les unités d'une commande passée et les expédie ; une expédition est le moment où des
unités sortent. Après `Stock::ship()` ou `Stock::shipPart()`, appelez `ShippedOrders::record()` avec la commande, ou
envoyez `RecordShipment` avec son numéro à un worker :

```php
$logistics->ship($order->number, $clock->now());
$shippedOrders->record($order, $clock->now());
$entityManager->flush();

// or, in a worker on a bus with Doctrine's transaction middleware:
$bus->dispatch(new RecordShipment($order->number));
```

`LogisticsShippedUnits` lit les réservations de la commande : chacune détient un SKU à un emplacement, et ce qu'elle a
expédié est sa quantité moins ce qu'elle détient encore ou a rendu. `ShippedOrders` vend, au grand livre et sous le
numéro de la commande, les unités expédiées de chaque emplacement et pas encore écrites, au prix unitaire des lignes de
produits de la commande dans leur ordre — deux lignes configurées d'un même produit partagent son SKU et peuvent différer
de prix. Un second appel n'écrit que ce qui a été expédié depuis : après une expédition partielle, après une nouvelle
tentative d'un `flush` échoué, rien n'est écrit deux fois. Plus d'unités expédiées que la commande n'en contient d'un
SKU sont refusées.

Le prix est le prix unitaire de la ligne, avant les ajustements de la commande : un rabais sur toute la commande ne
diminue pas la marge de chaque unité.

## Ce que le module de logistique n'expose pas

Les adaptateurs utilisent ce que le module de logistique publie, et rien d'autre. Ce qu'il n'offre pas encore :

| Manquant | Effet ici |
|---|---|
| Un événement quand des unités sont expédiées — commande, SKU, emplacement, unités | L'application appelle `ShippedOrders` après chaque expédition, ou envoie `RecordShipment` ; rien n'écrit une vente de lui-même. |
| Un genre de mouvement pour les retours de clients, et un service qui remet les unités vendues sur les tablettes | Un retour est reçu de nouveau sous la référence de la vente : le journal de la logistique l'écrit comme une réception. |
| Un inventaire qui change un niveau d'un écart | L'adaptateur lit le niveau, puis fixe ce qu'il avait plus l'écart : deux inventaires d'un même niveau en même temps reposent sur la version du niveau dans le module de logistique. |
| Un mouvement qui retire des unités de la tablette selon une quantité, pour des unités rendues à leur consignataire | L'adaptateur lit le niveau, refuse plus d'unités qu'il n'en a en main, puis le fixe à ce qu'il avait moins les unités par `Stock::count()` : le journal de la logistique écrit un inventaire sous la référence du bordereau. |
| Le coût unitaire et le propriétaire sur les lignes d'un bon de commande | La réception les porte dans ce bundle ; le bon de commande ne compte que des unités. |
