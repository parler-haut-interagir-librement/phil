# Gifts and wish lists

## Gift options

An order is offered as a gift with `GiftOptions`: wrapped or not, in recycled wrap or not, with a message for the
person who receives it, and with its prices hidden from the documents that travel with the goods.

```php
use Phil\CommerceBundle\Domain\ValueObject\GiftOptions;

$modifier->offerAsGift($order, GiftOptions::of(wrapped: true, recycledWrap: true, message: "Happy birthday!", pricesHidden: true));
$modifier->offerAsGift($order, null); // no longer a gift
```

- **One wrapping per order**: the gift wrapping processor charges it as one `Fee` adjustment on the whole order, of
  origin `gift_wrap` or `gift_wrap:recycled`, and leaves the other fees alone.
- **The store decides what it offers** under `phil_commerce.gift_wrapping`: whether it wraps at all, whether it
  offers the recycled wrap, and the price of each per currency. `OrderModifier::offerAsGift()` refuses a wrapping the
  store does not offer; a message and hidden prices are always offered.
- **The message** holds 1 to 500 characters of text, line breaks allowed, no other control character. It belongs on
  the documents that travel with the goods — a packing slip, a gift receipt —, never on the receipt the buyer keeps.
- **Hidden prices** tell those documents to leave the amounts off; the buyer's receipt keeps them.
- **The wrapping is not taxed** by `TaxOrderProcessor`, which taxes lines, like any adjustment on the whole order; a
  store whose wrapping is taxed sells it as a sellable instead.

```yaml
phil_commerce:
    gift_wrapping:
        enabled: true
        recycled_option: true           # offers the recycled wrap
        label: 'Gift wrapping'          # the adjustment's label
        prices: { CAD: '4.00' }         # free in a currency without a price
        recycled_prices: { CAD: '3.00' } # the plain price when absent
```

The options are stored with the order, in the JSON column `gift`.

## Wish lists

A wish list holds what its owner wishes for, each thing with how many are wished for and how many others already
bought. Shared by a link, it lets others buy from it; the owner never learns who bought.

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

- **The owner is a stable identifier**, never an email address.
- **The link's token is a secret shown once**: 192 random bits; only its SHA-256 is stored. Sharing again gives a new
  token and the old link stops working; `WishList::stopSharing()` closes it. A malformed token is refused without a
  lookup.
- **A buyer buys only what is still wished for**, and an order buys from one list: `buyFrom()` refuses more units than
  are left, and marks the order with the list's id (`Order::wishList()`, column `wish_list`).
- **Units count once the order is paid in full**: `WishLists::recordPurchase()` listens to `OrderPurchased`
  ([payments.md](payments.md#an-order-paid-in-full)) and counts each line of the order against the list, never more
  than are still wished for.
- **A list holds at most 500 things**, each 1 to 9,999 units.

Lists are stored in `phil_commerce_wish_list`, their things in a JSON column, through `WishListRepository`
(`DoctrineWishListRepository` with DoctrineBundle).
