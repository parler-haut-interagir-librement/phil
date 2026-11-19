<!-- phil-dev source-sha256=d6e9eacf55ef70137146c8787d41671a7cd0d3c6cf27a73733fa5ff36eae9d0b -->
# Promotions

Une promotion retranche quelque chose d'une commande : un pourcentage de chaque ligne admissible, un montant réparti
sur les lignes admissibles, N unités pour le prix de X, un prix pour chaque lot complet de groupes de produits, ou les
frais de livraison annulés. Elle s'applique où, à qui et quand le disent ses conditions, et certaines exigent un code.

```php
use Phil\CommercePromotionsBundle\Domain\Model\Promotion;
use Phil\CommercePromotionsBundle\Domain\ValueObject\BundleGroup;

$promotions->add(Promotion::percentOff('black-friday', 'Vendredi fou −20 %', 200_000, Currency::of('CAD'))
    ->when(channels: ['web'], startsAt: $friday, endsAt: $monday, excludedSkus: ['GIFT-CARD']));

$promotions->add(Promotion::buyNPayX('dice-3-for-2', 'Dés : 3 pour 2', 3, 2, Currency::of('CAD'))
    ->when(skus: ['DICE-D6', 'DICE-D20'], after: ['black-friday']));

$promotions->add(Promotion::bundlePrice('starter', 'Boîte de départ', [
    BundleGroup::of(['RULEBOOK']),
    BundleGroup::of(['DICE-D6', 'DICE-D20'], 2),
], Money::of(5_000, Currency::of('CAD'))));

$promotions->add(Promotion::freeShipping('ship-free', 'Livraison offerte', Currency::of('CAD'))
    ->when(deliveryMethods: ['post'], minimumSubtotal: Money::of(7_500, Currency::of('CAD'))));

$coupon = Promotion::amountOff('welcome', 'Bienvenue −5 $', Money::of(500, Currency::of('CAD')))
    ->when(requiresCode: true, perCustomerLimit: 1, minimumSubtotal: Money::of(3_000, Currency::of('CAD')));
$promotions->add($coupon);
$codes = $promotionService->generateCodes($coupon, 500, 'WELCOME-'); // 500 codes of one use each
```

`$promotions` est le `PromotionRepository` ; `$promotionService` est le service public `Promotions`.

## Ce que donne une promotion

| Constructeur | Donne |
|---|---|
| `percentOff(code, label, ppm, currency)` | Un pourcentage de rabais sur chaque ligne admissible : `100_000` ppm valent 10 %, de 1 à 1 000 000 ppm |
| `amountOff(code, label, amount)` | Un montant retranché de la commande, au plus ce que valent les lignes admissibles, réparti entre elles en proportion de ce que vaut chacune — pour que la taxe de chaque ligne suive son rabais |
| `buyNPayX(code, label, buy, pay, currency)` | Sur chaque ligne admissible, chaque groupe de `buy` unités a `buy − pay` unités gratuites ; jusqu'à 1 000 |
| `bundlePrice(code, label, groups, price)` | Un prix pour chaque lot complet : de 1 à 10 `BundleGroup`, chacun une liste de 1 à 100 produits et un nombre d'unités, de 1 à 1 000 ; aucun produit dans deux groupes |
| `freeShipping(code, label, currency)` | Les frais de livraison annulés, pour les modes listés ou pour tous les modes |

Le libellé est ce que montre le reçu ; `relabel()` le change, et ses traductions vivent sur `PromotionTranslation`. Les
rabais sont des ajustements `Promotion` sur les lignes, jamais un prix négatif.

### Les lots

Un lot est complet quand chacun de ses groupes tient son nombre d'unités ; une commande contient autant de lots que son
groupe le plus rare le permet. Chaque groupe prend ses unités d'abord sur ses lignes les moins chères, la plus
ancienne ligne d'abord en cas d'égalité. Le rabais est ce que valent ces unités, après les promotions précédentes,
moins le prix du lot multiplié par le nombre de lots ; il est réparti sur les lignes qui ont donné des unités, en
proportion de ce que chacune a donné.

**Chaque produit garde sa propre ligne** : une promotion entre deux produits donne deux lignes réduites, chacune portant
sa part du rabais. Un produit retourné seul rompt la promotion : il est remboursé à sa propre valeur moins tout le rabais
de la promotion, jamais sous zéro, et les produits gardés sont payés en entier. Un produit vendu en entier comme
un ensemble — une boîte assemblée une fois et stockée sous un seul *SKU* — est plutôt un produit composé du catalogue,
une seule ligne taxée en bloc.

### La livraison gratuite

Une livraison gratuite s'applique quand le mode de livraison de la commande en est un qu'elle annule, et que ses lignes
admissibles atteignent son minimum. `PromotionEvaluator` laisse alors sur la commande un ajustement neutre de zéro,
libellé et portant l'origine de la promotion. `FreeShippingProcessor`, un processeur de commande étiqueté après le
`ShippingProcessor` du module de logistique et avant les taxes du noyau, ajoute un ajustement de livraison qui reprend
ce que coûte la livraison : le reçu montre les frais et leur annulation. Seule la première livraison gratuite annule ;
des frais de livraison neutres — déjà inclus — ne sont jamais annulés. Sans le module de logistique, la commande n'a
aucuns frais de livraison et rien n'est annulé.

## Quand elle s'applique

`when()` fixe les conditions avant que la promotion soit stockée ; chaque argument omis garde la condition actuelle.
Chaque liste contient jusqu'à 500 noms de 1 à 64 caractères ASCII visibles, sans répétition.

| Condition | Vide signifie |
|---|---|
| `skus` | Tous les produits ; seuls les produits ont un rabais, jamais les offres ni les autres articles que vend le noyau |
| `excludedSkus` | Rien d'exclu ; un produit exclu n'a jamais de rabais de la promotion, même quand `skus` le liste |
| `channels` | Tous les canaux : en ligne, en magasin ou les deux, c'est une liste de canaux |
| `customerGroups` | Tout le monde |
| `deliveryMethods` | Tous les modes de livraison, pour une livraison gratuite |
| `ownedSkus` | Aucune condition ; sinon, le client possède l'un de ces produits, selon le `CustomerOwnership` de l'application |
| `requiresCode` | Aucun code exigé |
| `startsAt`, `endsAt` | Toujours ; le début compris, la fin exclue |
| `usageLimit` | Aucune limite au total |
| `perCustomerLimit` | Aucune limite par client ; sinon chaque client obtient ce nombre d'utilisations, et un invité aucune |
| `minimumSubtotal` | Aucun minimum : ce que valent les lignes admissibles, après les promotions précédentes, doit l'atteindre |
| `after` | Essayée par code ; sinon après les promotions listées, quand elles sont actives aussi |
| `incompatibleWith` | Se combine avec toutes les promotions ; sinon jamais avec celles listées, quelle que soit celle des deux listes qui nomme l'autre |
| `exclusive` | Non exclusive |

Le canal et le groupe de clients sont ceux de la commande (`OrderModifier::changeAudience()`), le client est celui de
son `assignCustomer()`, le mode de livraison celui de son `chooseDeliveryMethod()`, le code est celui que l'acheteur a
saisi (`OrderModifier::enterCode()`), et le moment est celui de l'horloge. Les codes sont stockés en majuscules et
reconnus quelle que soit la casse tapée par l'acheteur : `welcome-7kq4m2xa` débloque `WELCOME-7KQ4M2XA`.

## Comment les promotions s'additionnent

`PromotionProcessor` est un processeur de commande du noyau, étiqueté avant son `TaxOrderProcessor`, son
`ManualDiscountProcessor` et le `ShippingProcessor` du module de logistique, pour que chaque ligne soit taxée une fois
le rabais appliqué et que le seuil de livraison lise les lignes réduites. Chaque fois que la commande change :

1. les promotions non retirées dont le canal, le groupe, la devise, la période et la limite tiennent sont triées par
   code, puis chacune est déplacée après les promotions que nomme sa liste `after`, en profondeur d'abord, par le
   `BeforeAfterSorter` de Symfony ; un nom qui n'est celui d'aucune promotion est ignoré, et les listes qui bouclent
   sont écartées au profit de l'ordre par code ;
2. une promotion s'applique quand son code a été saisi et reste utilisable si elle en exige un, que le client a encore
   des utilisations et possède ce qu'elle demande, qu'aucune promotion avec laquelle elle est incompatible ne s'est
   appliquée avant elle, et que ses lignes admissibles atteignent son minimum ;
3. **chaque rabais se retranche de ce qu'ont laissé les promotions précédentes**, et ne fait jamais passer une ligne
   sous zéro ;
4. une promotion exclusive qui donne quelque chose arrête celles qui la suivent.

Une promotion qui ne s'applique plus — retirée, terminée, son code enlevé — tombe au changement suivant.

Chaque rabais garde son origine sur la commande : `promotion:<code>`, et `promotion:<code>:<promotion code>` quand un
code l'a débloqué. Les origines sont figées avec la commande quand elle est passée, et c'est à partir d'elles que les
utilisations sont comptées. Chaque code saisi est consulté une seule fois par évaluation, et les libellés sont lus une
seule fois, en une requête, pour toutes les promotions essayées.

## Codes et utilisations

- `PromotionCode::of(code, promotion, usageLimit)` : de 3 à 64 lettres majuscules, chiffres, `_` ou `-` ; `1`
  utilisation pour un code donné à un seul acheteur, `null` pour un code que tous peuvent utiliser.
- `Promotions::generateCodes(promotion, count, prefix, usageLimit = 1)` tire un lot de 1 à 10 000 codes de huit
  caractères qu'une personne relit sans les confondre — aucun `0`, `O`, `1`, `I` ni `L` —, chacun différent des autres
  et des codes stockés.
- **Les utilisations sont comptées dans la transaction qui passe la commande** : `Promotions::redeem(order)` compte
  une utilisation de chaque promotion qu'a obtenue la commande passée, lue dans les origines de ses rabais — jamais en
  évaluant de nouveau les promotions, qui peuvent avoir changé depuis —, du code qui l'a débloquée, et du client quand
  la promotion limite ses utilisations par client. Une promotion retirée après que la commande a été passée est quand
  même comptée ; une promotion commencée après ne l'est pas.

  ```php
  $entityManager->wrapInTransaction(function () use ($modifier, $order, $promotionService, $at): void {
      $modifier->place($order, $at);
      $promotionService->redeem($order);
  });
  ```

- **Chaque promotion est comptée une seule fois par commande** : une rangée d'utilisation l'enregistre, avec le code et
  le client, si bien qu'une tâche relancée, ou une commande rouverte et passée de nouveau, ne compte rien deux fois ;
  `redeem()` retourne les utilisations qu'il a ajoutées.
- **Une utilisation est réclamée contre sa limite immédiatement** : l'adaptateur Doctrine en ajoute une par un
  `UPDATE` qui ne s'applique que tant que la rangée est sous sa limite. Deux commandes qui ont pris la dernière
  utilisation d'un code avant que l'une ou l'autre soit passée ne la comptent donc jamais toutes les deux. La première
  utilisation d'un client insère sa rangée ; deux premières utilisations simultanées entrent en collision sur sa clé
  primaire. Le `redeem()` de la seconde commande lève `PromotionRefused` — ou la base de données refuse la rangée en
  double —, et son passage est annulé avec les utilisations déjà réclamées ; rouverte et recalculée, la commande
  n'obtient plus la promotion. Hors d'une transaction, l'adaptateur lève une `LogicException`.
- Une promotion ou un code qui a atteint sa limite ne s'applique plus ; un client qui a atteint la sienne ne l'obtient
  plus.

## Ce que lit un retour

Les rabais restent sur leurs lignes, avec leurs origines, dans la commande passée. `phil/commerce-returns-bundle` les
lit : un produit d'un lot retourné seul est remboursé à sa valeur moins tout le rabais du lot, jamais sous zéro, les
produits gardés étant payés en entier ; tout autre rabais est repris comme la part que sa ligne porte. Un produit
composé se retourne en entier, jamais une de ses parties.

## Stockage

Avec DoctrineBundle :

| Colonne de `phil_commerce_promotion` | Type | Contient |
|---|---|---|
| `code` | `VARCHAR(64)`, clé primaire | Le code de la promotion |
| `label` | `VARCHAR(255)` | Ce que montre le reçu, dans le texte propre de la promotion |
| `exclusive` | `BOOLEAN` | Si elle arrête les autres |
| `action` | `VARCHAR(16)` | `percent_off`, `amount_off`, `buy_n_pay_x`, `bundle_price` ou `free_shipping` |
| `parts_per_million`, `amount`, `buy_quantity`, `pay_quantity` | `INTEGER`, `BIGINT`, `SMALLINT` | Ce qu'elle donne ; `amount` est le prix d'un lot ; zéro pour ce que son action n'utilise pas |
| `bundle_groups` | JSON | Les groupes d'un lot : leurs produits et leurs unités |
| `currency`, `minimum_subtotal` | `CHAR(3)`, `BIGINT` | Sa devise et son minimum |
| `skus`, `excluded_skus`, `channels`, `customer_groups`, `delivery_methods`, `owned_skus` | JSON | Ses conditions |
| `after_promotions`, `incompatible_with` | JSON | Son ordre et ses incompatibilités |
| `requires_code`, `starts_at`, `ends_at`, `usage_limit`, `per_customer_limit` | `BOOLEAN`, `DATETIME` en UTC (`datetime_utc_immutable`), `INTEGER` | Ses conditions |
| `uses`, `active` | `INTEGER`, `BOOLEAN` indexé | Ses utilisations, et `false` une fois retirée |

| Colonne de `phil_commerce_promotion_translation` | Type | Contient |
|---|---|---|
| `id` | `INTEGER`, clé primaire | La rangée |
| `promotion`, `locale` | `VARCHAR(64)` avec une clé étrangère, `VARCHAR(12)` ; uniques ensemble | La promotion, supprimée avec elle, et la locale |
| `label` | `VARCHAR(255)` | Le libellé dans cette locale ; vide tant qu'il n'est pas écrit |

| Colonne de `phil_commerce_promotion_code` | Type | Contient |
|---|---|---|
| `code` | `VARCHAR(64)`, clé primaire | Le code |
| `promotion` | `VARCHAR(64)`, indexé | La promotion qu'il débloque |
| `usage_limit`, `uses` | `INTEGER` | Sa limite et ses utilisations |

| Colonne de `phil_commerce_promotion_redemption` | Type | Contient |
|---|---|---|
| `order_number`, `promotion` | `VARCHAR(64)` chacune, clé primaire ensemble ; `promotion` indexé | La commande passée, et une promotion comptée pour elle |
| `code`, `customer` | `VARCHAR(64)`, nullable | Le code qui l'a débloquée, et le client qui a acheté |

| Colonne de `phil_commerce_promotion_customer_use` | Type | Contient |
|---|---|---|
| `promotion`, `customer` | `VARCHAR(64)` chacune, clé primaire ensemble | La promotion et le client |
| `uses` | `INTEGER` | Les utilisations que le client en a faites |
