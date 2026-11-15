<!-- phil-dev source-sha256=03c070c07714efa95cf9c85924b8f9e20ea12725aefeffd0bd0f379581f627a4 -->
# Cadeaux et listes de souhaits

## Options de cadeau

Une commande est offerte en cadeau avec `GiftOptions` : emballée ou non, dans un emballage recyclé ou non, avec un
message pour la personne qui la reçoit, et avec ses prix cachés des documents qui voyagent avec la marchandise.

```php
use Phil\CommerceBundle\Domain\ValueObject\GiftOptions;

$modifier->offerAsGift($order, GiftOptions::of(wrapped: true, recycledWrap: true, message: "Happy birthday!", pricesHidden: true));
$modifier->offerAsGift($order, null); // no longer a gift
```

- **Un seul emballage par commande** : le processeur d'emballage-cadeau le facture comme un seul ajustement `Fee` sur
  toute la commande, d'origine `gift_wrap` ou `gift_wrap:recycled`, et laisse les autres frais tranquilles.
- **La boutique décide de ce qu'elle offre** sous `phil_commerce.gift_wrapping` : si elle emballe tout court, si elle
  offre l'emballage recyclé, et le prix de chacun par devise. `OrderModifier::offerAsGift()` refuse un emballage que la
  boutique n'offre pas ; un message et des prix cachés sont toujours offerts.
- **Le message** tient de 1 à 500 caractères de texte, sauts de ligne permis, aucun autre caractère de contrôle. Il
  va sur les documents qui voyagent avec la marchandise — un bordereau d'expédition, un reçu-cadeau —, jamais sur le
  reçu que l'acheteur garde.
- **Des prix cachés** disent à ces documents d'omettre les montants ; le reçu de l'acheteur les garde.
- **L'emballage n'est pas taxé** par `TaxOrderProcessor`, qui taxe les lignes, comme tout ajustement sur toute la
  commande ; une boutique dont l'emballage est taxé le vend plutôt comme un article vendable.

```yaml
phil_commerce:
    gift_wrapping:
        enabled: true
        recycled_option: true           # offers the recycled wrap
        label: 'Gift wrapping'          # the adjustment's label
        prices: { CAD: '4.00' }         # free in a currency without a price
        recycled_prices: { CAD: '3.00' } # the plain price when absent
```

Les options sont stockées avec la commande, dans la colonne JSON `gift`.

## Listes de souhaits

Une liste de souhaits contient ce que son propriétaire souhaite, chaque chose avec le nombre souhaité et le nombre que
d'autres ont déjà acheté. Partagée par un lien, elle permet à d'autres d'y acheter ; le propriétaire n'apprend jamais
qui a acheté.

```php
use Phil\CommerceBundle\Application\WishLists;

// $lists: WishLists, from the container
$list = $lists->create('member-42', 'Birthday', $now);
$list->wish(SellableReference::of('product', 'dice'), 2);
$token = $lists->share($list);          // shown once, in the link sent to friends

// A friend follows the link.
$list = $lists->shared($token);         // null for a link that opens nothing
$lists->buyFrom($list, $friendsCart, SellableReference::of('product', 'dice'), 1, $context);
```

- **Le propriétaire est un identifiant stable**, jamais une adresse de courriel.
- **Le jeton du lien est un secret montré une fois** : 192 bits aléatoires ; seule son empreinte SHA-256 est stockée.
  Partager de nouveau donne un nouveau jeton et l'ancien lien cesse de fonctionner ; `WishList::stopSharing()` le
  ferme. Un jeton mal formé est refusé sans recherche.
- **Un acheteur n'achète que ce qui est encore souhaité**, et une commande achète d'une seule liste : `buyFrom()`
  refuse plus d'unités qu'il n'en reste, et marque la commande de l'identifiant de la liste (`Order::wishList()`,
  colonne `wish_list`).
- **Les unités comptent une fois la commande payée en entier** : `WishLists::recordPurchase()` écoute `OrderPurchased`
  ([payments.md](payments.md#une-commande-payée-en-entier)) et compte chaque ligne de la commande contre la liste, jamais
  plus que ce qui est encore souhaité.
- **Une liste contient au plus 500 choses**, chacune de 1 à 9 999 unités.

Les listes sont stockées dans `phil_commerce_wish_list`, leurs choses dans une colonne JSON, par `WishListRepository`
(`DoctrineWishListRepository` avec DoctrineBundle).
