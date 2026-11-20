<!-- phil-dev source-sha256=0b750571a63caa82f2d72086bef571bcb56a75c96f1af88c8f25e8b5c8331472 -->
<!-- phil-dev source-sha256=0 -->
# Achats et numéros de série

## Bons de commande

`Purchasing` commande des marchandises à des fournisseurs et les reçoit contre leurs bons de commande. Chaque réception
ajoute les unités au stock de l'emplacement du bon, dans le journal sous le numéro du bon.

```php
$purchasing->order('PO-2026-014', 'Dice Works', 'warehouse', ['DICE-D20' => 120, 'DICE-D6' => 60], $clock->now(), expectedOn: new DateTimeImmutable('2026-11-20'));
$purchasing->onOrder('DICE-D20');                                       // 120
$purchasing->receive('PO-2026-014', ['DICE-D20' => 80], $clock->now()); // 80 on hand, 40 still expected
$purchasing->close('PO-2026-014');                                      // the rest will not come
```

| Méthode de `Purchasing` | Effet |
|---|---|
| `order(number, supplier, location, lines, at, expectedOn)` | Ouvre un bon de commande de 1 à 500 *SKU*, chacune d'une unité ou plus, à un emplacement connu |
| `receive(number, lines, at)` | Reçoit des unités contre un bon ouvert, jamais plus d'un *SKU* qu'il n'en reste attendu |
| `close(number)` | N'attend plus rien d'un bon ouvert |
| `onOrder(sku)` | Les unités d'un *SKU* commandées et pas encore reçues, sur les bons ouverts |

- Un bon de commande est `open` jusqu'à ce que chaque unité soit arrivée — il est alors `received` — ou jusqu'à sa
  fermeture.
- Un *SKU* que le bon ne liste pas, une quantité au-delà de ce qui reste attendu, ou un bon qui n'est plus ouvert est
  refusé avec `PurchaseRefused` ; un numéro employé deux fois aussi.
- `PurchaseOrder::expectOn()` déplace le jour où le reste est attendu.

Les retours aux fournisseurs, le réapprovisionnement et les prévisions ne font pas partie du module.

## Numéros de série

`SerialNumbers` garde le numéro de série de chaque unité avec son stock : enregistré à l'emplacement qui la détient,
déplacé avec un transfert, noté contre la commande avec laquelle elle part, et remis sur une tablette quand elle revient.

```php
$stock->receive('CONSOLE', 'warehouse', 3, $clock->now(), 'PO-2026-020');
$serials->register('CONSOLE', 'warehouse', ['SN-0001', 'SN-0002', 'SN-0003'], $clock->now());
$serials->ship($order->number, 'CONSOLE', ['SN-0002'], $clock->now());
$serials->ofOrder($order->number);   // [SerialNumber SN-0002]
$serials->restock('CONSOLE', 'SN-0002', 'store');
```

- **Jamais plus d'unités sérialisées dans un emplacement qu'il n'en détient en stock** ; un numéro de série est
  enregistré une fois par *SKU*.
- Une unité ne se déplace ou ne part que tant qu'elle est en stock, et seulement de là où elle est ; une unité n'est
  remise en stock qu'une fois partie.
- Un appel nomme de 1 à 1 000 numéros de série, chacun une fois ; un numéro de série compte de 1 à 128 caractères ASCII
  visibles.
- Le module ne lie pas une unité au mouvement de stock d'une expédition : l'application note les unités avec
  l'expédition qui les a prises, par `ship()`.

## Stockage

| Table | Contient |
|---|---|
| `phil_commerce_logistics_purchase_order` | `number` (clé primaire), `supplier`, `location`, `ordered` et `received` (JSON, unités par *SKU*), `state` (indexé), `ordered_at`, `expected_on` |
| `phil_commerce_logistics_serial_number` | `sku` et `serial` (clé primaire), `location`, `state`, `order_number`, `received_at`, `shipped_at` ; indexés par *SKU*, emplacement et état, et par commande |
