<!-- phil-dev source-sha256=e18d11d501a0906425732b8bef244d383a24eac262e451d9f224475ea87522f5 -->
# Points d'extension

Quinze ports. Les modules et l'application se branchent aux neuf que l'autoconfiguration étiquette, que lisent
`OrderModifier`, `Payments`, `LineOptions`, `OptionDraws`, `DeferredSales` et `DocumentRegister` ; les six autres sont
des alias, que l'application fait pointer vers ses propres adaptateurs ou laisse sur ceux du bundle (voyez
[installation.md](installation.md)). `StockCommitments`, `DocumentTypes`, `DocumentSeries`, `TransitionForcing` et
`OrderShipments` vivent dans `Phil\CommerceBundle\Application\Contract`, les autres dans
`Phil\CommerceBundle\Application\Port`. Les contrats de stockage du domaine, les événements et ce sur quoi s'appuie un
module de retours les suivent.

| Port | Étiquette | Utilisé par | But |
|---|---|---|---|
| `SellableResolver` | `phil_commerce.sellable_resolver` | `OrderModifier`, à chaque ligne ajoutée ou changée | Ce que vend une référence |
| `UnitPriceResolver` | `phil_commerce.unit_price_resolver` | `OrderModifier`, à chaque ligne à laquelle il donne un prix | Un prix unitaire autre que le prix de base |
| `OrderProcessor` | `phil_commerce.order_processor` | `OrderModifier`, après chaque changement | Recalcule un type d'ajustement |
| `PaymentGateway` | `phil_commerce.payment_gateway` | `Payments` | Un processeur de paiement |
| `SellableAvailability` | `phil_commerce.sellable_availability` | `OrderModifier`, à chaque ligne ajoutée et à chaque changement de canal ; `SellableOffers`, pour les pages | Ce qu'un canal offre de quelque chose |
| `SellableOptions` | `phil_commerce.sellable_options` | `LineOptions`, pour `OrderModifier` et `OptionSupplementProcessor` | Les options de ce qui est vendu |
| `OptionDrawer` | `phil_commerce.option_drawer` | `OptionDraws`, une fois la commande payée | Tire la valeur d'une option pour une unité |
| `StockCommitments` | `phil_commerce.stock_commitments` | `DeferredSales`, quand une vente différée retient ou libère son stock | Retient et libère le stock d'une commande payée plus tard |
| `TaxRateResolver` | — (alias ; `ConfiguredTaxRates` par défaut) | `TaxOrderProcessor` | Les taux qui s'appliquent à une vente |
| `PaymentAttemptStateStore` | — (alias) | l'application, autour de `PaymentAttemptLimiter` | Un état du limiteur par clé, avec une durée de vie |
| `DocumentNumbering` | — (alias ; `DoctrineDocumentNumbering` avec DoctrineBundle) | `Receipts` | Le prochain numéro d'une série de documents |
| `DocumentTypes` | `phil_commerce.document_types` | `DocumentRegister` | Déclare des types de documents au registre |
| `DocumentSeries` | — (alias ; `DoctrineDocumentNumbering` avec DoctrineBundle) | `DocumentRegister` | Le prochain numéro de la série d'un type |
| `TransitionForcing` | — (alias ; `DenyForcing` par défaut) | `OrderLifecycle::force()` | Qui peut forcer une commande hors de ses transitions |
| `OrderShipments` | — (alias ; `UnknownShipments` par défaut) | les modules, un module de retours en premier | Quand une commande, ou l'une de ses lignes, a quitté la boutique |

## `SellableResolver`

| Méthode | Contrat |
|---|---|
| `supports(string $type): bool` | Si le résolveur répond pour ce type de référence |
| `resolve(SellableReference $reference): ?Sellable` | Ce que vend la référence, ou `null` quand rien n'est connu sous son identifiant |

Invariants :

- **Un résolveur par type.** Le premier qui prend en charge un type répond seul ; un type que revendiquent deux
  résolveurs est une erreur de configuration que l'ordre des services réglerait en silence.
- Le prix unitaire de base et la catégorie de taxe viennent des données propres au module, jamais de la requête.
- `null` fait lever `UnknownSellable` par `OrderModifier` : un produit retiré de la vente n'est pas vendable.

```php
namespace App\Commerce;

use Phil\CommerceBundle\Application\Port\SellableResolver;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\Money\Money;

final readonly class GiftCards implements SellableResolver
{
    public function supports(string $type): bool
    {
        return 'gift_card' === $type;
    }

    public function resolve(SellableReference $reference): ?Sellable
    {
        $amount = $this->denominations->find($reference->id); // the application's own data

        return null === $amount ? null : Sellable::of($reference, 'Gift card', $amount, 'exempt');
    }
}
```

## `UnitPriceResolver`

| Méthode | Contrat |
|---|---|
| `priceFor(Sellable $sellable, PricingContext $context): ?Money` | Le prix unitaire dans la devise de l'article vendable, ou `null` quand ce résolveur n'a pas de prix pour lui dans ce contexte |

Invariants :

- Les résolveurs sont interrogés dans l'ordre de leurs étiquettes, qui nomment par `before` et `after` les
  résolveurs qu'ils précèdent ou suivent ; le premier prix donné l'emporte, sinon le prix de base s'applique.
- La quantité du contexte est toute la quantité de la ligne, pour qu'un palier de quantité s'applique à la ligne, pas
  aux unités qui viennent d'être ajoutées.
- Retournez un prix de zéro ou plus ; un rabais sur un prix est un ajustement `Promotion`.

## `OrderProcessor`

| Méthode | Contrat |
|---|---|
| `process(Order $order): void` | Recalcule les ajustements du type propre au processeur avec `Order::replaceAdjustments()` |

Invariants :

- **Un type par processeur, remplacé en entier** : l'exécuter deux fois ne change rien.
- Les processeurs s'exécutent dans l'ordre de leurs étiquettes, qui nomment par `before` et `after` les processeurs
  qu'ils précèdent ou suivent. Les promotions s'exécutent avant `TaxOrderProcessor`, pour que les taxes soient
  calculées sur les lignes après rabais ; la livraison et les frais peuvent s'exécuter d'un côté ou de l'autre.
- Un processeur ne change jamais les lignes ni les prix ; il n'écrit que des ajustements.

## `SellableAvailability`

| Méthode | Contrat |
|---|---|
| `offerThrough(SellableReference $reference, ?string $channel): Offer` | Jusqu'où le canal la montre — `Listed`, `Unlisted` pour son lien direct seulement, `Hidden` —, si une commande du canal peut la contenir, et si son prix est montré |

Invariants : répondez `Offer::full()` pour ce que l'implémentation ne connaît pas, pour que plusieurs sélections
s'additionnent, la réponse la plus stricte l'emportant ; une commande sans canal se voit offrir seulement ce
qu'offrent tous les canaux. La référence arrive sans la configuration d'une ligne.

## `SellableOptions`

| Méthode | Contrat |
|---|---|
| `supports(string $type): bool` | S'il déclare les options de ce type de référence |
| `optionsOf(SellableReference $reference, Currency $currency): OptionSet` | Les options de l'article vendable, ses suppléments dans la devise donnée ; `OptionSet::none()` pour aucune |

Invariants : une implémentation par type, la première qui le prend en charge répondant seule ; la référence arrive
sans la configuration d'une ligne ; la même référence donne les mêmes options au sein d'une requête, puisque
`OrderModifier` vérifie la configuration et que `OptionSupplementProcessor` la facture à partir de deux appels
([options.md](options.md)).

## `OptionDrawer`

| Méthode | Contrat |
|---|---|
| `supports(string $drawer): bool` | S'il tire pour le tireur que nomme une option |
| `draw(string $drawer, Order $order, OrderLine $line, string $option, int $unit): string` | Le tirage pour une unité de la ligne, numérotée à partir de 1 : de 1 à 1000 caractères |

Invariants : appelé seulement pour une commande payée, une fois par unité et par option ; le résultat est gardé avec
la ligne et n'est jamais redemandé. Un tireur dont les acheteurs peuvent vérifier les tirages garde sa propre preuve,
liée à la commande, à la ligne et à l'unité qu'on lui donne.

## `PaymentGateway`

| Méthode | Contrat |
|---|---|
| `supports(string $method): bool` | Si la passerelle répond pour ce mode ; une seule devrait le faire |
| `start(Payment $payment, Order $order): PaymentStart` | Remet le paiement au processeur pour `$payment->amount` ; retourne la référence du processeur et une page ou un secret client |
| `refund(Payment $payment, Money $amount, string $idempotencyKey): void` | Demande un remboursement au processeur, en lui remettant la clé comme clé d'idempotence ; n'enregistre rien |
| `read(string $payload, array $headers): PaymentNotification` | Vérifie la signature du processeur et lit son événement ; lève `InvalidNotification` sinon |

Invariants :

- **Vérifiez avant de lire** : la signature, avec une comparaison en temps constant, et l'âge de la notification
  quand le processeur signe un horodatage. Une notification n'est digne de confiance que par sa signature.
- **Nommez le paiement par la référence propre au processeur**, celle qu'a retournée `start()`, et la notification
  par l'identifiant d'événement propre au processeur : le paiement n'applique un identifiant qu'une seule fois.
- **Faites correspondre les événements du processeur à `PaymentEvent`** ; un événement sur lequel la boutique n'agit
  pas est lu comme `InvalidNotification`, auquel le *webhook* répond 400, ou n'est tout simplement pas souscrit.
- **Ne prenez jamais un montant dans la requête** : `start()` facture le montant du paiement, `refund()` le montant
  demandé.
- **Remettez la clé d'idempotence au processeur** : la même clé accompagne chaque tentative d'un même remboursement,
  de sorte que le processeur ne le fasse qu'une fois.
- **Une passerelle qui est son propre processeur** — elle enregistre un remboursement aussitôt, sans notification à
  attendre — applique le remboursement sous sa clé, comme identifiant de la notification : `Payments::refund()` trouve
  alors la clé appliquée (`Payment::hasApplied()`) et ne fait rien quand le remboursement est redemandé.
- Gardez les identifiants de connexion du processeur dans les secrets de l'application, lus par l'adaptateur.

## `StockCommitments`

`Phil\CommerceBundle\Application\Contract\StockCommitments` retient et libère le stock d'une commande payée plus
tard ([deferred-sales.md](deferred-sales.md)). `DeferredSales` appelle chaque service étiqueté, avant que les
événements soient distribués ; sans aucun, le stock n'est suivi que par les événements.

| Méthode | Contrat |
|---|---|
| `commit(string $orderNumber): void` | Retient la marchandise de la commande pour son acheteur : à l'ouverture de la vente avec `StockCommitment::OnOrder`, une fois réglée avec `OnBalance` |
| `release(string $orderNumber): void` | Libère ce que `commit()` retenait : la vente différée a été annulée avant d'être payée en entier |

Invariants :

- **Appelé une fois par vente et par sens** : une vente engage son stock une fois et le libère au plus une fois.
- Le numéro de la commande est tout ce qu'il reçoit : l'implémentation lit les lignes dans son propre stockage, jamais
  dans une requête.
- Il s'exécute dans l'unité de travail de l'application, avant le commit : il planifie ses écritures dans la même
  transaction, et laisse ce qui part vers l'extérieur à un écouteur après le commit.

```php
namespace App\Logistics;

use Phil\CommerceBundle\Application\Contract\StockCommitments;

final readonly class Reservations implements StockCommitments
{
    public function commit(string $orderNumber): void
    {
        $this->stock->reserveFor($orderNumber); // the application's own stock
    }

    public function release(string $orderNumber): void
    {
        $this->stock->freeFor($orderNumber);
    }
}
```

## `TaxRateResolver`

| Méthode | Contrat |
|---|---|
| `ratesFor(string $taxCategory, string $zone): list<Phil\Tax\TaxRate>` | Chaque taux qui s'applique à une vente de cette catégorie à un acheteur de cette zone, chacun sur la même base ; une liste vide quand aucun ne s'applique |

Invariants :

- La catégorie et la zone sont des noms que choisit l'application (`digital`, `QC`…) ; le bundle ne leur donne aucun
  sens.
- Retournez chaque code de taux une seule fois : `TaxBreakdown` refuse un code qui apparaît deux fois.
- Les taux composés (une taxe sur la base plus une autre taxe sur celle-ci) ne peuvent pas être exprimés : chaque taux
  s'applique à la même base.
- Les seuils, les inscriptions et le lieu de fourniture sont décidés ici ou par le moteur derrière l'adaptateur,
  jamais par le bundle.

Le `ConfiguredTaxRates` propre au bundle répond à partir de `phil_commerce.tax_rates`. Une application dont les taux
viennent d'ailleurs — un moteur de taxes externe, des taux gardés dans sa base de données — crée un alias vers son
propre adaptateur, qui le remplace :

```php
namespace App\Commerce;

use Phil\CommerceBundle\Application\Port\TaxRateResolver;
use Phil\Tax\TaxRate;

final readonly class StoredTaxRates implements TaxRateResolver
{
    public function __construct(private TaxRateRepository $repository) // the application's own storage
    {
    }

    public function ratesFor(string $taxCategory, string $zone): array
    {
        $rates = [];

        foreach ($this->repository->percentagesFor($taxCategory, $zone) as $code => $percent) {
            $rates[] = TaxRate::ofPercent($code, $percent);
        }

        return $rates;
    }
}
```

```yaml
# config/services.yaml
services:
    Phil\CommerceBundle\Application\Port\TaxRateResolver: '@App\Commerce\StoredTaxRates'
```

Un adaptateur sur un moteur de taxes externe peut plutôt construire la ventilation à partir des montants du moteur
avec `TaxLine::reported()` et `TaxBreakdown::of()` de `phil/tax`, pour que le reçu détaille ce qu'a calculé le moteur
(voyez [taxes.md](taxes.md)).

## `PaymentAttemptStateStore`

| Méthode | Contrat |
|---|---|
| `find(PaymentAttemptKey $key): ?PaymentAttemptState` | L'état stocké, ou `null` quand il n'y en a pas ou qu'il a expiré |
| `save(PaymentAttemptKey $key, PaymentAttemptState $state, int $timeToLiveSeconds): void` | Stocke l'état sous `$key->value`, en remplaçant tout état précédent, et **le fait expirer après `$timeToLiveSeconds`** |

Invariants :

- **Respectez la durée de vie.** Une adresse IP du client est un renseignement personnel, et la fenêtre du limiteur
  est ce qui borne la durée pendant laquelle elle est gardée. Un stockage avec expiration native (un stockage
  clé-valeur, un bassin de cache avec une durée de vie par élément) convient naturellement.
- **Stockez exactement l'état donné** : ses trois champs. Aucun verdict, aucun compteur de pénalités, aucun
  historique, aucun journal des clés.
- Reconstruisez l'état avec `PaymentAttemptState::of()` à la relecture.

```php
namespace App\Commerce;

use DateTimeImmutable;
use Phil\CommerceBundle\Application\Port\PaymentAttemptStateStore;
use Phil\CommerceBundle\Domain\ValueObject\PaymentAttemptKey;
use Phil\CommerceBundle\Domain\ValueObject\PaymentAttemptState;
use Psr\Cache\CacheItemPoolInterface;

final readonly class CachePaymentAttemptStateStore implements PaymentAttemptStateStore
{
    public function __construct(private CacheItemPoolInterface $pool)
    {
    }

    public function find(PaymentAttemptKey $key): ?PaymentAttemptState
    {
        $item = $this->pool->getItem($this->id($key));

        if (!$item->isHit()) {
            return null;
        }

        [$attempts, $wait, $last] = $item->get();

        return PaymentAttemptState::of($attempts, $wait, new DateTimeImmutable($last));
    }

    public function save(PaymentAttemptKey $key, PaymentAttemptState $state, int $timeToLiveSeconds): void
    {
        $item = $this->pool->getItem($this->id($key));
        $item->set([$state->attempts, $state->cumulativeWaitSeconds, $state->lastAttemptAt->format('Y-m-d\TH:i:s.uP')]);
        $item->expiresAfter($timeToLiveSeconds);
        $this->pool->save($item);
    }

    private function id(PaymentAttemptKey $key): string
    {
        // PSR-6 forbids some characters in keys (":" among them); hash the key.
        return 'payment_attempt.'.hash('sha256', $key->value);
    }
}
```

## `DocumentNumbering`

| Méthode | Contrat |
|---|---|
| `next(DocumentKind $kind): string` | Le prochain numéro de la série de la nature, mis en forme |

Invariants : **séquentiel et sans trou** — un numéro donné est utilisé, ou rendu avec la transaction qui l'a pris ;
deux appels simultanés ne retournent jamais le même numéro ; chaque nature a sa propre série.
`DoctrineDocumentNumbering` y parvient avec une rangée par série verrouillée en écriture, relue dans la base de
données une fois verrouillée, et refuse de s'exécuter hors d'une transaction. La première rangée d'une série est
créée à part, dans un point de sauvegarde, pour que deux premières émissions simultanées n'échouent pas ; une
migration peut aussi créer les rangées d'avance.

## `DocumentTypes`

| Méthode | Contrat |
|---|---|
| `documentTypes(): iterable<DocumentType>` | Les types que déclare le module, chacun un code, un préfixe, des chiffres et une visibilité |

Invariants : un code et un préfixe qu'aucun autre type n'utilise ; le registre refuse un doublon à sa première lecture.
Un module de logistique déclare ainsi son bordereau d'expédition :

```php
final readonly class LogisticsDocuments implements DocumentTypes
{
    public function documentTypes(): iterable
    {
        yield DocumentType::of('packing_slip', 'PS-', 8);                         // kept for the store
        yield DocumentType::of('delivery_note', 'DN-', 8, Visibility::Unlisted); // reached by the link of its email
    }
}
```

## `DocumentSeries`

| Méthode | Contrat |
|---|---|
| `nextIn(DocumentType $type): string` | Le prochain numéro de la série du type, mis en forme par `DocumentType::format()` |

Invariants : ceux de `DocumentNumbering`, une série par code de type ; `DoctrineDocumentNumbering` implémente les deux
sous le même verrou.

## `TransitionForcing`

| Méthode | Contrat |
|---|---|
| `allows(Order $order, OrderState $to, string $actor): bool` | Si l'acteur peut forcer la commande vers cet état |

Invariants : répondre d'après les droits de l'application, jamais d'après la requête ; refuser par défaut. Un adaptateur
au-dessus des voters de sécurité de l'application :

```php
final readonly class VoterForcing implements TransitionForcing
{
    public function __construct(private AuthorizationCheckerInterface $authorization)
    {
    }

    public function allows(Order $order, OrderState $to, string $actor): bool
    {
        return $this->authorization->isGranted('ROLE_ORDER_FORCE', $order);
    }
}
```

```yaml
# config/services.yaml
services:
    Phil\CommerceBundle\Application\Contract\TransitionForcing: '@App\Commerce\VoterForcing'
```

## `OrderShipments`

| Méthode | Contrat |
|---|---|
| `shippedAt(string $orderNumber, ?SellableReference $line = null): ?DateTimeImmutable` | Quand les biens de la commande ont été expédiés ou remis — ceux de la ligne quand une ligne est donnée — ; `null` quand ils n'ont pas quitté, ou que l'implémentation ne le sait pas |

Invariants : répondre d'après les dossiers de la boutique, jamais d'après une requête ; une ligne expédiée en plusieurs
colis répond le dernier, quand toute la ligne est partie ; une commande répond quand sa dernière ligne est partie. Le
bundle ne garde aucune expédition : son `UnknownShipments` répond `null` pour toute commande, de sorte qu'un module qui
compte une fenêtre depuis l'expédition retombe sur sa propre règle. Un module de logistique, ou l'application, crée
l'alias vers le sien :

```php
namespace App\Logistics;

use DateTimeImmutable;
use Phil\CommerceBundle\Application\Contract\OrderShipments;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;

final readonly class ParcelShipments implements OrderShipments
{
    public function __construct(
        private Parcels $parcels, // the application's own parcels
    ) {
    }

    public function shippedAt(string $orderNumber, ?SellableReference $line = null): ?DateTimeImmutable
    {
        return $this->parcels->lastShippedAt($orderNumber, $line?->value);
    }
}
```

```yaml
# config/services.yaml
services:
    Phil\CommerceBundle\Application\Contract\OrderShipments: '@App\Logistics\ParcelShipments'
```

## Contrats de stockage des nouveaux enregistrements

Chacun est un contrat de `Phil\CommerceBundle\Domain\Contract`, avec l'alias de son adaptateur Doctrine quand
DoctrineBundle est là ; une application qui les stocke ailleurs crée l'alias vers les siens.

| Contrat | Méthodes | Adaptateur Doctrine |
|---|---|---|
| `OrderJournal` | `record()`, `movesOf($orderNumber, $limit)` | `DoctrineOrderJournal` |
| `WishListRepository` | `findWishList()`, `findSharedWishList($tokenHash)`, `wishListsOf($owner, $limit)`, `addWishList()`, `removeWishList()` | `DoctrineWishListRepository` |
| `PaymentLinkRepository` | `findPaymentLinkByTokenHash()`, `paymentLinksOf($orderNumber)`, `addPaymentLink()` | `DoctrinePaymentLinkRepository` |
| `OrderChangeRepository` | `findOrderChange()`, `changesOf($orderNumber)`, `addOrderChange()` | `DoctrineOrderChangeRepository` |
| `RefundRepository` | `findRefund()`, `refundsOf($paymentId)`, `addRefund()` | `DoctrineRefundRepository` |
| `ExpiringOrders` | `unpaidPlacedBefore()`, `expiredBefore()`, `deleteExpired()` | `DoctrineOrderRepository` |

Invariants : `add*()` et `record()` ne font que planifier, l'application valide ; les listes viennent de la plus
ancienne à la plus récente et bornées ; `deleteExpired()` vérifie de nouveau, dans sa transaction, qu'aucun paiement n'a
jamais tenu d'argent et qu'aucun reçu n'existe.

## Événements

| Événement | Envoyé par | Quand |
|---|---|---|
| `OrderPurchased` | `Payments` | Une fois, quand une commande devient payée en entier ([payments.md](payments.md#une-commande-payée-en-entier)) |
| `OrderRefunded` | `Refunds` | Une fois par remboursement exécuté ([refunds.md](refunds.md)) |
| `OrderCancelled` | `OrderLifecycle` | Une fois par annulation : annulée, expirée ou forcée vers `cancelled` ([lifecycle.md](lifecycle.md#annoncer-une-annulation)) |
| `OrderStateForced` | `OrderLifecycle` | Quand une personne force une commande hors de ses transitions ([lifecycle.md](lifecycle.md#forcer-une-transition)) |
| `workflow.phil_commerce_order.*` | le workflow des commandes | À chaque transition, comme les nomme `symfony/workflow` ([lifecycle.md](lifecycle.md)) |

Chacun est envoyé par sa classe et ne porte que des identifiants — le numéro de la commande, son canal, l'identifiant
stable de son client, le moment, et le montant pour un achat ou un remboursement —, jamais un nom, une adresse ni une
ligne ; un écouteur lit le reste de ce dont il a besoin dans son propre stockage, et celui qui confie l'événement à
Messenger n'envoie que ces identifiants.

| Événement | Champs |
|---|---|
| `OrderPurchased` | `orderNumber`, `channel`, `customer`, `total`, `occurredAt()` |
| `OrderRefunded` | `orderNumber`, `refundId`, `paymentId`, `channel`, `customer`, `amount`, `occurredAt()` |
| `OrderCancelled` | `orderNumber`, `from`, `channel`, `customer`, `expired`, `actor`, `occurredAt()` |

### Consigner les achats dans un fil d'activité

Une application qui tient un fil d'activité — l'historique d'un membre, des points, des badges — consigne un achat à
partir de `OrderPurchased` et le retire à `OrderRefunded` ou `OrderCancelled`, avec pour clé le numéro de la commande,
de sorte qu'une deuxième livraison du même événement ne change rien :

```php
namespace App\Activity;

use Phil\CommerceBundle\Domain\Event\OrderCancelled;
use Phil\CommerceBundle\Domain\Event\OrderPurchased;
use Phil\CommerceBundle\Domain\Event\OrderRefunded;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final readonly class PurchaseActivity
{
    public function __construct(
        private ActivityFeed $feed, // the application's own feed
    ) {
    }

    #[AsEventListener]
    public function onPurchased(OrderPurchased $event): void
    {
        if (null !== $event->customer) {
            $this->feed->record($event->customer, 'order.paid', 'order:'.$event->orderNumber.':paid', $event->occurredAt());
        }
    }

    #[AsEventListener]
    public function onRefunded(OrderRefunded $event): void
    {
        $this->feed->cancel('order:'.$event->orderNumber.':paid', $event->occurredAt()); // or only on a refund in full
    }

    #[AsEventListener]
    public function onCancelled(OrderCancelled $event): void
    {
        $this->feed->cancel('order:'.$event->orderNumber.':paid', $event->occurredAt());
    }
}
```

`OrderRefunded` porte le montant remboursé, pas si la commande est maintenant remboursée en entier : un écouteur qui ne
retire qu'à un remboursement en entier le compare à ce qu'il a consigné, ou lit les paiements de la commande avec
`Payments::summary()`.

## Ce sur quoi s'appuie un module de retours

Un module de retours — demandes de retour, leur fenêtre et leurs états, le bordereau de retour — garde ses propres
enregistrements et se branche au noyau par ceci :

| Besoin | Ce que le noyau offre |
|---|---|
| La fenêtre comptée depuis l'expédition | `OrderShipments::shippedAt()`, par commande ou par ligne ; `Order::placedAt()` et `OrderPurchased` quand aucune expédition n'est connue |
| Le remboursement de ce qui est revenu | `Refunds::grant()` et `execute()`, avec la raison du retour ; `OrderRefunded` une fois exécuté ([refunds.md](refunds.md)) |
| La note de crédit de ce qui est revenu | `Receipts`, pour le reçu en entier ([receipts.md](receipts.md)) ; un crédit d'une partie de la quantité n'est pas commencé |
| Un bordereau de retour numéroté sans trou | Un service `DocumentTypes` qui déclare son type — `return_slip`, visibilité `unlisted` —, numéroté par `DocumentRegister::next()`, son PDF mis en page avec `phil/paged-media` ([documents.md](documents.md)) |
| Une commande qui n'est plus retournable | `OrderCancelled`, et les transitions `cancel` et `expire` du workflow (`workflow.phil_commerce_order.completed.expire`) ; `OrderExpiry` ne balaie que les commandes impayées |
| Qui est l'acheteur | `Order::customer()`, l'identifiant stable du client, et `Order::channel()` |

L'expiration d'une demande de retour, sa limite de débit et les états où elle attend sont ceux du module : le noyau ne
connaît aucun retour.

## `ReceiptRepository`

| Méthode | Contrat |
|---|---|
| `find(string $number): ?Receipt` | Le reçu ou la note de crédit |
| `receiptOf(string $orderNumber): ?Receipt` | Le reçu d'une commande ; jamais une note de crédit |
| `creditNotesOf(string $receiptNumber): list<Receipt>` | Les notes de crédit d'un reçu, de la plus ancienne à la plus récente |
| `add(Receipt $receipt): void` | Le planifie pour qu'il soit stocké avec le prochain commit de l'application |

## `PaymentRepository`

`Phil\CommerceBundle\Domain\Repository\PaymentRepository` est le contrat du domaine pour stocker les paiements ; avec
DoctrineBundle, le bundle crée l'alias vers `DoctrinePaymentRepository`, et `Payments` le reçoit par autowiring.

| Méthode | Contrat |
|---|---|
| `find(string $id): ?Payment` | Le paiement stocké, ou `null` |
| `findByExternalReference(string $method, string $externalReference): ?Payment` | Le paiement que nomme une notification |
| `forOrder(string $orderNumber): list<Payment>` | Chaque paiement de la commande, du plus ancien au plus récent |
| `add(Payment $payment): void` | Planifie le paiement pour qu'il soit stocké avec le prochain commit de l'application |

## `OrderRepository`

`Phil\CommerceBundle\Domain\Repository\OrderRepository` est le contrat du domaine pour stocker les commandes ; avec
DoctrineBundle, le bundle crée l'alias vers `DoctrineOrderRepository`. Une application qui stocke les commandes
ailleurs crée plutôt un alias vers son propre adaptateur.

| Méthode | Contrat |
|---|---|
| `find(string $number): ?Order` | La commande stockée, ou `null` |
| `add(Order $order): void` | Planifie la commande pour qu'elle soit stockée avec le prochain commit de l'application |

Invariants : stockez la commande en entier, lignes et ajustements compris ; refusez une écriture sur une copie
périmée, comme le fait la colonne de version ; reconstruisez les lignes et les ajustements par leurs propres
constructeurs à la lecture.

## Objets-valeurs et services

Chaque autre type public est une classe `final` : utilisez-les, ne les étendez pas. `PaymentAttemptLimiter` ne
contient qu'une politique : déclarez-le comme service ou construisez-le là où il faut ; `OrderModifier` vient du
conteneur.
