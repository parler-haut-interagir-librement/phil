<!-- phil-dev source-sha256=d9fde2483b68e92f2397705f5840736d9135ef4ed81b58aa25895b9f4ba3b6aa -->
# Commandes

Une commande se construit ligne par ligne, puis ses ajustements sont recalculés par les processeurs de commande. Le
noyau tient la commande et les ports ; ce qui est vendu et à quel prix vient des modules et de l'application.

| Type | Rôle |
|---|---|
| `Domain\Model\Order` | La commande : numéro, devise, lignes, ajustements, zone de taxe, état ; totaux toujours calculés |
| `Domain\Order\OrderState` | `Cart`, `Quoted`, `Placed`, `Cancelled` |
| `Domain\Repository\OrderRepository` | Trouve et stocke les commandes ; `DoctrineOrderRepository` avec DoctrineBundle |
| `Domain\ValueObject\OrderLine` | Une ligne : ce qui est vendu, la quantité, le prix unitaire, la catégorie de taxe, la configuration ([options.md](options.md)) |
| `Domain\ValueObject\SellableReference` | Ce que vend une ligne : un type et un identifiant, `product:42`, et l'empreinte d'une ligne configurée |
| `Domain\ValueObject\Sellable` | Ce qu'un module dit d'une référence : la description, le prix unitaire de base, la catégorie de taxe, les options |
| `Domain\ValueObject\PricingContext` | Ce dont un prix peut dépendre : la quantité, le moment, le groupe de clients, le canal |
| `Domain\ValueObject\Adjustment` | Un montant sur la commande ou sur une ligne, avec son libellé ; neutre ou non ; son origine, telle que la nomme son processeur |
| `Domain\Adjustment\AdjustmentType` | `Option`, `Promotion`, `Proration`, `Tax`, `Shipping`, `Fee`, `Tip` |
| `Application\OrderModifier` | Change une commande par les ports et la recalcule |

## Exemple

```php
use Phil\CommerceBundle\Application\OrderModifier;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\PricingContext;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\Money\Currency;

$order = Order::open('2026-0001', Currency::of('CAD'));
$context = PricingContext::of(1, $clock->now(), customerGroup: 'wholesale', channel: 'web');

$modifier->add($order, SellableReference::of('product', 'dice'), 2, $context); // OrderModifier, from the container
$modifier->changeTaxZone($order, 'QC');

$order->itemsTotal();        // the lines, at the prices the resolvers gave
$order->adjustmentsTotal();  // promotions, taxes, shipping, fees, tips that are not neutral
$order->total();

$modifier->place($order, $clock->now());   // recomputed once more, then frozen
$orders->add($order);                       // OrderRepository; the application commits its unit of work
```

## `OrderModifier`

| Méthode | Comportement |
|---|---|
| `add(Order, SellableReference, int $quantity, PricingContext, ?LineConfiguration): OrderLine` | Résout l'article vendable et ses options, vérifie la configuration, donne un prix à la ligne pour toute sa nouvelle quantité, l'ajoute, recalcule ; retourne la ligne |
| `changeQuantity(Order, SellableReference, int $quantity, PricingContext)` | Redonne un prix à la ligne pour la nouvelle quantité, recalcule |
| `remove(Order, SellableReference)` | Retire la ligne et ses ajustements, recalcule |
| `changeTaxZone(Order, ?string $zone)` | Fixe la zone pour laquelle les taux de taxe sont lus, recalcule |
| `changeAudience(Order, ?string $channel, ?string $customerGroup, PricingContext)` | Fixe par qui et à qui la commande est vendue, redonne un prix à chaque ligne pour eux, recalcule |
| `chooseDeliveryMethod(Order, ?string $method)` | Enregistre le mode de livraison que l'acheteur a choisi, recalcule |
| `enterCode(Order, string $code)`, `removeCode(Order, string $code)` | Enregistre ou abandonne un code de promotion que l'acheteur a saisi, recalcule |
| `place(Order, DateTimeImmutable $at)` | Recalcule le panier une dernière fois, puis le passe |
| `recalculate(Order)` | Exécute de nouveau chaque processeur, après un changement que le modificateur n'a pas fait |

- **Les prix viennent des résolveurs, jamais de l'appelant.** Une requête nomme quoi acheter et en quelle quantité ;
  le serveur décide de ce que cela coûte.
- **Le canal de la commande doit le vendre** : chaque `SellableAvailability` est interrogée, à l'ajout d'une ligne et
  à `changeAudience()` ; un article vendable qu'aucune commande du canal ne peut contenir lève `NotSoldThere` avant que
  quoi que ce soit change, et celui que seul son lien direct atteint se vend comme tout ce qui est listé
  ([channels.md](channels.md)).
- **Le premier résolveur d'articles vendables qui prend en charge le type de la référence répond** ; s'il ne connaît
  rien sous cet identifiant, `UnknownSellable` est levée, et aucun autre résolveur n'est interrogé.
- **Le prix unitaire** est le premier que donne un `UnitPriceResolver`, dans l'ordre de leurs étiquettes, interrogé
  avec toute la quantité de la ligne et le canal et le groupe de clients de la commande — sauf si le
  `PricingContext` de l'appelant nomme les siens — ; sinon, le prix de base de l'article vendable.
- **Les options viennent du premier `SellableOptions` du type de la référence**, par `LineOptions`, et vérifient la
  configuration : `InvalidConfiguration` avant que quoi que ce soit change ([options.md](options.md)).
- **Les résolveurs, les prix et les sélections sont interrogés avec la référence `unconfigured()`** : ce qui est
  vendu, sans la configuration d'une ligne.
- **Chaque processeur s'exécute après chaque changement**, dans l'ordre de leurs étiquettes.

## `Order`

| Membre | Comportement |
|---|---|
| `static open(string $number, Currency $currency): self` | Le numéro : de 1 à 64 caractères ASCII visibles |
| `add(Sellable, int $quantity, Money $unitPrice, ?LineConfiguration): OrderLine` | Ajoute une ligne, ou ajoute à la ligne qui vend la même chose avec les mêmes valeurs d'options, au nouveau prix unitaire ; les options de l'article vendable vérifient la configuration |
| `changeQuantity(SellableReference, int $quantity, Money $unitPrice)` | `LineNotInOrder` quand la commande n'a pas cette ligne |
| `remove(SellableReference)` | Retire la ligne et ses ajustements |
| `recordDraws(SellableReference, string $option, list<string> $draws)` | Une commande passée seulement : les tirages d'une option laissée à tirer, un par unité |
| `line(SellableReference): ?OrderLine`, `lines(): list<OrderLine>`, `isEmpty()` | Les lignes, dans l'ordre où elles ont été ajoutées la première fois |
| `taxZone(): ?string`, `changeTaxZone(?string)` | La zone pour laquelle les taux de taxe sont lus ; `null` tant qu'elle n'est pas connue |
| `channel(): ?string`, `changeChannel(?string)` | Le canal qui vend — un site, un point de vente — ; de 1 à 64 caractères ASCII visibles |
| `customerGroup(): ?string`, `changeCustomerGroup(?string)` | Le groupe de clients de l'acheteur ; de 1 à 64 caractères ASCII visibles |
| `deliveryMethod(): ?string`, `chooseDeliveryMethod(?string)` | Le mode de livraison choisi, tel que le nomme le module de logistique |
| `codes(): list<string>`, `enterCode(string)`, `removeCode(string)` | Les codes de promotion saisis, chacun une fois ; ce que vaut un code est la décision des promotions |
| `replaceAdjustments(AdjustmentType, list<Adjustment>)` | Remplace chaque ajustement de ce type ; refusé, sans changement, quand l'un est d'un autre type, dans une autre devise ou sur une ligne absente de la commande |
| `adjustments(?AdjustmentType)`, `lineAdjustments(SellableReference, ?AdjustmentType)` | Les ajustements, tous ou d'un type, sur la commande ou sur une ligne |
| `adjustmentsByLine(?AdjustmentType)` | Les ajustements sur les lignes, tous ou d'un type, indexés par la valeur de la référence de la ligne, lus en une passe : ce qu'utilise un processeur qui visite chaque ligne au lieu d'un `lineAdjustments()` par ligne |
| `itemsTotal()`, `adjustmentsTotal(?AdjustmentType)`, `total()` | Calculés chaque fois ; les ajustements neutres sont exclus des sommes |
| `taxIncluded()`, `changeTaxIncluded(bool)` | Si les prix incluent les taxes ; un panier seulement |
| `includedTax()` | Les ajustements de taxe neutres : les taxes que les prix incluent |
| `state()`, `placedAt()`, `cancelledAt()` | Où en est la commande, et quand elle a changé d'état |
| `quote()` | Un panier d'au moins une ligne devient une soumission : figé jusqu'à ce qu'il soit passé ou rouvert |
| `place(DateTimeImmutable $at)` | Un panier ou une soumission d'au moins une ligne devient passé |
| `reopen()` | Une soumission ou une commande passée redevient un panier, pour être modifiée ; une commande passée oublie son moment |
| `cancel(DateTimeImmutable $at)` | Un panier, une soumission ou une commande passée devient annulé |
| `version(): int` | La version stockée, que fait avancer chaque écriture |

- **Une devise par commande.** Un prix ou un ajustement dans une autre devise lève `CurrencyMismatch`.
- **Une ligne contient au moins une unité, à un prix unitaire de zéro ou plus.** Un rabais est un ajustement, jamais
  un prix négatif.
- **Un ajustement neutre est affiché mais pas additionné** : une taxe déjà incluse dans le prix, par exemple.
- **Un ajustement peut garder son origine**, ce qui l'a donné tel que le nomme son processeur
  (`promotion:black-friday`). Jamais affichée, elle permet à un module de lire après le passage de la commande ce
  qu'elle a obtenu, plutôt que de le recalculer à partir de règles qui peuvent avoir changé depuis.

## États

| De | Vers | Par |
|---|---|---|
| `Cart` | `Quoted` | `quote()`, quand le panier a une ligne |
| `Cart` | `Placed` | `place()`, quand le panier a une ligne |
| `Cart` | `Cancelled` | `cancel()` |
| `Quoted` | `Placed` | `place()` : la soumission acceptée, à ses prix |
| `Quoted` | `Cart` | `reopen()` : la soumission refusée |
| `Quoted` | `Cancelled` | `cancel()` |
| `Placed` | `Cart` | `reopen()` : la commande modifiée ; savoir si elle peut encore l'être — aucun reçu, rien d'expédié — relève de l'application |
| `Placed` | `Cancelled` | `cancel()` ; rembourser ce qui a été payé relève du paiement |

- **Seul un panier change**, sauf les tirages qu'une commande passée enregistre après le paiement
  ([options.md](options.md#les-tirages-après-le-paiement)). Ajouter, changer ou retirer une ligne, changer la zone de
  taxe, le canal, le groupe de clients, le mode de livraison ou les codes, ou remplacer les ajustements d'une commande
  en soumission, passée ou annulée lève `OrderLocked`, tout comme un passage que la table ne liste pas.
- **Ce que l'acheteur accepte est ce que donnent les processeurs au passage de la commande** :
  `OrderModifier::place()` recalcule le panier avant de le passer.
- **Le workflow `phil_commerce_order` décrit ces passages**, les journalise, et laisse une personne autorisée en forcer
  un hors d'eux ; `Order::expire()` annule une commande passée que personne n'a payée à temps
  ([lifecycle.md](lifecycle.md), [expiry.md](expiry.md)).

## À côté des lignes

| Membre | Contient | Voir |
|---|---|---|
| `customer()`, `assignCustomer()` | L'identifiant stable de l'acheteur, jamais une adresse de courriel ; `null` pour un invité | [payments.md](payments.md#une-commande-payée-en-entier) |
| `giftOptions()`, `offerAsGift()` | L'emballage, l'emballage recyclé, le message, les prix cachés | [gifts.md](gifts.md) |
| `wishList()`, `buyFromWishList()` | La liste de souhaits où la commande achète | [gifts.md](gifts.md#listes-de-souhaits) |
| `manualDiscounts()`, `grantManualDiscount()`, `withdrawManualDiscount()` | Les rabais accordés à la main | [discounts.md](discounts.md) |
| `status()`, `changeStatus()` | Le statut de l'application, rattaché à un état | [lifecycle.md](lifecycle.md#statuts) |
| `revision()`, `recordRevision()` | 1 au passage de la commande, un de plus par changement confirmé | [order-changes.md](order-changes.md) |
| `expiredAt()`, `expire()` | Quand la commande a expiré impayée | [expiry.md](expiry.md) |

## Soumissions

`Quotes` offre un panier à un client jusqu'à une date : `issue()` le recalcule une dernière fois et le fige ;
`accept()` le passe **aux prix soumis**, sans rien recalculer, et est refusé une fois la soumission expirée ;
`decline()` en refait un panier. Chaque soumission est un `Quote` — le numéro de la commande, le client, quand elle a
été émise, jusqu'à quand elle est valide, `Pending`, `Accepted` ou `Declined` — dans la table `phil_commerce_quote`.

```php
$quotes->issue($order, 'customer-42', $clock->now(), $clock->now()->modify('+30 days'));
$quotes->accept($order, $clock->now());
```

## Modifier une commande passée

`OrderAmendments::reopen()` refait un panier d'une commande passée, sauf si son reçu est émis — une note de crédit et
une nouvelle commande la remplacent alors. Le panier est modifié avec l'`OrderModifier` et passé de nouveau ; ses
paiements restent. Ce qui lui manque ensuite est `payable()` ; ce qui a été payé au-delà de son nouveau total est
`overpaid()`, que l'application rembourse. Libérer et réserver de nouveau son stock relève du module de logistique. Un changement préparé,
confirmé et versionné, avec sa différence due, passe par `OrderChanges` ([order-changes.md](order-changes.md)).

## Stockage

Quand DoctrineBundle est enregistré, le bundle mappe `Order` à la table `phil_commerce_order` et crée l'alias
`OrderRepository` vers `DoctrineOrderRepository`. **Une commande est une rangée** : ses lignes et ses ajustements sont
des valeurs de la commande, stockées dans deux colonnes JSON de types propres, `phil_commerce_order_lines` et
`phil_commerce_adjustments`, jamais comme des entités à part.

| Colonne | Type | Contient |
|---|---|---|
| `number` | `VARCHAR(64)`, clé primaire | Le numéro de la commande |
| `currency` | `CHAR(3)` | Le code ISO 4217 de la devise de la commande |
| `lines` | JSON (`phil_commerce_order_lines`) | Chaque ligne : la référence, la description, la quantité, le prix unitaire en unités mineures, la devise, la catégorie de taxe et, pour une ligne configurée, sa configuration versionnée ([options.md](options.md#stockage)) |
| `adjustments` | JSON (`phil_commerce_adjustments`) | Chaque ajustement : le type, le libellé, le montant en unités mineures, la devise, la ligne avec l'empreinte d'une ligne configurée, neutre, l'origine (une rangée qui ne l'a pas n'en a aucune) |
| `tax_zone` | `VARCHAR(64)`, nullable | La zone pour laquelle les taux de taxe sont lus |
| `tax_included` | `BOOLEAN`, faux par défaut | Si les prix unitaires incluent les taxes |
| `channel`, `customer_group` | `VARCHAR(64)`, nullable | Par qui et à qui la commande est vendue |
| `delivery_method` | `VARCHAR(64)`, nullable | Le mode de livraison choisi |
| `codes` | JSON | Les codes de promotion saisis |
| `state` | `VARCHAR(16)`, indexé avec `placed_at` | `cart`, `quoted`, `placed` ou `cancelled` |
| `placed_at`, `cancelled_at` | `DATETIME`, nullable | Quand la commande a changé d'état |
| `customer` | `VARCHAR(255)`, nullable | L'identifiant stable de l'acheteur |
| `gift` | JSON, nullable | Les options de cadeau |
| `wish_list` | `CHAR(36)`, nullable | La liste de souhaits où la commande achète |
| `manual_discounts` | JSON | Les rabais accordés à la main |
| `status` | `VARCHAR(64)`, nullable | Le statut de l'application |
| `revision` | `INTEGER`, 1 par défaut | Un de plus par changement confirmé |
| `expired_at` | `DATETIME`, nullable | Quand la commande a expiré impayée |
| `payments_started` | `INTEGER`, 0 par défaut | Combien de paiements ont été commencés ; chaque début fait avancer la version ([payments.md](payments.md#payer-une-commande)) |
| `version` | `INTEGER` | Avancée par chaque écriture ; une écriture sur une copie périmée échoue avec l'`OptimisticLockException` de Doctrine |

- **L'application crée la table avec ses migrations** : `doctrine:migrations:diff` voit le mappage comme celui de ses
  propres entités.
- **`add()` ne fait que planifier** : l'application valide son unité de travail, et un changement apporté à une
  commande trouvée est écrit avec ce même commit.
- **Une valeur stockée qu'aucun code n'aurait pu écrire** — une ligne sans quantité, une devise inconnue — est
  refusée à la lecture comme une erreur de conversion DBAL plutôt que lue dans une commande.

## Objets-valeurs

| Type | Règles |
|---|---|
| `SellableReference::of(string $type, string $id, ?string $configuration)` | Type : jusqu'à 32 lettres minuscules, chiffres ou `_`, commençant par une lettre ; identifiant : de 1 à 128 caractères ASCII visibles ; configuration : une empreinte de 16 chiffres hexadécimaux minuscules, ou aucune ; `value()` vaut `type:id`, ou `type.fingerprint:id`, relu par `fromValue()` |
| `Sellable::of(SellableReference, string $description, Money $unitPrice, string $taxCategory, ?OptionSet $options)` | Une référence sans configuration, une description, un prix unitaire de zéro ou plus, une catégorie de taxe ; aucune option par défaut, `withOptions()` |
| `PricingContext::of(int $quantity, DateTimeImmutable $at, ?string $customerGroup, ?string $channel)` | Au moins une unité ; un groupe ou un canal, quand il est donné, a un nom |
| `Adjustment::of(AdjustmentType, string $label, Money $amount, ?SellableReference $line, bool $neutral, ?string $origin)` | Un libellé que montre le reçu ; un montant signé ; une origine de 1 à 255 caractères ASCII visibles, ou aucune |

Chaque refus lève `InvalidValue` de `phil/ddd-bundle`. `LineNotInOrder` et `UnknownSellable` implémentent son
`DomainError`.

## Ailleurs

Les paiements sont dans [payments.md](payments.md), les reçus et les notes de crédit dans [receipts.md](receipts.md).
