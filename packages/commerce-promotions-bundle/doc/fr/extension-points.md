<!-- phil-dev source-sha256=30788e8608feb7945fd61d251f513d655719a5125a4e61fd78971decdaa7219b -->
# Points d'extension

Ce que le module branche dans `phil/commerce-bundle`, et ce qu'une application étend ou remplace. Les ports du noyau
et leurs contrats y sont décrits. Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa
version 1.0 ; d'ici là, une version mineure peut les changer, et son CHANGELOG dit comment.

## Les ports du noyau qu'il implémente

| Port | Service | Étiquette et ordre |
|---|---|---|
| `OrderProcessor` | `PromotionProcessor` | `phil_commerce.order_processor`, avant `TaxOrderProcessor` et `ManualDiscountProcessor` du noyau et `ShippingProcessor` du module de logistique |
| `OrderProcessor` | `FreeShippingProcessor` | `phil_commerce.order_processor`, après `PromotionProcessor` et `ShippingProcessor` du module de logistique, avant les taxes du noyau |

Le processeur du module de logistique est nommé par sa classe, sous forme de chaîne : la contrainte est ignorée quand
le module n'est pas installé. Le processeur propre à une application nomme ces services dans le `before` ou le `after`
de son étiquette pour prendre sa place dans l'ordre : un rabais à lui avant ou après les promotions.

## Ce qu'une application implémente

| Port | Quand |
|---|---|
| `Application\Contract\CustomerOwnership` | Pour tenir des promotions destinées aux propriétaires d'un produit (`ownedSkus`) : `ownsAny(customer, skus)` dit si le client a acheté, reçu ou débloqué l'un d'eux. Sans service, une telle promotion ne s'applique jamais. |

## Le dépôt

`Domain\Contract\PromotionRepository` est un alias public de `DoctrinePromotionRepository` ; une application le fait
pointer vers un de ses propres services sous le même contrat. `claimUse()` et `claimCustomerUse()` restent atomiques
contre leurs limites, si bien qu'un code ou un client limité en utilisations n'en a jamais une de trop.

## Les services

| Service | Comment une application l'utilise |
|---|---|
| `Promotions` | Crée des lots de codes, et compte les utilisations d'une commande passée ; public |
| `PromotionTexts` | Lit les libellés dans une locale et écrit leurs traductions ; public. Voyez [Configuration](configuration.md) |
| `PromotionEvaluator` | Ce qu'obtient une commande, pour un aperçu ; `originOf()` relit la promotion et le code à partir d'un rabais |
