<!-- phil-dev source-sha256=0a04c1bc412982d4089be9f44c0ed36d8fead0de44d0889249c9fab33b6a07f8 -->
# Avec le module de logistique

Le bundle répartit par propriétaire le stock que tient `phil/commerce-logistics-bundle`, et ne le déplace que par les
services publics de ce module, derrière les ports `StockMovements` et `ShippedUnits`. Il exige la version du module de
logistique qui annonce les expéditions, prend les retours de clients et les ajustements, et donne aux lignes d'un bon de
commande un coût unitaire et un propriétaire.

## Comment le stock bouge

`LogisticsStockMovements` est l'adaptateur de `StockMovements` :

| Le grand livre | Le module de logistique |
|---|---|
| Une réception | `Stock::receive()` par SKU, sous la référence de la réception ; avec un bon de commande, `Purchasing::receive()` |
| Un transfert | `Stock::transfer()`, sous la référence du transfert |
| L'écart d'un inventaire | `Stock::adjust()` de l'écart, jamais sous zéro |
| Un retour d'une commande expédiée par le module de logistique | `Stock::returnFromCustomer()` sous le numéro de la commande |
| Un retour d'une vente qu'il n'a jamais expédiée, une vente au comptoir | `Stock::receive()` sous la référence de la vente |
| Des unités rendues à leur consignataire | `Stock::adjust()` des unités retirées, sous la référence du bordereau |
| Un rachat | Rien ne bouge : seul le propriétaire change |
| Une adoption | Rien ne bouge ; les unités en main sont lues dans le niveau |

`ConsignmentStock` écrit d'abord le grand livre, puis déplace le stock : un refus du grand livre ne déplace rien. Ni
l'un ni l'autre ne valide : appelez `flush` une seule fois, pour que le grand livre et le stock soient écrits en une
seule transaction. Après un refus de l'un ou de l'autre, n'appelez pas `flush` : l'unité de travail contient ce que le
grand livre avait commencé à écrire.

Le journal de la logistique distingue les genres : l'écart d'un inventaire et les unités rendues sont `adjusted`, le
retour d'un client est `returned`. Un inventaire que le grand livre trouve court de plus d'unités que le niveau n'en a
en main — des unités que le module de logistique a expédiées avant que leur vente soit écrite — retire ce qui est en
main et pas davantage.

## Bons de commande

Une réception contre un bon de commande est reçue à l'emplacement du bon, et chacune de ses lignes doit s'accorder avec
ce que le bon donne pour son SKU :

- un propriétaire sur la ligne du bon : la ligne de réception est consignée par ce consignataire ;
- un coût unitaire sur la ligne du bon : une ligne que la boutique achète a ce coût unitaire ; une ligne consignée n'en
  a aucun à comparer.

Ce que la ligne du bon omet n'est pas vérifié. Une ligne qui diffère est refusée avec `ConsignmentRefused` avant
qu'une unité bouge.

```php
$purchasing->order('PO-12', 'distributor', 'store', [
    'BOOK-1' => PurchaseLine::of(4, Money::of(1200, Currency::of('CAD'))),
    'ZINE-7' => PurchaseLine::of(3, owner: 'author-ann'),
], $clock->now());

$consignmentStock->receive(Receipt::of('DN-2', 'store', [
    ReceiptLine::purchased('BOOK-1', 4, Money::of(1200, Currency::of('CAD'))),
    ReceiptLine::consigned('ZINE-7', 3, 'author-ann', ConsignmentTerms::share(Money::of(300, Currency::of('CAD')))),
], 'PO-12'), $clock->now());
```

## Commandes expédiées

Le module de logistique réserve les unités d'une commande passée et les expédie ; une expédition est le moment où des
unités sortent. Chaque appel à `Stock::ship()` ou à `Stock::shipPart()` qui déplace des unités envoie `UnitsShipped`,
avec le numéro de la commande, avant que quoi que ce soit soit validé. `UnitsShippedListener` l'entend et appelle
`ShippedOrders::record()` avec la commande, à l'instant où les unités sont parties : les ventes se joignent à la
transaction de l'expédition, écrites par le même `flush`.

```php
$logistics->ship($order->number, $clock->now());
$entityManager->flush(); // the shipment and its sales
```

Avec `record_shipments: false`, l'application écrit elle-même les ventes, après l'expédition ou dans un worker :

```php
$shippedOrders->record($order, $clock->now());

// or, in a worker on a bus with Doctrine's transaction middleware:
$bus->dispatch(new RecordShipment($order->number));
```

`LogisticsShippedUnits` lit les réservations de la commande : chacune détient un SKU à un emplacement, et compte les
unités qui en sont parties, celles qui sont revenues depuis comprises. `ShippedOrders` vend, au grand livre et sous le
numéro de la commande, les unités expédiées de chaque emplacement et pas encore écrites, au prix unitaire des lignes de
produits de la commande dans leur ordre — deux lignes configurées d'un même produit partagent son SKU et peuvent différer
de prix. Un second appel n'écrit que ce qui a été expédié depuis : après une expédition partielle, deux expéditions
avant un même `flush`, un message après l'événement, ou une nouvelle tentative d'un `flush` échoué, rien n'est écrit
deux fois. Plus d'unités expédiées que la commande n'en contient d'un SKU sont refusées.

Le prix est le prix unitaire de la ligne, avant les ajustements de la commande : un rabais sur toute la commande ne
diminue pas la marge de chaque unité.
