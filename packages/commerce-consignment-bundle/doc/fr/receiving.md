<!-- phil-dev source-sha256=84f600a1c70e021033598aa28da66a1c6f4d9673eab3974bcb113a522bf370c1 -->
# Recevoir des marchandises

Une `Receipt` est un ensemble de marchandises arrivées à un emplacement sous une référence — un bordereau de livraison,
un bordereau de consignation —, avec de 1 à 500 lignes. Chaque ligne est achetée ou consignée, et une même réception
peut contenir les deux pour le même SKU :

```php
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ConsignmentTerms;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Receipt;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ReceiptLine;

$receipt = Receipt::of('DN-1042', 'store', [
    // Bought: the unit cost the shop paid.
    ReceiptLine::purchased('BOOK-123', 5, Money::of(1200, $cad)),
    // Consigned for a fixed share of each copy sold.
    ReceiptLine::consigned('BOOK-123', 5, 'author-17', ConsignmentTerms::share(Money::of(1500, $cad))),
    // Consigned for the shop's commission on the price paid: 30 % is 300 000 parts per million.
    ReceiptLine::consigned('MUG-4', 12, 'potter-3', ConsignmentTerms::commission(300_000)),
]);

$layers = $stock->receive($receipt, $clock->now());
```

| Valeur | Règle |
|---|---|
| SKU, emplacement, consignataire, référence | De 1 à 64 caractères, aucun caractère de contrôle, comparés octet par octet |
| Quantité | Une unité ou plus |
| Coût unitaire, part | Zéro ou plus ; la part est due pour chaque unité vendue, quel que soit le prix |
| Commission | De 0 à 1 000 000 de parties par million — l'unité des taux de `phil/pricing` —, un entier qui n'arrondit jamais une fraction avant que le prix soit connu |

`ConsignmentStock::receive()` ouvre une couche par ligne, écrit une écriture `received` pour chacune, puis ajoute les
unités au stock du module de logistique à l'emplacement de la réception. Un refus du grand livre ne déplace aucun stock.

## Contre un bon de commande

`Receipt::of(..., purchaseOrder: 'PO-88')` reçoit les unités contre un bon de commande ouvert du module de logistique,
par son `Purchasing::receive()` : elles comptent contre ce que le bon attend encore, et jamais au-delà. La réception se
fait à l'emplacement du bon ; un autre emplacement est refusé avant que rien ne bouge.

## Stock déjà en main

`ConsignmentStock::adopt()` commence à tenir les propriétaires d'unités que le module de logistique détient déjà — du
stock reçu avant l'installation du bundle —, sans déplacer le stock : il écrit des écritures `adopted`. Le grand livre ne
détient jamais plus d'unités d'un SKU à un emplacement que le module de logistique n'en a en main à cet endroit ; une
adoption au-delà est refusée.
