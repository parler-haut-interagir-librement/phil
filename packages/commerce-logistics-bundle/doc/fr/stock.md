<!-- phil-dev source-sha256=584d9232887f94dfa9966692606fdf6e558920074e5467dadca941a6787d4349 -->
<!-- phil-dev source-sha256=0 -->
# Stock

Le stock est tenu par *SKU* et par emplacement — un entrepôt, l'arrière-boutique d'un magasin —, et chaque mouvement
est écrit dans un journal qui ne change jamais. Les marchandises commandées à des fournisseurs sont reçues par les
[Achats](purchasing.md), et les articles de convention d'une commande passée en ligne peuvent aller à l'impression :
voyez les [Conventions](conventions.md).

```php
use Phil\CommerceLogisticsBundle\Application\Stock;
use Phil\CommerceLogisticsBundle\Domain\Model\Location;

$locations->addLocation(Location::of('warehouse', 'Entrepôt', priority: 10));
$locations->addLocation(Location::of('store-mtl', 'Magasin Montréal'));

$stock->receive('DICE-D20', 'warehouse', 120, $clock->now(), 'PO-2026-014');
$stock->transfer('DICE-D20', 'warehouse', 'store-mtl', 20, $clock->now(), 'TR-88');
$stock->count('DICE-D20', 'store-mtl', 18, $clock->now(), 'COUNT-11'); // the journal keeps −2
$stock->adjust('DICE-D20', 'warehouse', -1, $clock->now(), 'DAMAGED'); // one die written off
$stock->available('DICE-D20');                                        // 117
```

| Méthode de `Stock` | Effet |
|---|---|
| `receive(sku, location, quantity, at, reference, unitCost, owner)` | Ajoute des unités en main ; avec le calcul des coûts, à leur coût unitaire, ou comme unités d'un propriétaire — voyez [Coûts](costing.md) |
| `count(sku, location, counted, at, reference, reason)` | Fixe ce qu'un comptage a trouvé ; le journal garde la différence, et son motif s'il est donné |
| `adjust(sku, location, difference, at, reference)` | Change ce qui est en main d'une différence, jamais sous zéro ; le journal la garde comme `adjusted` |
| `adjustFor(sku, location, difference, reason, at, reference)` | Ajuste comme le fait `adjust()`, pour un `VarianceReason` que le journal garde à côté de la différence |
| `transfer(sku, from, to, quantity, at, reference)` | Déplace des unités disponibles d'un emplacement à un autre |
| `available(sku)` | Le stock en main moins le stock réservé, sur les emplacements ouverts |
| `reserve(order, at)` | Met de côté les lignes suivies d'une commande passée, une seule fois |
| `release(orderNumber, at)` | Rend ce qui est encore réservé pour la commande : elle a été annulée |
| `ship(orderNumber, at)` | Retire de la tablette ce qui est encore réservé pour la commande : elle est partie |
| `shipPart(orderNumber, sku, quantity, at)` | Retire de la tablette une partie de ce qui est réservé pour un *SKU* : une expédition partielle, que fait `Shipments` |
| `returnFromCustomer(orderNumber, sku, location, quantity, at)` | Remet sur une tablette des unités qu'un client a renvoyées, sous le numéro de la commande ; le journal les garde comme `returned` |
| `allowBackorders(sku)`, `forbidBackorders(sku)`, `allowsBackorders(sku)` | Vend le *SKU* sans stock, ou ne le vend plus ; s'il le fait |
| `backordered(sku)` | Les unités réservées au-delà de ce qui est en main, sur tous les emplacements |

- **Un *SKU* est suivi dès qu'il a un niveau de stock quelque part.** Un produit jamais reçu — un téléchargement, un
  service — n'est jamais réservé, et seules les lignes de type `product` le sont.
- **Les lignes configurées d'un même produit partagent son *SKU*** : deux lignes qui diffèrent par leurs options —
  deux noms imprimés sur un même chandail — sont réservées ensemble, dans le même stock.
- **Les réservations puisent dans les emplacements ouverts par priorité**, la plus haute d'abord, puis par code. Une
  pénurie sur n'importe quelle ligne ne réserve rien, et lève `StockRefused`.
- **Le disponible est le stock en main moins le stock réservé**, jamais sous zéro ; un comptage peut trouver moins en
  main que ce qui est réservé, ce que l'application règle ensuite.
- **Seule une commande passée est réservée** : tout autre état lève `StockRefused`.
- **Réserver, libérer ou expédier deux fois ne change rien.** Une commande qui a déjà des réservations n'est pas
  réservée de nouveau — un écouteur exécuté deux fois ou une nouvelle tentative après un *flush* raté ne réserve rien
  de plus —, et seul ce qui est encore réservé est libéré ou expédié.
- **La réservation consulte le stock une seule fois par commande** : une lecture des emplacements ouverts et une des
  niveaux de tous les *SKU* de produits de la commande (`StockRepository::levelsOfSkus()`) ; les lignes d'un autre
  type ne sont jamais consultées.
- Un emplacement fermé (`Location::close()`) ne sert plus aux réservations ; son stock reste compté.
- La référence — un numéro de commande, un bon de livraison, une feuille de comptage — va au journal, que
  `StockRepository::journal()` relit du plus ancien au plus récent.
- **Chaque mouvement a son genre** dans le journal : `received`, `counted`, `adjusted`, `transferred_out`,
  `transferred_in`, `reserved`, `backordered`, `released`, `shipped` et `returned`.

## Ajuster par une différence

`adjust()` change ce qu'un niveau détient en main d'une différence — les unités qu'un comptage a trouvées en moins ou en
trop, une unité abîmée radiée — là où `count()` fixe un chiffre absolu.

```php
$stock->adjust('DICE-D20', 'warehouse', -2, $clock->now(), 'COUNT-12');   // two missing
$stock->adjust('DICE-D20', 'warehouse', 1, $clock->now(), 'COUNT-12');    // one found behind the shelf
```

- **La différence s'applique au niveau tel qu'il se lit maintenant.** `count()` écrase ce qui était en main par le
  chiffre qu'on lui donne, et perd un mouvement fait depuis que le comptage a été pris ; `adjust()` non.
- **Jamais sous zéro** : une différence qui laisserait moins que rien en main lève `StockRefused::belowZero()`, et une
  différence de zéro lève `StockRefused::quantity()`. Comme un comptage, elle peut laisser moins en main que ce qui est
  réservé, ce que l'application règle ensuite.
- **Deux ajustements d'un même niveau en même temps comptent tous les deux.** Chaque requête lit le niveau, ajoute sa
  différence et vide ; la colonne de version du niveau refuse le second vidage avec l'`OptimisticLockException` de
  Doctrine. L'appelant reprend cette unité de travail dans un nouveau gestionnaire d'entités : la différence
  s'applique de nouveau, au niveau tel que le premier l'a laissé.
- La référence va au journal : une feuille de comptage, un rapport de dommages. `adjustFor()` ajoute un motif typé — endommagé,
  vol, perdu, périmé, erreur de comptage, erreur du fournisseur, usage interne, donné —, gardé dans le `reason` du
  mouvement ; avec le calcul des coûts, le journal des coûts garde à côté la valeur des unités au coût. Voyez
  [Coûts](costing.md#les-écarts).

## Les retours de clients

`returnFromCustomer()` remet sur une tablette des unités qu'un client a renvoyées, sous le numéro de la commande.

```php
$stock->ship($order->number, $clock->now());                                    // 3 dice left
$stock->returnFromCustomer($order->number, 'DICE-D20', 'returns', 2, $clock->now()); // 2 came back
```

- **Jamais plus que ce qui est parti et n'est pas encore revenu** : les réservations de la commande pour le *SKU*
  disent combien de ses unités ont été expédiées, et combien sont déjà revenues ; un retour au-delà lève
  `StockRefused::overReturned()` et ne déplace rien. Un *SKU* que la commande n'a jamais expédié par ses
  réservations — un *SKU* non suivi — ne reprend rien.
- **N'importe quel emplacement** : les unités retournent là où l'appelant le dit — l'entrepôt, une zone des retours —,
  qui n'est pas forcément celui d'où elles sont parties.
- **Le journal distingue un retour d'une réception** : le mouvement est `returned`, sous le numéro de la commande.
- `Reservation::shipped()`, `returned()` et `returnable()` donnent, pour chaque réservation, ce qui est parti, ce qui
  est revenu et ce qui peut encore revenir. Qu'une unité retournée puisse se revendre, c'est à l'application d'en
  décider ; elle la déplace avec `transfer()` ou `adjust()`.
- **Avec le calcul des coûts, les unités reviennent au coût auquel elles sont parties**, jamais au coût du jour — voyez
  [Coûts](costing.md#les-retours-de-clients).
- Avec `phil/commerce-returns-bundle`, sa remise en stock de la logistique, ou un `Restocking` propre à l'application,
  appelle `returnFromCustomer()` quand un retour est reçu.

## Vendre sans stock

Un *SKU* dont le `StockPolicy` permet les commandes en attente se vend sans stock — un indicateur, sans seuil ni plafond.

```php
$stock->allowBackorders('DICE-D20');
$stock->reserve($order, $clock->now());   // 5 ordered, 2 on hand: 2 reserved, 3 backordered
$stock->backordered('DICE-D20');          // 3
$stock->receive('DICE-D20', 'warehouse', 3, $clock->now(), 'PO-2026-015');
$stock->ship($order->number, $clock->now());
```

- Ce que les emplacements ouverts détiennent est réservé d'abord, par priorité ; **le manque est réservé au premier
  emplacement ouvert du *SKU***, au-delà de ce qu'il a de disponible, et le journal le note `backordered`.
- Les unités en attente ne partent qu'une fois les marchandises reçues à cet emplacement : les expédier avant est refusé,
  avec `StockRefused::notOnHand()`.
- Un *SKU* n'est mis en attente que là où il est suivi — dès qu'il a un niveau de stock dans un emplacement ouvert ; un
  *SKU* sans politique, ou dont la politique interdit les commandes en attente, est refusé en cas de manque, comme
  avant.
- Sans `StockPolicyRepository`, `allowBackorders()` lève une exception et aucun *SKU* n'est mis en attente.

## Les ventes payées plus tard

`DeferredStock` est le `StockCommitments` du noyau, portant l'étiquette `phil_commerce.stock_commitments` : quand la
politique d'une vente différée — une précommande, des versements, une mise de côté — engage son stock, les
`DeferredSales` du noyau l'appellent, et il réserve la commande par `Stock::reserve()` ; quand la vente est annulée
avant d'être payée en entier, il libère les réservations. Engager ou libérer deux fois ne change rien. Le moment vient
de l'horloge PSR-20.

## Stockage

Avec DoctrineBundle :

| Table | Contient |
|---|---|
| `phil_commerce_logistics_location` | `code` (clé primaire), `name`, `priority`, `active` |
| `phil_commerce_logistics_stock_level` | `sku` et `location` (clé primaire), `on_hand`, `reserved`, `version` |
| `phil_commerce_logistics_stock_movement` | `id` (UUID v7), `sku`, `location`, `kind`, `on_hand_change`, `reserved_change`, `reference`, `moved_at`, `reason` (nul sans motif) ; indexée par *SKU*, emplacement et moment, et par référence |
| `phil_commerce_logistics_reservation` | `id` (UUID v7), `order_number` (indexé), `sku`, `location`, `quantity`, `shipped`, `returned` (0 par défaut), `state` |
| `phil_commerce_logistics_stock_policy` | `sku` (clé primaire), `backorders` |

La colonne de version d'un niveau de stock refuse deux mouvements faits sur la même copie.

Une base créée pour 0.1.0-alpha gagne une colonne par table qui a changé et les deux tables du calcul des coûts, et
rien d'autre ne change : `returned` dans `phil_commerce_logistics_reservation`, un entier qui vaut 0 pour les lignes
existantes, `line_terms` dans `phil_commerce_logistics_purchase_order` — voyez [Achats](purchasing.md) —, `reason` dans
`phil_commerce_logistics_stock_movement`, nul pour les lignes existantes, et `phil_commerce_logistics_cost_layer` et
`phil_commerce_logistics_cost_entry` — voyez [Coûts](costing.md#stockage). Générez la migration avec
`doctrine:migrations:diff` de Doctrine, ou `doctrine:schema:update --dump-sql` pour la lire d'abord.
