<!-- phil-dev source-sha256=113c47062325c88c39b2ff0592e849c457603d7442edba1773482949da167178 -->
# Livraison

Un mode de livraison est une façon dont une commande parvient à son acheteur : l'expédition par un transporteur, la
livraison locale ou le ramassage à un emplacement. Il dessert des zones, coûte un prix, peut devenir gratuit à partir
d'un seuil et prend un certain nombre de jours.

```php
use Phil\CommerceLogisticsBundle\Domain\Delivery\DeliveryKind;
use Phil\CommerceLogisticsBundle\Domain\Model\DeliveryMethod;

$methods->addMethod(DeliveryMethod::of('post', 'Postes Canada', DeliveryKind::Shipping,
    Money::of(1_200, $cad), freeFrom: Money::of(7_500, $cad), zones: ['QC', 'ON'], minDays: 2, maxDays: 5));
$methods->addMethod(DeliveryMethod::of('pickup-mtl', 'Ramassage au magasin', DeliveryKind::Pickup,
    Money::zero($cad), pickupLocation: 'store-mtl', priority: 10));

$options->for($order);                              // the methods serving the order, by priority
$modifier->chooseDeliveryMethod($order, 'post');    // the core's OrderModifier; the cost follows
```

| Argument de `DeliveryMethod::of()` | Règle |
|---|---|
| `code`, `label` | Jusqu'à 64 lettres minuscules, chiffres, `_`, `.` ou `-` ; un libellé que montre le reçu |
| `kind` | `Shipping`, `LocalDelivery`, `Pickup` |
| `price`, `freeFrom` | Zéro ou plus ; le seuil dans la même devise |
| `zones` | Nommées comme la zone de taxe de la commande ; toutes les zones quand la liste est vide |
| `minDays`, `maxDays` | De 0 à 365, le minimum avant le maximum |
| `pickupLocation` | Exigé pour un ramassage, refusé sinon |
| `priority` | L'ordre dans lequel les modes sont offerts |

## Ce que paie une commande

`ShippingProcessor` est un processeur de commande du noyau, étiqueté après le `PromotionProcessor` du catalogue et
avant le `TaxOrderProcessor` du noyau. Pour le mode que la commande a choisi
(`OrderModifier::chooseDeliveryMethod()`), s'il dessert toujours la zone de taxe de la commande dans sa devise, il
ajoute un ajustement `Shipping` libellé comme le mode : son prix, ou zéro une fois que les lignes, **après leurs
promotions**, atteignent son seuil. Un mode retiré, d'une autre zone ou inconnu n'ajoute rien ; l'application vérifie
`DeliveryOptions::chosen()` avant de passer la commande.

Les taxes du noyau s'appliquent aux lignes seulement : des frais de livraison taxables ne sont pas encore taxés.

## Stockage

Avec DoctrineBundle, un mode de livraison est une rangée de `phil_commerce_logistics_delivery_method` : `code` (clé
primaire), `label`, `kind`, `price`, `currency`, `free_from`, `zones` (JSON), `min_days`, `max_days`,
`pickup_location`, `priority`, `active`.
