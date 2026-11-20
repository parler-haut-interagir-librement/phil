<!-- phil-dev source-sha256=5543645f6b0326ce0b42a19ee1c8c26a8215fa7b072949d99297901993e72fd2 -->
# La caisse

## Caisses

Une caisse est un tiroir-caisse : son code, son nom, la devise que contient son tiroir, l'incrément auquel l'argent
comptant est arrondi, son rôle — la caisse principale de son lieu, ou la caisse mobile d'un vendeur
([devices.md](devices.md)) — et, si l'application s'en sert, l'emplacement de stock d'où elle vend et le canal auquel
appartiennent ses ventes.

```php
$registers->create('front', 'Front counter', Currency::of('CAD'), cashIncrement: 5, location: 'store-1', channel: 'shop', role: RegisterRole::Main);
```

## Sessions

Une session est un quart de travail. Elle s'ouvre sur le fonds de caisse compté dans le tiroir, et l'argent comptant
attendu dans le tiroir suit chaque mouvement jusqu'à la fermeture.

```php
$session = $cashSessions->open('front', 'cashier-7', Money::of(20_000, $cad), $clock->now());
$cashSessions->paidIn($session->id, Money::of(5_000, $cad), 'cashier-7', $clock->now(), 'Change from the safe');
$cashSessions->paidOut($session->id, Money::of(1_250, $cad), 'cashier-7', $clock->now(), 'Milk');
$cashSessions->drop($session->id, Money::of(30_000, $cad), 'cashier-7', $clock->now());
$report = $cashSessions->close($session->id, Money::of(41_870, $cad), 'manager-2', $clock->now());
```

Une seule session est ouverte par caisse à la fois. Les personnes sont nommées par ce que l'application utilise pour
les identifier.

### Une journée à prix taxes comprises

Dans une foire, le prix sur l'étiquette est le prix payé. Une session ouverte avec `taxIncluded: true` vend chaque
panier de cette façon : un livre à 10,00 est enregistré à 10,00, la TPS de 0,44 et la TVQ de 0,87 en étant retirées, et
le reçu l'indique après le total.

```php
$session = $cashSessions->open('fair', 'cashier-7', Money::of(20_000, $cad), $clock->now(), taxIncluded: true);
$checkout->start($order, $session);  // before the first line: the cart is priced as the session sells
```

`start()` règle le panier sur la façon de vendre de la session et le recalcule. Enregistrer un panier tarifé autrement
que sa session lève `PosRefused`, puisque le client aurait vu un total et en aurait payé un autre. Une vente importée
d'hors ligne prend la façon de vendre de sa session.

## Le panier

Le panier est la commande du noyau, construite avec son `OrderModifier`. La caisse ajoute ce que seul un tiroir-caisse
fait :

```php
$checkout->start($order, $session);                                            // price it as the session sells
$checkout->hold($order, 'front', 'Mme Tremblay', 'cashier-7', $clock->now()); // park it
$order = $checkout->resume('A-0042', 'front');                                 // pick it up again
$checkout->tip($order, Money::of(300, $cad));                                  // or tipShare($order, 150_000) for 15 %
$checkout->ring($order, $session, 'cashier-7', $clock->now());                 // place it as a sale of the session
```

Un pourboire est un ajustement du noyau de type `Tip` : sur aucune ligne, donc sans taxe ; une part est calculée sur
les articles avant taxes, les taxes que comprennent les prix en étant retirées. L'enregistrement de la vente recalcule
la commande une dernière fois, le pourboire gardé.

## Paiements

Chaque paiement est un paiement du noyau, et plusieurs forment un paiement partagé. Chacun est sa propre transaction :
le noyau lit ce qui reste à payer à partir des paiements déjà enregistrés.

```php
$tender = $registerPayments->cash($order, $session, tendered: Money::of(5_000, $cad), by: 'cashier-7', at: $clock->now());
$tender->due;     // 22.00 for 21.98 owed, rounded
$tender->change;  // 28.00

$registerPayments->recorded($order, $session, 'debit', Money::of(1_000, $cad), approval: 'A1B2C3', at: $clock->now());
$registerPayments->count($giftCardPayment, $session, $clock->now());   // a payment another module took
$terminalPayments->charge($order, $session, 't1', $clock->now());       // a connected terminal: terminals.md
```

**L'arrondi appartient au tiroir** : le paiement enregistre le montant exact dû, 21,98, et le tiroir l'argent comptant
reçu, 22,00 ; la différence est gardée sur le mouvement et totalisée dans le rapport. La commande et ses taxes ne sont
jamais modifiées.

Les remboursements : `refundCash()` rend de l'argent comptant puisé dans le tiroir de la session, arrondi de la même
façon ; `refundRecorded()` enregistre un remboursement fait sur le terminal. Les deux prennent une clé d'idempotence, la
même à chaque essai d'un remboursement : redemandé sous sa clé — un deuxième toucher, une nouvelle tentative —, un
remboursement est enregistré une fois, et un remboursement en argent comptant ne rend rien de plus et ne déplace aucun
argent.

## Le rapport de fin de journée

`SessionReport`, tiré de `close()`, ou de `report()` tant que la session est ouverte :

| Partie | Contenu |
|---|---|
| Le tiroir | Le fonds de caisse, les ventes en argent comptant, les remboursements en argent comptant, les entrées de fonds, les sorties de fonds, les dépôts, l'arrondi |
| Le comptage | L'attendu, le compté, l'excédent ou le manque |
| `byMethod` | Les paiements reçus, par méthode |
| `tips`, `sales` | Les pourboires des ventes enregistrées, et leur nombre |

Le rapport ne lit aucune commande : chaque vente garde le pourboire qu'a pris sa commande au moment de son
enregistrement, dans les colonnes `tip` et `currency` de `phil_commerce_pos_sale` ; une journée chargée coûte donc une
seule requête sur les ventes, pas une par vente.

Sur plusieurs sessions, le rapport des écarts de caisse liste l'excédent ou le manque que chaque comptage a trouvé, et
signale les gros écarts ([counter.md](counter.md#le-rapport-des-écarts-de-caisse)).
