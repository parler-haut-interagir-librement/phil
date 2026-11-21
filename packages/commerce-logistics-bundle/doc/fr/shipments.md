<!-- phil-dev source-sha256=119e6ac1552a04b7e0b700b89868503e88bab6f79243b7da40e85e6a58fb47fd -->
<!-- phil-dev source-sha256=0 -->
# Expéditions

Une commande part en un colis ou en plusieurs. Chaque expédition dit combien d'unités de quelles lignes partent, les
retire de la tablette à partir des réservations de la commande, et garde le transporteur et le numéro de suivi.

```php
$shipments->ship($order, $clock->now(), ['product:DICE' => 4], carrier: 'Canada Post', tracking: 'CP123456789CA');
$shipments->left($order);    // ['product:DICE' => 2, 'product:MAP' => 1]
$shipments->state($order);   // FulfilmentState::Partial
$shipments->ship($order, $clock->now());   // everything left
```

- Les lignes sont nommées comme la commande les nomme, `type:id`. Seule une commande passée est expédiée, et aucune
  ligne n'est expédiée au-delà de ce qu'il en reste.
- Une ligne `product` prend ses unités dans les réservations de la commande, dans l'ordre où elles ont été faites ;
  une ligne d'un autre type, ou un produit non suivi, part sans toucher au stock.
- Expédier plus d'un *SKU* que n'en tiennent encore ses réservations est refusé ; libérer la commande ensuite ne rend
  que ce qui est encore réservé.

## Les unités expédiées

Quand des unités quittent la tablette, `Stock` envoie `UnitsShipped` par le répartiteur d'événements de l'application,
selon sa classe : ce qu'écoute un registre de propriétaires, un rapport de ventes ou une notification.

```php
use Phil\CommerceLogisticsBundle\Domain\Event\UnitsShipped;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class RecordTheSale
{
    public function __invoke(UnitsShipped $event): void
    {
        foreach ($event->lines as $line) {
            // $event->orderNumber, $line->sku, $line->location, $line->units, $event->occurredAt()
        }
    }
}
```

- **Un événement par appel qui a déplacé des unités** : `Stock::ship()` annonce chaque unité de la commande qui est
  partie, et `Stock::shipPart()` — que `Shipments::ship()` appelle une fois par ligne de produit — celles d'un seul
  *SKU*. Un appel qui ne déplace rien — une commande déjà expédiée, un *SKU* non suivi — n'envoie rien.
- **Une ligne par *SKU* et par emplacement**, avec les unités qui en sont parties ; `units()` donne le total. Le moment
  est celui qu'on a donné à l'appel, en UTC.
- **Aucune donnée personnelle** : le numéro de la commande, les *SKU*, les emplacements et les unités seulement. Un
  écouteur lit le reste dans son propre stockage, et l'événement peut traverser un transport de Messenger tel quel.
- **Après le journal, avant le commit.** L'événement est envoyé une fois les mouvements écrits dans l'unité de travail,
  et avant que l'application la vide, comme les événements du noyau. Un écouteur qui écrit dans la base écrit dans la
  même transaction : la vente et le mouvement sont validés ensemble, ou aucun des deux. Un vidage repris après un échec
  n'envoie pas l'événement de nouveau, puisque c'est l'appel qui l'envoie, non le vidage ; un appel refait dans une
  nouvelle unité de travail après une annulation annonce ce qu'il déplace alors. Un écouteur qui joint le monde
  extérieur — un courriel, un webhook — le fait après la validation, par Messenger avec le *middleware* de transaction
  de Doctrine par exemple.
- Sans répartiteur d'événements, rien n'est envoyé, et les unités s'expédient comme avant.

## Bordereaux de livraison et listes de prélèvement

`ShippingDocuments` émet le bordereau de livraison d'une expédition et la liste de prélèvement d'une commande.
`LogisticsDocumentTypes` déclare les deux au registre des types de documents du noyau, portant l'étiquette
`phil_commerce.document_types`, chacun numéroté dans sa propre série sans trou : `DN-00000001` et `PS-00000001` par
défaut.

```php
$slip = $entityManager->wrapInTransaction(fn () => $documents->pickingSlip($order, $clock->now()));
$slip->lines;         // [PickingLine('DICE', 'store', 2), PickingLine('DICE', 'warehouse', 1), PickingLine('MAP', null, 2)]
$slip->giftWrapped;   // true: wrap it, in recycled wrap when $slip->recycledWrap

$note = $entityManager->wrapInTransaction(fn () => $documents->deliveryNote($order, $shipment, $clock->now()));
$note->lines;         // [DeliveryNoteLine('product:DICE', 'Dice', 2, Money 10.00 CAD), …]
$note->giftMessage;   // 'Bonne fête!'
```

- **Un numéro est tiré dans la transaction qui stocke le document**, comme le demande le registre du noyau : enveloppez
  l'appel dans `wrapInTransaction()`. Un document est numéroté une seule fois — un bordereau de livraison par expédition,
  une liste de prélèvement par commande — ; l'émettre de nouveau rend son numéro et son moment, avec ce que la commande
  contient maintenant.
- **La liste de prélèvement** énumère les unités encore réservées pour la commande, par emplacement puis par *SKU*, et
  enfin les lignes de produits dont le stock n'est pas suivi, sans emplacement. Elle dit s'il faut emballer la commande
  dans un emballage-cadeau, et dans lequel.
- **Le bordereau de livraison** énumère ce que le colis contient de chaque ligne, avec le transporteur et le numéro de
  suivi, et les options-cadeaux de la commande : l'emballage, le message pour la personne qui le reçoit, et aucun prix
  unitaire quand la commande cache ses prix. Le noyau facture lui-même l'emballage, par son processeur `GiftWrapping`.
- `DeliveryNote` et `PickingSlip` sont ce que l'application imprime, dans le gabarit qu'elle choisit.

## Vagues d'expédition

`ShippingWaves` planifie les commandes qui partent ensemble un jour estimé — les marchandises d'une précommande qui
arrivent, un tirage d'impression —, montre ce jour à l'acheteur, et libère la vague quand il arrive.

```php
$waves->plan('january', 'January run', new DateTimeImmutable('2027-01-15'));
$waves->assign('2026-0412', 'january');
$waves->estimatedOn('2026-0412');            // 2027-01-15
$waves->reschedule('january', new DateTimeImmutable('2027-01-29'));
$orders = $waves->release('january', $clock->now());   // the numbers of its orders, ready to ship
```

- Une commande est dans une seule vague au plus : l'assigner de nouveau la déplace, `withdraw()` la retire.
- Une vague libérée garde ses commandes et son jour : on ne peut plus lui assigner de commande, en retirer, la
  reporter ni la libérer.
- `planned()` énumère les vagues pas encore libérées, la plus proche d'abord.

## Stockage

| Table | Contient |
|---|---|
| `phil_commerce_logistics_shipment` | `id` (UUID v7), `order_number`, `line_items` (JSON, unités par ligne), `shipped_at`, `carrier`, `tracking` ; indexée par commande |
| `phil_commerce_logistics_document` | `number` (clé primaire), `kind` (`delivery_note`, `picking_slip`), `order_number` (indexé), `shipment` (vide pour une liste de prélèvement), `issued_at` ; unique par type, commande et expédition |
| `phil_commerce_logistics_wave` | `code` (clé primaire), `name`, `estimated_on` (date), `released_at` ; indexée par libération et jour |
| `phil_commerce_logistics_wave_order` | `order_number` (clé primaire), `wave` (indexé) |

Une réservation garde aussi combien de ses unités sont déjà parties, dans sa colonne `shipped`.
